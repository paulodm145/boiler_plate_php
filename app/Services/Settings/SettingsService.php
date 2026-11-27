<?php

namespace App\Services\Settings;

use App\Mail\SmtpConfig;
use App\Repositories\SettingRepository;
use App\Services\ServiceResult;

/** Configuracoes do site e do SMTP (a senha SMTP vazia mantem a atual; SMTP_* do .env tem prioridade). */
final class SettingsService
{
    private SettingRepository $settings;

    public function __construct(?SettingRepository $settings = null)
    {
        $this->settings = $settings ?? new SettingRepository();
    }

    public function all(): array
    {
        return $this->settings->all();
    }

    public function update(array $data): ServiceResult
    {
        $this->settings->update($data);

        return ServiceResult::ok();
    }

    /** Campos do SMTP que vem do .env e que a tela nao controla. */
    public function smtpFromEnv(): array
    {
        return SmtpConfig::envOverriddenKeys();
    }
}
