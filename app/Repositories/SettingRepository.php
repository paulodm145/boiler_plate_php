<?php

namespace App\Repositories;

use App\Database\Database;

/**
 * Configuracoes chave/valor da aplicacao (app/Views/admin/settings.php).
 * $defaults define o schema — so chaves listadas aqui sao aceitas em
 * all()/update(); adicione a chave nos dois lugares ao introduzir uma
 * nova configuracao.
 */
class SettingRepository
{
    private $database;

    private $defaults = [
        'site_name' => 'App Boilerplate',
        'site_description' => 'Boilerplate de PHP puro (MVC hand-rolled, sem framework).',
    ];

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?: Database::shared();
    }

    public function all(): array
    {
        $settings = $this->defaults;
        $rows = $this->database->fetchAll('SELECT setting_key, setting_value FROM settings');

        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = (string) $row['setting_value'];
            }
        }

        return $settings;
    }

    public function update(array $data): void
    {
        $settings = [
            'site_name' => $this->requiredText($data['site_name'] ?? '', $this->defaults['site_name']),
            'site_description' => trim((string) ($data['site_description'] ?? '')),
        ];

        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    private function set(string $key, string $value): void
    {
        $this->database->execute(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            ['setting_key' => $key, 'setting_value' => $value]
        );
    }

    private function requiredText(string $value, string $fallback): string
    {
        $value = trim($value);

        return $value !== '' ? $value : $fallback;
    }
}
