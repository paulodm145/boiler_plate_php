<?php

declare(strict_types=1);

echo "\nDatabase::transaction\n";

function txDatabase(): \App\Database\Database
{
    $db = testDatabase();
    $db->execute('DROP TABLE IF EXISTS tx_probe');
    $db->execute('CREATE TABLE tx_probe (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20)) ENGINE=InnoDB');

    return $db;
}

function txCount(\App\Database\Database $db): int
{
    return (int) $db->fetch('SELECT COUNT(*) AS total FROM tx_probe')['total'];
}

it('commit grava', function (): void {
    $db = txDatabase();
    $db->transaction(fn () => $db->execute("INSERT INTO tx_probe (name) VALUES ('a')"));

    assertSame(1, txCount($db));
});

it('excecao desfaz e relanca', function (): void {
    $db = txDatabase();

    assertThrows(function () use ($db): void {
        $db->transaction(function () use ($db): void {
            $db->execute("INSERT INTO tx_probe (name) VALUES ('a')");
            throw new RuntimeException('falhou');
        });
    });
    assertSame(0, txCount($db));
});

it('aninhada so faz commit no nivel externo', function (): void {
    $db = txDatabase();

    assertThrows(function () use ($db): void {
        $db->transaction(function () use ($db): void {
            $db->transaction(fn () => $db->execute("INSERT INTO tx_probe (name) VALUES ('interna')"));
            throw new RuntimeException('externa falhou');
        });
    });
    assertSame(0, txCount($db));
});

it('falha interna capturada ainda desfaz tudo', function (): void {
    $db = txDatabase();

    assertThrows(function () use ($db): void {
        $db->transaction(function () use ($db): void {
            $db->execute("INSERT INTO tx_probe (name) VALUES ('externa')");

            try {
                $db->transaction(function (): void {
                    throw new RuntimeException('interna');
                });
            } catch (RuntimeException $ignored) {
            }
        });
    });
    assertSame(0, txCount($db));
});
