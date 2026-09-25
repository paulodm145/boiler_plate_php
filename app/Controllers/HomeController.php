<?php

namespace App\Controllers;

use App\Core\Env;
use App\Core\View;
use App\Repositories\SettingRepository;

class HomeController
{
    private $settings;

    public function __construct()
    {
        $this->settings = (new SettingRepository())->all();
    }

    public function index(): void
    {
        $appUrl = rtrim((string) Env::get('APP_URL', ''), '/');

        View::render('site/home', [
            'title' => $this->settings['site_name'],
            'description' => $this->settings['site_description'],
            'settings' => $this->settings,
            'active' => 'home',
            'canonical' => $appUrl !== '' ? $appUrl . '/' : null,
        ]);
    }
}
