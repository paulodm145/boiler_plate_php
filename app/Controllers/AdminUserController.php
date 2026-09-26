<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ErrorPage;
use App\Core\InstallState;
use App\Core\Validator;
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
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $error = $this->validate($name, $email, (string) ($_POST['password'] ?? ''), null);

        if ($error !== null) {
            View::render('admin/user-form', [
                'title' => 'Novo usuario | Admin',
                'user' => Auth::user(),
                'adminUser' => ['id' => null, 'name' => $name, 'email' => $email],
                'isNew' => true,
                'error' => $error,
                'pendingCount' => $this->pendingMigrationsCount(),
            ]);
            return;
        }

        $this->users->create($_POST);
        header('Location: /admin/users');
    }

    public function edit(string $userUuid): void
    {
        $user = $this->users->findByUuid($userUuid);

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

    public function update(string $userUuid): void
    {
        $user = $this->users->findByUuid($userUuid);

        if ($user === null) {
            ErrorPage::notFound();
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $error = $this->validate($name, $email, (string) ($_POST['password'] ?? ''), (int) $user['id']);

        if ($error !== null) {
            View::render('admin/user-form', [
                'title' => 'Editar usuario | Admin',
                'user' => Auth::user(),
                'adminUser' => ['id' => (int) $user['id'], 'uuid' => $userUuid, 'name' => $name, 'email' => $email],
                'isNew' => false,
                'error' => $error,
                'pendingCount' => $this->pendingMigrationsCount(),
            ]);
            return;
        }

        $this->users->update((int) $user['id'], $_POST);
        header('Location: /admin/users');
    }

    public function delete(string $userUuid): void
    {
        $user = $this->users->findByUuid($userUuid);

        if ($user === null) {
            ErrorPage::notFound();
            return;
        }

        $currentUser = Auth::user();

        if (($currentUser['uuid'] ?? null) === $userUuid) {
            http_response_code(422);
            View::render('admin/user-form', [
                'title' => 'Editar usuario | Admin',
                'user' => $currentUser,
                'adminUser' => $user,
                'isNew' => false,
                'error' => 'Voce nao pode excluir o proprio usuario.',
                'pendingCount' => $this->pendingMigrationsCount(),
            ]);
            return;
        }

        $this->users->delete((int) $user['id']);
        header('Location: /admin/users');
    }

    // $excludeUserId: null pra um usuario novo (store()); o id do proprio
    // usuario pra edicao (update()), pra nao rejeitar o e-mail dele mesmo
    // sem ter mudado. $password vazio so e permitido quando $excludeUserId
    // nao e null (update sem trocar a senha) — UserRepository::update() ja
    // preserva o hash existente nesse caso.
    private function validate(string $name, string $email, string $password, ?int $excludeUserId): ?string
    {
        if (!Validator::required($name) || !Validator::required($email)) {
            return 'Informe o nome e o e-mail.';
        }

        if (!Validator::email($email)) {
            return 'Informe um e-mail valido.';
        }

        if ($password !== '' && !Validator::minLength($password, 8)) {
            return 'A senha precisa de pelo menos 8 caracteres.';
        }

        if ($excludeUserId === null && $password === '') {
            return 'Informe uma senha.';
        }

        $existing = $this->users->findByEmail($email);

        if ($existing !== null && ($excludeUserId === null || (int) $existing['id'] !== $excludeUserId)) {
            return 'Este e-mail ja esta em uso.';
        }

        return null;
    }

    private function pendingMigrationsCount(): int
    {
        return (new InstallState($this->rootPath))->pendingCount();
    }
}
