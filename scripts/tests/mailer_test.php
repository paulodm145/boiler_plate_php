<?php

declare(strict_types=1);

use App\Core\Mail\MailerInterface;
use App\Core\Mail\SmtpMailer;
use App\Database\Database;
use App\Repositories\SettingRepository;

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
