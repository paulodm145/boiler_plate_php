<?php

declare(strict_types=1);

use App\Database\Database;
use App\Repositories\SettingRepository;

echo "\nSettingRepository\n";

function settingsFixture(): Database
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

it('all() devolve os defaults de SMTP quando nada foi salvo ainda', function (): void {
    $settings = new SettingRepository(settingsFixture());

    $all = $settings->all();

    assertSame('', $all['smtp_host']);
    assertSame('587', $all['smtp_port']);
    assertSame('tls', $all['smtp_encryption']);
});

it('update() grava os campos de SMTP', function (): void {
    $settings = new SettingRepository(settingsFixture());

    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_host' => 'smtp.exemplo.com',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl',
        'smtp_username' => 'usuario',
        'smtp_password' => 'segredo',
        'smtp_from_address' => 'nao-responda@exemplo.com',
        'smtp_from_name' => 'Meu App',
    ]);

    $all = $settings->all();
    assertSame('smtp.exemplo.com', $all['smtp_host']);
    assertSame('465', $all['smtp_port']);
    assertSame('ssl', $all['smtp_encryption']);
    assertSame('usuario', $all['smtp_username']);
    assertSame('segredo', $all['smtp_password']);
    assertSame('nao-responda@exemplo.com', $all['smtp_from_address']);
    assertSame('Meu App', $all['smtp_from_name']);
});

it('update() com smtp_host/smtp_username em branco sobrescreve (limpa) o valor salvo', function (): void {
    $settings = new SettingRepository(settingsFixture());
    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_host' => 'smtp.exemplo.com',
        'smtp_username' => 'usuario',
    ]);

    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_host' => '',
        'smtp_username' => '',
    ]);

    $all = $settings->all();
    assertSame('', $all['smtp_host'], 'smtp_host em branco deveria limpar o valor, nao preservar o antigo');
    assertSame('', $all['smtp_username']);
});

it('update() com smtp_password em branco preserva a senha ja salva', function (): void {
    $settings = new SettingRepository(settingsFixture());
    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_password' => 'senha-original',
    ]);

    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_password' => '',
    ]);

    assertSame('senha-original', $settings->all()['smtp_password']);
});

it('update() com smtp_encryption invalido cai pro default tls', function (): void {
    $settings = new SettingRepository(settingsFixture());

    $settings->update([
        'site_name' => 'App',
        'site_description' => 'desc',
        'smtp_encryption' => 'nao-e-uma-opcao-valida',
    ]);

    assertSame('tls', $settings->all()['smtp_encryption']);
});
