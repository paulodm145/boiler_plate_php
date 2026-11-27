<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\InstallState;
use App\Core\View;
use App\Http\Flash;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\Settings\SettingsService;

class AdminSettingController
{
    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
    }

    public function edit(): void
    {
        View::render('admin/settings', [
            'title' => 'Configurações | Admin',
            'user' => Auth::user(),
            'settings' => $this->settings->all(),
            'smtpFromEnv' => $this->settings->smtpFromEnv(),
            'pendingCount' => (new InstallState(dirname(__DIR__, 2)))->pendingCount(),
        ]);
    }

    public function update(): void
    {
        $this->settings->update(UpdateSettingsRequest::validate());

        Flash::success('Configurações salvas.');
        http_response_code(303);
        header('Location: /admin/settings');
    }
}
