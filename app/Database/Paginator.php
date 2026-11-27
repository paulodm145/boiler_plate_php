<?php

namespace App\Database;

use App\Http\ListQuery;

/**
 * Pagina uma consulta em SQL puro com os parametros de uma ListQuery:
 *
 *     Paginator::sql($db, 'SELECT u.uuid, u.name FROM users u WHERE u.name LIKE :q', ['q' => "%$q%"], $list, 'u.id ASC');
 *
 * O ORDER BY vem so do mapa de colunas permitidas da ListQuery, e LIMIT/OFFSET
 * entram como inteiros na string (com prepares nativos, LIMIT por parametro
 * nao e confiavel). Pagina alem da ultima vira a ultima.
 */
final class Paginator
{
    private function __construct()
    {
    }

    public static function sql(Database $db, string $selectSql, array $params, ListQuery $list, string $tieBreaker = ''): Page
    {
        $row = $db->fetch('SELECT COUNT(*) AS total FROM (' . $selectSql . ') AS counted', $params);
        $total = $row === null ? 0 : (int) $row['total'];
        $page = min($list->page(), Page::pagesFor($total, $list->perPage()));
        $offset = ($page - 1) * $list->perPage();

        $items = $total === 0 ? [] : $db->fetchAll(
            $selectSql . ' ORDER BY ' . $list->orderBy($tieBreaker) . ' LIMIT ' . $list->perPage() . ' OFFSET ' . $offset,
            $params
        );

        return new Page($items, $total, $page, $list->perPage());
    }
}
