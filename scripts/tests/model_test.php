<?php

declare(strict_types=1);

use App\Core\Model;
use App\Database\Database;

echo "\nModel\n";

class Widget extends Model
{
    protected $table = 'widgets';
    protected $fillable = ['name', 'is_active', 'tags', 'price', 'scheduled_at'];
    protected $casts = [
        'is_active' => 'bool',
        'tags' => 'array',
        'price' => 'int',
        'scheduled_at' => 'datetime',
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

it('cast datetime trata string vazia como null, nao como "agora"', function (): void {
    widgetFixture();
    $widget = new Widget(['name' => 'Caixa', 'scheduled_at' => '']);

    assertSame(null, $widget->scheduled_at);
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

it('acesso de propriedade a um nome que colide com um metodo nao invoca o metodo', function (): void {
    $database = widgetFixture();
    $widget = Widget::create(['name' => 'Caixa', 'price' => 5]);

    $widget->delete;

    $count = $database->fetch('SELECT COUNT(*) AS total FROM widgets')['total'];
    assertSame(1, (int) $count, 'acessar $widget->delete como propriedade (sem parenteses) nao deveria apagar a linha');
});

class WidgetWithoutUuid extends Model
{
    protected $table = 'widgets';
}

function seedWidgets(Database $database): void
{
    Widget::create(['name' => 'A', 'is_active' => true, 'price' => 10]);
    Widget::create(['name' => 'B', 'is_active' => false, 'price' => 20]);
    Widget::create(['name' => 'C', 'is_active' => true, 'price' => 30]);
}

it('find() localiza pelo id e retorna null quando nao existe', function (): void {
    $database = widgetFixture();
    seedWidgets($database);
    $first = Widget::where('name', 'A')->first();

    assertSame('A', Widget::find($first->id)->name);
    assertSame(null, Widget::find(999999));
});

it('all() retorna todas as linhas', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    assertSame(3, count(Widget::all()));
});

it('where() com operador de comparacao e orderBy funcionam juntos', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    $results = Widget::where('price', '>', 10)->orderBy('price', 'desc')->get();

    assertSame(2, count($results));
    assertSame('C', $results[0]->name);
    assertSame('B', $results[1]->name);
});

it('duas clausulas where() na mesma coluna nao colidem (gotcha do PDO nativo)', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    $results = Widget::where('price', '>', 10)->where('price', '<', 30)->get();

    assertSame(1, count($results));
    assertSame('B', $results[0]->name);
});

it('where() recusa operador fora da allowlist', function (): void {
    widgetFixture();

    assertThrows(function (): void {
        Widget::where('price', 'DROP TABLE widgets; --', 1)->get();
    });
});

it('where() recusa um operador SQL valido mas fora da allowlist, isolado da execucao da query', function (): void {
    widgetFixture();

    // '<>' e um operador de comparacao SQL perfeitamente valido (nao esta em
    // QueryBuilder::$operators, que usa '!=' pra isso). Se a checagem da
    // allowlist fosse removida, esta chamada executaria com sucesso e NENHUMA
    // excecao seria lancada — diferente do teste acima, que so falha porque o
    // texto malicioso produz SQL invalido e o PDO reclama por outro motivo.
    // Isso prova que e especificamente a allowlist que bloqueia, e nao um
    // efeito colateral de SQL malformado. where() lanca a excecao de forma
    // sincrona, antes de qualquer SQL ser montado, entao nem precisa de get().
    assertThrows(function (): void {
        Widget::where('price', '<>', 1);
    });
});

it('whereIn() filtra pelos ids informados', function (): void {
    $database = widgetFixture();
    seedWidgets($database);
    $ids = array_map(function (Widget $widget): int {
        return $widget->id;
    }, Widget::where('is_active', 1)->get());

    assertSame(2, count(Widget::query()->whereIn('id', $ids)->get()));
});

it('whereIn() com array vazio nao quebra e retorna vazio', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    assertSame([], Widget::query()->whereIn('id', [])->get());
});

it('limit()/offset() paginam os resultados', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    $page = Widget::query()->orderBy('price', 'asc')->limit(1)->offset(1)->get();

    assertSame(1, count($page));
    assertSame('B', $page[0]->name);
});

it('count() conta respeitando o where', function (): void {
    $database = widgetFixture();
    seedWidgets($database);

    assertSame(2, Widget::where('is_active', 1)->count());
});

it('findByUuid() localiza pelo uuid gerado automaticamente', function (): void {
    widgetFixture();
    $widget = Widget::create(['name' => 'A', 'price' => 10]);

    $found = Widget::findByUuid($widget->uuid);

    assertSame($widget->id, $found->id);
});

it('findByUuid() lanca excecao se a subclasse nao declarou $uuidColumn', function (): void {
    widgetFixture();

    assertThrows(function (): void {
        WidgetWithoutUuid::findByUuid('qualquer-coisa');
    });
});

echo "\nModel relations\n";

class Author extends Model
{
    protected $table = 'authors';
    protected $fillable = ['name'];

    public function books()
    {
        return $this->hasMany(Book::class, 'author_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'author_tag', 'author_id', 'tag_id');
    }
}

class Book extends Model
{
    protected $table = 'books';
    protected $fillable = ['title', 'author_id'];

    public function author()
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function cover()
    {
        return $this->hasOne(Cover::class, 'book_id');
    }
}

class Cover extends Model
{
    protected $table = 'covers';
    protected $fillable = ['book_id', 'url'];
}

class Tag extends Model
{
    protected $table = 'tags';
    protected $fillable = ['name'];
}

function authorBookFixture(): Database
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE authors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE books (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            author_id INT UNSIGNED NOT NULL,
            title VARCHAR(120) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE covers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            book_id INT UNSIGNED NOT NULL,
            url VARCHAR(255) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE tags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE author_tag (
            author_id INT UNSIGNED NOT NULL,
            tag_id INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    Model::setConnection($database);

    return $database;
}

it('hasMany: $author->books lista os livros do autor', function (): void {
    authorBookFixture();
    $author = Author::create(['name' => 'Machado']);
    Book::create(['title' => 'Dom Casmurro', 'author_id' => $author->id]);
    Book::create(['title' => 'Memorias Postumas', 'author_id' => $author->id]);
    Book::create(['title' => 'Outro autor', 'author_id' => 999]);

    $books = $author->books;

    assertSame(2, count($books));
});

it('belongsTo: $book->author retorna o autor dono do livro', function (): void {
    authorBookFixture();
    $author = Author::create(['name' => 'Machado']);
    $book = Book::create(['title' => 'Dom Casmurro', 'author_id' => $author->id]);

    assertSame('Machado', $book->author->name);
});

it('hasOne: $book->cover retorna uma unica capa (ou null)', function (): void {
    authorBookFixture();
    $author = Author::create(['name' => 'Machado']);
    $book = Book::create(['title' => 'Dom Casmurro', 'author_id' => $author->id]);
    $withoutCover = Book::create(['title' => 'Sem capa', 'author_id' => $author->id]);
    Cover::create(['book_id' => $book->id, 'url' => 'capa.png']);

    assertSame('capa.png', $book->cover->url);
    assertSame(null, $withoutCover->cover);
});

it('relacao via metodo permite encadear where() antes de buscar', function (): void {
    authorBookFixture();
    $author = Author::create(['name' => 'Machado']);
    Book::create(['title' => 'Dom Casmurro', 'author_id' => $author->id]);
    Book::create(['title' => 'Memorias Postumas', 'author_id' => $author->id]);

    $filtered = $author->books()->where('title', 'Dom Casmurro')->get();

    assertSame(1, count($filtered));
});

it('belongsToMany: attach/get/detach/sync gerenciam a tabela pivot', function (): void {
    authorBookFixture();
    $author = Author::create(['name' => 'Machado']);
    $ficcao = Tag::create(['name' => 'Ficcao']);
    $classico = Tag::create(['name' => 'Classico']);

    $author->tags()->attach($ficcao->id);
    $author->tags()->attach($ficcao->id);
    $author->tags()->attach($classico->id);

    $names = array_map(function (Tag $tag): string {
        return $tag->name;
    }, $author->tags()->get());
    sort($names);
    assertSame(['Classico', 'Ficcao'], $names);

    $author->tags()->detach($classico->id);
    assertSame(1, count($author->tags()->get()));

    $author->tags()->sync([$ficcao->id, $classico->id]);
    assertSame(2, count($author->tags()->get()));
});

echo "\nApp\\Models\\User\n";

use App\Models\User;

function realUsersFixture(): Database
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
    Model::setConnection($database);

    return $database;
}

it('App\\Models\\User cria, busca, atualiza e apaga sobre a tabela users real', function (): void {
    realUsersFixture();

    $user = User::create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'hash']);
    assertTrue($user->id > 0);

    $found = User::where('email', 'ana@exemplo.com')->first();
    assertSame('Ana', $found->name);

    $found->name = 'Ana Maria';
    $found->save();
    assertSame('Ana Maria', User::find($found->id)->name);

    $found->delete();
    assertSame(null, User::find($found->id));
});

it('App\\Models\\User respeita $fillable (password nao aceita chave fora da allowlist)', function (): void {
    realUsersFixture();

    $user = User::create([
        'name' => 'Ana',
        'email' => 'ana@exemplo.com',
        'password' => 'hash',
        'id' => 999,
    ]);

    assertTrue($user->id !== 999, 'id nao esta em $fillable, o INSERT deveria gerar o proprio id');
});
