<?php

declare(strict_types=1);

use App\Database\Database;
use App\Repositories\PasswordResetRepository;

echo "\nPasswordResetRepository\n";

function passwordResetsFixture(): Database
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(180) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    return $database;
}

it('create() grava e findByToken() encontra pelo token cru', function (): void {
    $database = passwordResetsFixture();
    $resets = new PasswordResetRepository($database);
    $token = 'token-de-teste-123';

    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    $found = $resets->findByToken($token);
    assertSame('ana@exemplo.com', $found['email']);
});

it('findByToken() devolve null pra um token que nunca existiu', function (): void {
    $database = passwordResetsFixture();
    $resets = new PasswordResetRepository($database);

    assertSame(null, $resets->findByToken('nunca-existiu'));
});

it('findByToken() devolve null pra um token ja expirado', function (): void {
    $database = passwordResetsFixture();
    $resets = new PasswordResetRepository($database);
    $token = 'token-expirado';

    $database->execute(
        'INSERT INTO password_resets (email, token_hash, expires_at, created_at) VALUES (:email, :hash, :expiry, :created)',
        [
            'email' => 'ana@exemplo.com',
            'hash' => hash('sha256', $token),
            'expiry' => gmdate('Y-m-d H:i:s', time() - 60),
            'created' => gmdate('Y-m-d H:i:s', time() - 3660),
        ]
    );

    assertSame(null, $resets->findByToken($token));
});

it('um segundo create() pro mesmo e-mail invalida o token anterior', function (): void {
    $database = passwordResetsFixture();
    $resets = new PasswordResetRepository($database);
    $firstToken = 'primeiro-token';
    $secondToken = 'segundo-token';

    $resets->create('ana@exemplo.com', hash('sha256', $firstToken), 60);
    $resets->create('ana@exemplo.com', hash('sha256', $secondToken), 60);

    assertSame(null, $resets->findByToken($firstToken), 'o primeiro token deveria ter parado de funcionar');
    assertSame('ana@exemplo.com', $resets->findByToken($secondToken)['email']);
});

it('deleteByToken() remove a linha', function (): void {
    $database = passwordResetsFixture();
    $resets = new PasswordResetRepository($database);
    $token = 'token-pra-apagar';

    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);
    $resets->deleteByToken($token);

    assertSame(null, $resets->findByToken($token));
});
