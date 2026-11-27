<?php

declare(strict_types=1);

use App\Core\Model;
use App\Database\Paginator;
use App\Http\ListQuery;

echo "\nPaginacao\n";

class PageProbe extends Model
{
    protected $table = 'page_probe';
    protected $fillable = ['name', 'created_at'];
}

function paginationDb(int $rows = 23): \App\Database\Database
{
    $db = testDatabase();
    $db->execute('DROP TABLE IF EXISTS page_probe');
    $db->execute('CREATE TABLE page_probe (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(40), created_at DATETIME)');

    for ($i = 1; $i <= $rows; $i++) {
        $db->execute('INSERT INTO page_probe (name, created_at) VALUES (:name, :created)', [
            'name' => sprintf('item-%02d', $i),
            'created' => gmdate('Y-m-d H:i:s', 1700000000 + $i),
        ]);
    }

    return $db;
}

function listQuery(array $query): ListQuery
{
    return ListQuery::fromRequest(['name' => 'p.name', 'created' => 'p.created_at'], 'name', 'asc', 25, $query);
}

it('fromRequest saneia pagina, tamanho, ordenacao, direcao e busca', function (): void {
    assertSame(1, listQuery(['page' => '-3'])->page());
    assertSame(1, listQuery(['page' => 'abc'])->page());
    assertSame(25, listQuery(['per_page' => '7'])->perPage());
    assertSame(50, listQuery(['per_page' => '50'])->perPage());
    assertSame('name', listQuery(['sort' => 'drop'])->sortKey());
    assertSame('asc', listQuery(['dir' => 'up'])->direction());
    assertSame(100, mb_strlen(listQuery(['q' => str_repeat('a', 150)])->search()));
    assertSame('', listQuery(['q' => ['x']])->search());
});

it('orderBy usa a expressao do mapa e o desempate', function (): void {
    $list = listQuery(['sort' => 'name', 'dir' => 'desc']);

    assertSame('p.name DESC', $list->orderBy());
    assertSame('p.name DESC, p.id ASC', $list->orderBy('p.id ASC'));
});

it('url mantem os parametros e remove partial e os nulos', function (): void {
    $list = listQuery(['q' => 'ana', 'partial' => '1', 'page' => '2']);

    assertSame('/admin/users?q=ana&page=3', $list->url(['page' => 3], '/admin/users'));
    assertSame('/admin/users?q=ana', $list->url(['page' => null], '/admin/users'));
});

it('Paginator::sql pagina com total, paginas e faixa', function (): void {
    $db = paginationDb();
    $page = Paginator::sql($db, 'SELECT p.* FROM page_probe p', [], listQuery(['per_page' => '10', 'page' => '2']), 'p.id ASC');

    assertSame(10, count($page->items));
    assertSame('item-11', $page->items[0]['name']);
    assertSame(23, $page->total);
    assertSame(3, $page->pages);
    assertSame(11, $page->from);
    assertSame(20, $page->to);
});

it('pagina alem da ultima mostra a ultima', function (): void {
    $db = paginationDb();
    $page = Paginator::sql($db, 'SELECT p.* FROM page_probe p', [], listQuery(['per_page' => '10', 'page' => '999']));

    assertSame(3, $page->page);
    assertSame(3, count($page->items));
    assertSame(21, $page->from);
    assertSame(23, $page->to);
});

it('lista vazia tem uma pagina e faixa zero', function (): void {
    $db = paginationDb();
    $page = Paginator::sql($db, 'SELECT p.* FROM page_probe p WHERE 1 = 0', [], listQuery([]));

    assertSame([], $page->items);
    assertSame(0, $page->total);
    assertSame(1, $page->pages);
    assertSame(0, $page->from);
    assertSame(0, $page->to);
});

it('Paginator::sql aceita parametros de busca', function (): void {
    $db = paginationDb();
    $page = Paginator::sql($db, 'SELECT p.* FROM page_probe p WHERE p.name LIKE :q', ['q' => 'item-1%'], listQuery([]));

    assertSame(10, $page->total);
});

it('window mostra vizinhos e reticencias', function (): void {
    $page = new \App\Database\Page([], 100, 5, 10);

    assertSame([1, null, 3, 4, 5, 6, 7, null, 10], $page->window());
});

it('meta tem as chaves da API', function (): void {
    $page = new \App\Database\Page([], 23, 2, 10);

    assertSame(['total' => 23, 'page' => 2, 'per_page' => 10, 'pages' => 3, 'from' => 11, 'to' => 20], $page->meta());
});

it('QueryBuilder::paginate devolve models e o mesmo total', function (): void {
    $db = paginationDb();
    Model::setConnection($db);
    $list = ListQuery::fromRequest(['name' => 'name'], 'name', 'desc', 10, []);
    $page = PageProbe::query()->where('name', 'LIKE', 'item-%')->paginate($list);

    assertSame(23, $page->total);
    assertSame(10, count($page->items));
    assertTrue($page->items[0] instanceof PageProbe);
    assertSame('item-23', $page->items[0]->name);
});
