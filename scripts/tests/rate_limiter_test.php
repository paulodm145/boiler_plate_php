<?php

declare(strict_types=1);

use App\Core\RateLimiter;
use App\Database\Database;

echo "\nRateLimiter\n";

function rateLimitsFixture(): Database
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(191) NOT NULL UNIQUE,
            attempts INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    RateLimiter::setConnection($database);

    return $database;
}

it('tooManyAttempts() e falso quando a chave nunca foi usada', function (): void {
    rateLimitsFixture();

    assertFalse(RateLimiter::tooManyAttempts('login:1.2.3.4', 5));
});

it('hit() incrementa ate estourar o limite', function (): void {
    rateLimitsFixture();
    $key = 'login:1.2.3.4';

    for ($i = 0; $i < 4; $i++) {
        RateLimiter::hit($key, 15);
    }
    assertFalse(RateLimiter::tooManyAttempts($key, 5));

    RateLimiter::hit($key, 15);
    assertTrue(RateLimiter::tooManyAttempts($key, 5));
});

it('hit() reseta a contagem quando a janela anterior ja expirou', function (): void {
    $database = rateLimitsFixture();
    $key = 'login:1.2.3.4';

    $database->execute(
        'INSERT INTO rate_limits (rate_key, attempts, expires_at) VALUES (:key, 5, :expired)',
        ['key' => $key, 'expired' => gmdate('Y-m-d H:i:s', time() - 60)]
    );

    RateLimiter::hit($key, 15);

    $row = $database->fetch('SELECT attempts FROM rate_limits WHERE rate_key = :key', ['key' => $key]);
    assertSame(1, (int) $row['attempts'], 'janela expirada deveria reiniciar a contagem em 1, nao continuar de 5');
    assertFalse(RateLimiter::tooManyAttempts($key, 5));
});

it('hit() soma corretamente em chamadas sucessivas (a atomicidade vem da propria query SQL)', function (): void {
    // Nao da pra reproduzir concorrencia real de dois processos PHP num
    // teste de processo unico. O que da pra confirmar aqui e que a query
    // (INSERT...ON DUPLICATE KEY UPDATE) soma certo em sequencia; a
    // garantia contra concorrencia real e a query ser uma unica instrucao
    // atomica no MySQL, nao alguma logica PHP que este teste possa
    // exercitar isoladamente.
    $database = rateLimitsFixture();
    $key = 'login:1.2.3.4';

    RateLimiter::hit($key, 15);
    RateLimiter::hit($key, 15);
    RateLimiter::hit($key, 15);

    $row = $database->fetch('SELECT attempts FROM rate_limits WHERE rate_key = :key', ['key' => $key]);
    assertSame(3, (int) $row['attempts']);
});

it('clear() remove a chave e tooManyAttempts() volta a falso', function (): void {
    rateLimitsFixture();
    $key = 'login:1.2.3.4';

    for ($i = 0; $i < 5; $i++) {
        RateLimiter::hit($key, 15);
    }
    assertTrue(RateLimiter::tooManyAttempts($key, 5));

    RateLimiter::clear($key);

    assertFalse(RateLimiter::tooManyAttempts($key, 5));
});

it('availableInSeconds() bate com o expires_at gravado', function (): void {
    rateLimitsFixture();
    $key = 'login:1.2.3.4';

    RateLimiter::hit($key, 15);

    $seconds = RateLimiter::availableInSeconds($key);
    assertTrue($seconds > 14 * 60 && $seconds <= 15 * 60, "esperado por volta de 900s, recebido {$seconds}");
});

it('availableInSeconds() e zero pra uma chave sem linha', function (): void {
    rateLimitsFixture();

    assertSame(0, RateLimiter::availableInSeconds('login:nunca-usada'));
});
