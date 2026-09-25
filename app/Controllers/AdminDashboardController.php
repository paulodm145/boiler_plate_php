<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Migrator;
use App\Core\View;
use App\Database\Database;
use App\Repositories\UserRepository;

class AdminDashboardController
{
    public function index(): void
    {
        Auth::requireAuth();

        $pendingMigrationNames = [];

        try {
            $pendingMigrationNames = (new Migrator(new Database(), dirname(__DIR__, 2) . '/database/migrations'))->pending();
        } catch (\Throwable $exception) {
            $pendingMigrationNames = [];
        }

        View::render('admin/dashboard', [
            'title' => 'Painel | Admin',
            'user' => Auth::user(),
            'userCount' => (new UserRepository())->count(),
            'pendingMigrationNames' => $pendingMigrationNames,
            'pendingCount' => count($pendingMigrationNames),
        ]);
    }
}
