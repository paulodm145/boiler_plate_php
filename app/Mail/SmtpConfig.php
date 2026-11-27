<?php

namespace App\Mail;

use App\Core\Env;
use App\Repositories\SettingRepository;

/**
 * Configuracao do SMTP: tela de Configuracoes (tabela settings) com as variaveis
 * SMTP_* do .env por cima, campo a campo. SMTP_PASSWORD so vale junto com
 * SMTP_HOST, e com ela a porta e a criptografia tambem saem do .env (padrao
 * 587/tls, nunca sem criptografia): quem edita a tela nao consegue mandar a
 * senha do .env para outro servidor.
 */
final class SmtpConfig
{
    /**
     * Variaveis do .env que, quando definidas, tem prioridade sobre os
     * campos de SMTP da tela Configuracoes (tabela settings). Assim a senha
     * do e-mail de producao fica so no .env do servidor, fora do banco.
     */
    public const ENV_KEYS = [
        'smtp_host' => 'SMTP_HOST',
        'smtp_port' => 'SMTP_PORT',
        'smtp_encryption' => 'SMTP_ENCRYPTION',
        'smtp_username' => 'SMTP_USERNAME',
        'smtp_password' => 'SMTP_PASSWORD',
        'smtp_from_address' => 'SMTP_FROM_ADDRESS',
        'smtp_from_name' => 'SMTP_FROM_NAME',
    ];

    private function __construct()
    {
    }

    /** A configuracao em vigor agora: settings do banco + .env. */
    public static function current(?SettingRepository $settings = null): array
    {
        return self::resolve(($settings ?? new SettingRepository())->all());
    }

    /** Configuracao de settings com as variaveis SMTP_* do .env por cima (as que valem, ver envOverrides()). */
    public static function resolve(array $settings): array
    {
        $overrides = self::envOverrides();

        if (isset($overrides['smtp_password'])) {
            // Senha do .env: a conexao inteira sai do .env, nunca da tela. Senao,
            // quem edita a tela trocaria o host e receberia a senha do servidor.
            $settings['smtp_port'] = '587';
            $settings['smtp_encryption'] = 'tls';
        }

        $settings = array_merge($settings, $overrides);

        $allowed = isset($overrides['smtp_password']) ? ['tls', 'ssl'] : ['tls', 'ssl', 'none'];
        $settings['smtp_encryption'] = in_array($settings['smtp_encryption'] ?? '', $allowed, true)
            ? $settings['smtp_encryption']
            : 'tls';

        return $settings;
    }

    /** @return string[] chaves de settings que a tela nao controla (vem do .env), na ordem de ENV_KEYS */
    public static function envOverriddenKeys(): array
    {
        $overrides = self::envOverrides();

        return array_values(array_filter(array_keys(self::ENV_KEYS), static function (string $key) use ($overrides): bool {
            // Com a senha no .env, porta e criptografia tambem saem de la (padrao 587/tls).
            return isset($overrides[$key])
                || (isset($overrides['smtp_password']) && in_array($key, ['smtp_port', 'smtp_encryption'], true));
        }));
    }

    /**
     * Variaveis SMTP_* preenchidas, por chave de settings. SMTP_PASSWORD so vale
     * junto com SMTP_HOST: sem host no .env a senha do .env e ignorada.
     *
     * @return array<string, string>
     */
    private static function envOverrides(): array
    {
        $overrides = [];

        foreach (self::ENV_KEYS as $settingKey => $envKey) {
            $value = (string) Env::get($envKey, '');

            if ($value !== '') {
                $overrides[$settingKey] = $value;
            }
        }

        if (isset($overrides['smtp_password']) && !isset($overrides['smtp_host'])) {
            unset($overrides['smtp_password']);
        }

        return $overrides;
    }
}
