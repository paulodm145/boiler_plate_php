<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ErrorPage;
use App\Core\InstallState;
use App\Core\View;
use App\Repositories\UserRepository;

class AdminUserController
{
    private $users;
    private $rootPath;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->rootPath = dirname(__DIR__, 2);
    }

    public function index(): void
    {
        View::render('admin/users', [
            'title' => 'Usuarios | Admin',
            'user' => Auth::user(),
            'users' => $this->users->all(),
            'pendingCount' => $this->pendingMigrationsCount(),
        ]);
    }

    public function create(): void
    {
        View::render('admin/user-form', [
            'title' => 'Novo usuario | Admin',
            'user' => Auth::user(),
            'adminUser' => ['id' => null, 'name' => '', 'email' => ''],
            'isNew' => true,
            'error' => null,
            'pendingCount' => $this->pendingMigrationsCount(),
        ]);
    }

    public function store(): void
    {
        $this->users->create($_POST);
        header('Location: /admin/users');
    }

    public function edit(string $id): void
    {
        $user = $this->users->find((int) $id);

        if ($user === null) {
            ErrorPage::notFound();
            return;
        }

        View::render('admin/user-form', [
            'title' => 'Editar usuario | Admin',
            'user' => Auth::user(),
            'adminUser' => $user,
            'isNew' => false,
            'error' => null,
            'pendingCount' => $this->pendingMigrationsCount(),
        ]);
    }

    public function update(string $id): void
    {
        $this->users->update((int) $id, $_POST);
        header('Location: /admin/users');
    }

    public function delete(string $id): void
    {
        $currentUser = Auth::user();

        if ((int) $currentUser['id'] === (int) $id) {
            http_response_code(422);
            View::render('admin/user-form', [
                'title' => 'Editar usuario | Admin',
                'user' => $currentUser,
                'adminUser' => $this->users->find((int) $id),
                'isNew' => false,
                'error' => 'Voce nao pode excluir o proprio usuario.',
                'pendingCount' => $this->pendingMigrationsCount(),
            ]);
            return;
        }

        $this->users->delete((int) $id);
        header('Location: /admin/users');
    }

    private function pendingMigrationsCount(): int
    {
        return (new InstallState($this->rootPath))->pendingCount();
    }
}
