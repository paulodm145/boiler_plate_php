<?php

declare(strict_types=1);

use App\Core\Migrator;

echo "\nMigrator\n";

function migrationsFixture(array $files): string
{
    $dir = tempDir();

    foreach ($files as $name => $sql) {
        file_put_contents($dir . '/' . $name, $sql);
    }

    return $dir;
}

it('pending lista todas as migrations num banco vazio, em ordem', function (): void {
    $dir = migrationsFixture([
        '002_b.sql' => 'CREATE TABLE b (id INT);',
        '001_a.sql' => 'CREATE TABLE a (id INT);',
    ]);

    $migrator = new Migrator(testDatabase(), $dir);

    assertSame(['001_a.sql', '002_b.sql'], $migrator->pending());
});

it('runOne executa e some da lista de pendentes', function (): void {
    $dir = migrationsFixture([
        '001_a.sql' => 'CREATE TABLE a (id INT);',
        '002_b.sql' => 'CREATE TABLE b (id INT);',
    ]);

    $migrator = new Migrator(testDatabase(), $dir);
    $result = $migrator->runOne('001_a.sql');

    assertSame('executed', $result['status']);
    assertSame('001_a.sql', $result['migration']);
    assertSame(null, $result['error']);
    assertTrue($result['duration_ms'] >= 0);
    assertSame(['002_b.sql'], $migrator->pending());
});

it('runOne devolve skipped para migration ja executada', function (): void {
    $dir = migrationsFixture(['001_a.sql' => 'CREATE TABLE a (id INT);']);

    $migrator = new Migrator(testDatabase(), $dir);
    $migrator->runOne('001_a.sql');

    assertSame('skipped', $migrator->runOne('001_a.sql')['status']);
});

it('runOne devolve empty para arquivo em branco e registra para nao repetir', function (): void {
    $dir = migrationsFixture(['001_vazia.sql' => "\n  \n"]);

    $migrator = new Migrator(testDatabase(), $dir);

    assertSame('empty', $migrator->runOne('001_vazia.sql')['status']);
    assertSame([], $migrator->pending());
});

it('runOne captura erro de SQL sem lancar excecao', function (): void {
    $dir = migrationsFixture(['001_ruim.sql' => 'CREATE TABLE (((;']);

    $migrator = new Migrator(testDatabase(), $dir);
    $result = $migrator->runOne('001_ruim.sql');

    assertSame('error', $result['status']);
    assertTrue(is_string($result['error']) && $result['error'] !== '');
    assertSame(['001_ruim.sql'], $migrator->pending());
});

it('runOne recusa nome fora do diretorio de migrations', function (): void {
    $dir = migrationsFixture(['001_a.sql' => 'CREATE TABLE a (id INT);']);

    $migrator = new Migrator(testDatabase(), $dir);
    $result = $migrator->runOne('../../.env');

    assertSame('error', $result['status']);
});

it('run continua funcionando', function (): void {
    $dir = migrationsFixture([
        '001_a.sql' => 'CREATE TABLE a (id INT);',
        '002_b.sql' => 'CREATE TABLE b (id INT);',
    ]);

    $results = (new Migrator(testDatabase(), $dir))->run();

    assertSame(2, count($results));
    assertSame('executed', $results[0]['status']);
    assertSame('executed', $results[1]['status']);
});

it('run nao repete uma migration ja executada numa chamada anterior', function (): void {
    $dir = migrationsFixture(['001_a.sql' => 'CREATE TABLE a (id INT);']);
    $database = testDatabase();

    (new Migrator($database, $dir))->run();
    $results = (new Migrator($database, $dir))->run();

    assertSame([], $results, 'nada pendente na segunda chamada, run() nao deveria reprocessar nada');
});

it('run para na primeira migration com erro de SQL, sem lancar excecao', function (): void {
    $dir = migrationsFixture([
        '001_a.sql' => 'CREATE TABLE a (id INT);',
        '002_ruim.sql' => 'CREATE TABLE (((;',
        '003_c.sql' => 'CREATE TABLE c (id INT);',
    ]);

    $migrator = new Migrator(testDatabase(), $dir);
    $results = $migrator->run();

    assertSame(2, count($results), 'deveria parar depois da migration com erro, sem tentar a 003');
    assertSame('executed', $results[0]['status']);
    assertSame('error', $results[1]['status']);
    assertTrue(is_string($results[1]['error']) && $results[1]['error'] !== '');
    assertSame(['002_ruim.sql', '003_c.sql'], $migrator->pending(), '002 e 003 continuam pendentes apos o erro');
});
