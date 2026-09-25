<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Repositories\SettingRepository;

class AdminSettingController
{
    private $settings;

    public function __construct()
    {
        Auth::requireAuth();
        $this->settings = new SettingRepository();
    }

    public function edit(): void
    {
        View::render('admin/settings', [
            'title' => 'Configuracoes | Admin',
            'user' => Auth::user(),
            'settings' => $this->settings->all(),
            'success' => ($_GET['saved'] ?? '') === '1',
        ]);
    }

    public function update(): void
    {
        $this->settings->update($_POST);
        header('Location: /admin/settings?saved=1');
    }
}
