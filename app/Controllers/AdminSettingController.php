<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\InstallState;
use App\Core\View;
use App\Repositories\SettingRepository;

class AdminSettingController
{
    private $settings;

    public function __construct()
    {
        $this->settings = new SettingRepository();
    }

    public function edit(): void
    {
        View::render('admin/settings', [
            'title' => 'Configuracoes | Admin',
            'user' => Auth::user(),
            'settings' => $this->settings->all(),
            'success' => ($_GET['saved'] ?? '') === '1',
            'smtpFromEnv' => \App\Core\Mail\SmtpMailer::envOverriddenKeys(),
            'pendingCount' => (new InstallState(dirname(__DIR__, 2)))->pendingCount(),
        ]);
    }

    public function update(): void
    {
        $this->settings->update($_POST);
        header('Location: /admin/settings?saved=1');
    }
}
