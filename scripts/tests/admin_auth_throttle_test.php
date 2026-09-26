<?php

declare(strict_types=1);

use App\Controllers\AdminAuthController;
use App\Core\Auth;
use App\Core\RateLimiter;
use App\Database\Database;
use App\Repositories\UserRepository;

echo "\nAdminAuthController::login() throttle\n";

// AdminAuthController nao aceita injecao de UserRepository (e um
// Controller) -- trocamos a propriedade privada $users por reflection,
// igual ja fizemos com AdminUserController::validate().
function controllerWithTestUsersAndThrottle(): array
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(191) NOT NULL UNIQUE,
            attempts INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    RateLimiter::setConnection($database);

    $users = new UserRepository($database);
    $controller = new AdminAuthController();
    $usersProperty = new ReflectionProperty(AdminAuthController::class, 'users');
    $usersProperty->setAccessible(true);
    $usersProperty->setValue($controller, $users);

    // Cada it() roda no mesmo processo PHP (a sessao e compartilhada) --
    // garante que nenhum teste comeca com uma sessao logada por um
    // anterior.
    Auth::logout();

    return [$controller, $users];
}

function attemptLogin(AdminAuthController $controller, string $email, string $password): array
{
    $_POST['email'] = $email;
    $_POST['password'] = $password;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

    ob_start();
    $controller->login();
    $body = ob_get_clean();

    return ['status' => http_response_code(), 'body' => $body];
}

it('bloqueia a 6a tentativa apos 5 falhas seguidas, mesmo com a senha certa', function (): void {
    [$controller, $users] = controllerWithTestUsersAndThrottle();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    for ($i = 0; $i < 5; $i++) {
        $result = attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
        assertSame(422, $result['status']);
    }

    $result = attemptLogin($controller, 'ana@exemplo.com', 'segredo123');

    assertSame(429, $result['status']);
    assertContains('Muitas tentativas', $result['body']);
});

it('login certo dentro do limite nao e bloqueado e limpa o contador', function (): void {
    [$controller, $users] = controllerWithTestUsersAndThrottle();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
    attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
    attemptLogin($controller, 'ana@exemplo.com', 'segredo123');

    assertTrue(Auth::check(), 'login certo deveria autenticar a sessao');
    assertFalse(RateLimiter::tooManyAttempts('login:203.0.113.5', 5), 'login certo deveria ter limpado o contador de tentativas');
});

it('uma tentativa errada depois de um login certo comeca uma contagem nova, nao continua a antiga', function (): void {
    [$controller, $users] = controllerWithTestUsersAndThrottle();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
    attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
    attemptLogin($controller, 'ana@exemplo.com', 'segredo123');

    for ($i = 0; $i < 5; $i++) {
        $result = attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
        assertSame(422, $result['status'], 'tentativa ' . ($i + 1) . ' da janela nova nao deveria estar bloqueada ainda');
    }

    $result = attemptLogin($controller, 'ana@exemplo.com', 'senha-errada');
    assertSame(429, $result['status'], 'a 6a tentativa errada apos o sucesso deveria bloquear, contando do zero a partir do sucesso');
});
