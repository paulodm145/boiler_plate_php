<?php

declare(strict_types=1);

use App\Http\ListQuery;
use App\Repositories\UserRepository;
use App\Services\User\UserService;

echo "\nUserService\n";

function usersTable(): \App\Database\Database
{
    $db = testDatabase();
    $db->connection()->exec(
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

    return $db;
}

function userService(\App\Database\Database $db): UserService
{
    return new UserService(new UserRepository($db));
}

function seedUser(UserService $service, string $name, string $email): array
{
    $result = $service->create(['name' => $name, 'email' => $email, 'password' => 'segredo123']);
    assertTrue($result->isOk());

    return $result->data();
}

it('create grava com hash e devolve o usuario sem o id interno', function (): void {
    $db = usersTable();
    $user = seedUser(userService($db), 'Ana', 'ana@exemplo.com');
    $row = $db->fetch("SELECT password FROM users WHERE email = 'ana@exemplo.com'");

    assertTrue(password_verify('segredo123', $row['password']));
    assertSame('Ana', $user['name']);
    assertFalse(array_key_exists('id', $user));
    assertFalse(array_key_exists('password', $user));
});

it('create com e-mail existente falha com email_taken', function (): void {
    $service = userService(usersTable());
    seedUser($service, 'Ana', 'ana@exemplo.com');

    assertSame('email_taken', $service->create(['name' => 'Outra', 'email' => 'ana@exemplo.com', 'password' => 'x12345678'])->code());
});

it('update sem senha mantem o hash; com senha troca', function (): void {
    $db = usersTable();
    $service = userService($db);
    $user = seedUser($service, 'Ana', 'ana@exemplo.com');
    $before = $db->fetch("SELECT password FROM users WHERE email = 'ana@exemplo.com'")['password'];

    assertTrue($service->update($user['uuid'], ['name' => 'Ana Maria', 'email' => 'ana@exemplo.com', 'password' => null])->isOk());
    assertSame($before, $db->fetch("SELECT password FROM users WHERE email = 'ana@exemplo.com'")['password']);

    $service->update($user['uuid'], ['name' => 'Ana Maria', 'email' => 'ana@exemplo.com', 'password' => 'nova-senha-1']);
    assertTrue(password_verify('nova-senha-1', $db->fetch("SELECT password FROM users WHERE email = 'ana@exemplo.com'")['password']));
});

it('update com e-mail de outra pessoa falha com email_taken', function (): void {
    $service = userService(usersTable());
    seedUser($service, 'Ana', 'ana@exemplo.com');
    $bia = seedUser($service, 'Bia', 'bia@exemplo.com');

    assertSame('email_taken', $service->update($bia['uuid'], ['name' => 'Bia', 'email' => 'ana@exemplo.com'])->code());
});

it('update de uuid inexistente falha com not_found', function (): void {
    $service = userService(usersTable());

    assertSame('not_found', $service->update('00000000-0000-4000-8000-000000000000', ['name' => 'X', 'email' => 'x@x.com'])->code());
});

it('delete do proprio usuario falha e mantem o registro', function (): void {
    $db = usersTable();
    $service = userService($db);
    $ana = seedUser($service, 'Ana', 'ana@exemplo.com');

    assertSame('cannot_delete_self', $service->delete($ana['uuid'], $ana)->code());
    assertSame(1, (int) $db->fetch('SELECT COUNT(*) AS total FROM users')['total']);
});

it('delete de uuid inexistente falha com not_found', function (): void {
    $service = userService(usersTable());
    $ana = seedUser($service, 'Ana', 'ana@exemplo.com');

    assertSame('not_found', $service->delete('00000000-0000-4000-8000-000000000000', $ana)->code());
});

it('delete de outro usuario remove', function (): void {
    $db = usersTable();
    $service = userService($db);
    $ana = seedUser($service, 'Ana', 'ana@exemplo.com');
    $bia = seedUser($service, 'Bia', 'bia@exemplo.com');

    assertTrue($service->delete($bia['uuid'], $ana)->isOk());
    assertSame(null, $service->find($bia['uuid']));
});

it('list busca por nome ou e-mail e ordena', function (): void {
    $service = userService(usersTable());
    seedUser($service, 'Ana', 'ana@exemplo.com');
    seedUser($service, 'Bia', 'bia@outro.com');
    seedUser($service, 'Caio', 'caio@exemplo.com');

    $byEmail = $service->list(ListQuery::fromRequest(UserRepository::SORTABLE, 'name', 'asc', 25, ['q' => 'exemplo', 'sort' => 'email', 'dir' => 'desc']));
    assertSame(2, $byEmail->total);
    assertSame('caio@exemplo.com', $byEmail->items[0]['email']);

    $byName = $service->list(ListQuery::fromRequest(UserRepository::SORTABLE, 'name', 'asc', 25, ['q' => 'Bia']));
    assertSame(1, $byName->total);
    assertFalse(array_key_exists('password', $byName->items[0]));
    assertFalse(array_key_exists('id', $byName->items[0]));
});
