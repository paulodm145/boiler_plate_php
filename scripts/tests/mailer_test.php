<?php

declare(strict_types=1);

use App\Core\Mail\MailerInterface;
use App\Core\Mail\SmtpMailer;
use App\Database\Database;
use App\Repositories\SettingRepository;
use PHPMailer\PHPMailer\PHPMailer;

echo "\nSmtpMailer\n";

function settingsFixtureForMailer(): Database
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(80) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    return $database;
}

it('SmtpMailer implementa MailerInterface', function (): void {
    $settings = new SettingRepository(settingsFixtureForMailer());

    assertTrue(new SmtpMailer($settings) instanceof MailerInterface);
});

it('send() devolve false sem lancar excecao quando o SMTP nao esta configurado', function (): void {
    $settings = new SettingRepository(settingsFixtureForMailer());
    $mailer = new SmtpMailer($settings);

    assertFalse($mailer->send('ana@exemplo.com', 'Assunto', 'Corpo'));
});

it('configure() seta CharSet UTF-8 e os dados de SMTP no objeto PHPMailer', function (): void {
    $settings = new SettingRepository(settingsFixtureForMailer());
    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_host' => 'smtp.exemplo.com',
        'smtp_port' => '2525',
        'smtp_encryption' => 'tls',
        'smtp_username' => 'usuario',
        'smtp_password' => 'segredo',
        'smtp_from_address' => 'de@exemplo.com',
        'smtp_from_name' => 'Suporte da Aplicação',
    ]);
    $mailer = new SmtpMailer($settings);
    $mail = new PHPMailer(true);

    $method = new ReflectionMethod(SmtpMailer::class, 'configure');
    $method->setAccessible(true);
    $method->invoke($mailer, $mail, $settings->all());

    assertSame('utf-8', $mail->CharSet, 'sem CharSet explicito, nome de remetente acentuado sai mal codificado no cabecalho From');
    assertSame('smtp.exemplo.com', $mail->Host);
    assertSame(2525, $mail->Port);
    assertSame('usuario', $mail->Username);
});

it('variaveis SMTP_* do .env tem prioridade sobre a tela de configuracoes', function (): void {
    $base = ['smtp_host' => 'tela.exemplo.com', 'smtp_port' => '25', 'smtp_encryption' => 'none', 'smtp_username' => 'u',
        'smtp_password' => 'p', 'smtp_from_address' => 'a@x.com', 'smtp_from_name' => 'Tela'];
    putenv('SMTP_HOST=env.exemplo.com');
    putenv('SMTP_PASSWORD=senha-do-env');

    try {
        $config = \App\Core\Mail\SmtpMailer::resolveConfig($base);
        assertSame('env.exemplo.com', $config['smtp_host']);
        assertSame('senha-do-env', $config['smtp_password']);
        assertSame('u', $config['smtp_username'], 'o que nao esta no .env continua vindo da tela');
        assertSame(
            ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_password'],
            \App\Core\Mail\SmtpMailer::envOverriddenKeys(),
            'com a senha no .env, porta e criptografia tambem nao saem da tela'
        );
    } finally {
        putenv('SMTP_HOST');
        putenv('SMTP_PASSWORD');
    }
});

it('criptografia invalida vira tls', function (): void {
    putenv('SMTP_ENCRYPTION=xpto');

    try {
        assertSame('tls', \App\Core\Mail\SmtpMailer::resolveConfig(['smtp_encryption' => 'none'])['smtp_encryption']);
    } finally {
        putenv('SMTP_ENCRYPTION');
    }
});

it('SMTP_PASSWORD vazio no .env nao apaga a senha salva', function (): void {
    putenv('SMTP_PASSWORD=');

    try {
        assertSame('salva', \App\Core\Mail\SmtpMailer::resolveConfig(['smtp_password' => 'salva'])['smtp_password']);
        assertSame([], \App\Core\Mail\SmtpMailer::envOverriddenKeys());
    } finally {
        putenv('SMTP_PASSWORD');
    }
});

it('senha do .env nunca vai para um servidor que veio da tela', function (): void {
    // Qualquer usuario logado edita a tela: com a senha no .env e o host so no
    // banco, trocar o host mandaria a senha do .env para outro servidor.
    putenv('SMTP_PASSWORD=senha-do-env');

    try {
        $config = \App\Core\Mail\SmtpMailer::resolveConfig(['smtp_host' => 'atacante.exemplo.com', 'smtp_password' => 'da-tela']);
        assertSame('da-tela', $config['smtp_password']);
        assertSame([], \App\Core\Mail\SmtpMailer::envOverriddenKeys());
    } finally {
        putenv('SMTP_PASSWORD');
    }
});

it('com a senha no .env, porta e criptografia da tela sao ignoradas', function (): void {
    putenv('SMTP_HOST=env.exemplo.com');
    putenv('SMTP_PASSWORD=senha-do-env');

    try {
        $config = \App\Core\Mail\SmtpMailer::resolveConfig(['smtp_port' => '25', 'smtp_encryption' => 'none']);
        assertSame('587', $config['smtp_port']);
        assertSame('tls', $config['smtp_encryption']);
    } finally {
        putenv('SMTP_HOST');
        putenv('SMTP_PASSWORD');
    }
});

it('com a senha no .env, criptografia none no .env vira tls', function (): void {
    putenv('SMTP_HOST=env.exemplo.com');
    putenv('SMTP_PASSWORD=senha-do-env');
    putenv('SMTP_ENCRYPTION=none');

    try {
        assertSame('tls', \App\Core\Mail\SmtpMailer::resolveConfig([])['smtp_encryption']);
    } finally {
        putenv('SMTP_HOST');
        putenv('SMTP_PASSWORD');
        putenv('SMTP_ENCRYPTION');
    }
});
