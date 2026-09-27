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
