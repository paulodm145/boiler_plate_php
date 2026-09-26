<?php

declare(strict_types=1);

use App\Controllers\AdminUserController;
use App\Database\Database;
use App\Repositories\UserRepository;

echo "\nAdminUserController::validate()\n";

// AdminUserController nao aceita injecao de Database (e um Controller,
// nao um Repository) — pra testar validate() (que so depende de
// UserRepository::findByEmail(), sem tocar $_POST/View::render()/header())
// contra uma tabela isolada, trocamos a propriedade privada $users por
// reflection em vez de bater na conexao real do .env.
function controllerWithTestUsers(): AdminUserController
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $controller = new AdminUserController();
    $usersProperty = new ReflectionProperty(AdminUserController::class, 'users');
    $usersProperty->setAccessible(true);
    $usersProperty->setValue($controller, new UserRepository($database));

    return $controller;
}

function validate(AdminUserController $controller, string $name, string $email, string $password, ?int $excludeUserId)
{
    $method = new ReflectionMethod(AdminUserController::class, 'validate');
    $method->setAccessible(true);

    return $method->invoke($controller, $name, $email, $password, $excludeUserId);
}

it('validate() recusa nome ou email vazio', function (): void {
    $controller = controllerWithTestUsers();

    assertSame('Informe o nome e o e-mail.', validate($controller, '', 'a@x.com', 'segredo123', null));
    assertSame('Informe o nome e o e-mail.', validate($controller, 'Ana', '', 'segredo123', null));
});

it('validate() recusa email mal formado', function (): void {
    $controller = controllerWithTestUsers();

    assertSame('Informe um e-mail valido.', validate($controller, 'Ana', 'nao-e-email', 'segredo123', null));
});

it('validate() recusa senha vazia ao criar um usuario novo', function (): void {
    $controller = controllerWithTestUsers();

    assertSame('Informe uma senha.', validate($controller, 'Ana', 'ana@exemplo.com', '', null));
});

it('validate() permite senha vazia ao editar (mantem o hash existente)', function (): void {
    $controller = controllerWithTestUsers();

    assertSame(null, validate($controller, 'Ana', 'ana@exemplo.com', '', 1));
});

it('validate() recusa senha curta, tanto criando quanto editando', function (): void {
    $controller = controllerWithTestUsers();

    assertSame('A senha precisa de pelo menos 8 caracteres.', validate($controller, 'Ana', 'ana@exemplo.com', '123', null));
    assertSame('A senha precisa de pelo menos 8 caracteres.', validate($controller, 'Ana', 'ana@exemplo.com', '123', 1));
});

it('validate() recusa e-mail ja em uso por outro usuario', function (): void {
    $controller = controllerWithTestUsers();
    $usersProperty = new ReflectionProperty(AdminUserController::class, 'users');
    $usersProperty->setAccessible(true);
    $users = $usersProperty->getValue($controller);
    $users->create(['name' => 'Bia', 'email' => 'bia@exemplo.com', 'password' => 'segredo123']);

    assertSame('Este e-mail ja esta em uso.', validate($controller, 'Ana', 'bia@exemplo.com', 'segredo123', null));
});

it('validate() permite manter o proprio e-mail ao editar', function (): void {
    $controller = controllerWithTestUsers();
    $usersProperty = new ReflectionProperty(AdminUserController::class, 'users');
    $usersProperty->setAccessible(true);
    $users = $usersProperty->getValue($controller);
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $ana = $users->findByEmail('ana@exemplo.com');

    assertSame(null, validate($controller, 'Ana Maria', 'ana@exemplo.com', '', (int) $ana['id']));
});

it('validate() aceita dados validos pra um usuario novo', function (): void {
    $controller = controllerWithTestUsers();

    assertSame(null, validate($controller, 'Ana', 'ana@exemplo.com', 'segredo123', null));
});
