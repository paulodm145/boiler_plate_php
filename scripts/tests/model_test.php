<?php

declare(strict_types=1);

use App\Core\Model;
use App\Database\Database;

echo "\nModel\n";

class Widget extends Model
{
    protected $table = 'widgets';
    protected $fillable = ['name', 'is_active', 'tags', 'price'];
    protected $casts = [
        'is_active' => 'bool',
        'tags' => 'array',
        'price' => 'int',
    ];
    protected $uuidColumn = 'uuid';
}

class TableLess extends Model
{
}

function widgetFixture(): Database
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE widgets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(36) NULL,
            name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 0,
            tags TEXT NULL,
            price INT NOT NULL DEFAULT 0,
            secret VARCHAR(120) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    Model::setConnection($database);

    return $database;
}

it('getTable lanca excecao quando a subclasse nao declara $table', function (): void {
    $model = new TableLess();

    assertThrows(function () use ($model): void {
        $model->getTable();
    });
});

it('fill so aceita colunas declaradas em $fillable', function (): void {
    widgetFixture();
    $widget = new Widget(['name' => 'Caixa', 'secret' => 'vazou']);

    assertSame('Caixa', $widget->name);
    assertSame(null, $widget->secret);
});

it('casts bool/array/int aplicam na leitura e na escrita', function (): void {
    widgetFixture();
    $widget = new Widget(['name' => 'Caixa', 'is_active' => true, 'tags' => ['a', 'b'], 'price' => '10']);

    assertSame(true, $widget->is_active);
    assertSame(['a', 'b'], $widget->tags);
    assertSame(10, $widget->price);
});

it('save() faz INSERT numa instancia nova e preenche o id', function (): void {
    widgetFixture();
    $widget = new Widget(['name' => 'Caixa', 'price' => 5]);
    $widget->save();

    assertTrue($widget->id > 0, 'id deveria ser preenchido apos o insert');
});

it('save() gera created_at/updated_at automaticamente', function (): void {
    widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'price' => 5]);

    assertTrue($widget->created_at !== null);
    assertTrue($widget->updated_at !== null);
});

it('save() gera uuid automaticamente quando $uuidColumn esta declarado', function (): void {
    widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'price' => 5]);

    assertTrue(is_string($widget->uuid) && strlen($widget->uuid) === 36);
});

it('save() numa instancia existente faz UPDATE, nao um segundo INSERT', function (): void {
    $database = widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'price' => 5]);
    $originalId = $widget->id;

    $widget->price = 99;
    $widget->save();

    $count = $database->fetch('SELECT COUNT(*) AS total FROM widgets')['total'];
    assertSame(1, (int) $count, 'save() num registro existente nao deveria criar uma segunda linha');
    assertSame($originalId, $widget->id);

    $row = $database->fetch('SELECT price FROM widgets WHERE id = :id', ['id' => $originalId]);
    assertSame(99, (int) $row['price']);
});

it('delete() remove a linha e lanca excecao se chamado de novo', function (): void {
    $database = widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'price' => 5]);

    $widget->delete();

    $count = $database->fetch('SELECT COUNT(*) AS total FROM widgets')['total'];
    assertSame(0, (int) $count);

    assertThrows(function () use ($widget): void {
        $widget->delete();
    });
});

it('toArray() devolve os atributos com os casts aplicados', function (): void {
    widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'is_active' => true, 'tags' => ['x'], 'price' => 7]);

    $array = $widget->toArray();

    assertSame('Caixa', $array['name']);
    assertSame(true, $array['is_active']);
    assertSame(['x'], $array['tags']);
    assertSame(7, $array['price']);
});
