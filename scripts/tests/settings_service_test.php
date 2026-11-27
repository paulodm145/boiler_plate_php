<?php

declare(strict_types=1);

use App\Repositories\SettingRepository;
use App\Services\Settings\SettingsService;

echo "\nSettingsService\n";

function settingsService(): SettingsService
{
    $db = testDatabase();
    $db->connection()->exec(
        'CREATE TABLE settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(80) NOT NULL UNIQUE,
            setting_value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    return new SettingsService(new SettingRepository($db));
}

it('update grava e preserva a senha SMTP quando o campo vem vazio', function (): void {
    $service = settingsService();
    $base = ['site_name' => 'Meu App', 'site_description' => '', 'smtp_host' => 'smtp.x.com', 'smtp_port' => '587',
        'smtp_encryption' => 'tls', 'smtp_username' => 'u', 'smtp_from_address' => '', 'smtp_from_name' => 'App'];

    assertTrue($service->update($base + ['smtp_password' => 'secreta'])->isOk());
    assertTrue($service->update(['site_name' => 'Outro'] + $base + ['smtp_password' => ''])->isOk());

    $settings = $service->all();
    assertSame('Outro', $settings['site_name']);
    assertSame('secreta', $settings['smtp_password']);
});
