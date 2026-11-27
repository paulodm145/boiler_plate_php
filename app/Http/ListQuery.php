<?php

namespace App\Http;

use App\Core\Icon;

/**
 * Parametros de uma listagem, lidos e saneados da query string:
 * page (>= 1), per_page (PER_PAGE_OPTIONS), sort (so chaves de $sortable),
 * dir (asc|desc), q (busca, ate 100 caracteres) e partial=1 (so a tabela, para o
 * live-grid.js). Valor invalido cai no padrao, sem erro.
 *
 *     $list = ListQuery::fromRequest(['name' => 'u.name', 'created' => 'u.created_at'], 'name');
 */
final class ListQuery
{
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];
    private const SEARCH_MAX = 100;

    /** @param array<string, string> $sortable chave publica => expressao SQL */
    private function __construct(
        private readonly array $sortable,
        private readonly int $page,
        private readonly int $perPage,
        private readonly string $sortKey,
        private readonly string $direction,
        private readonly string $search,
        private readonly bool $partial,
        private readonly array $query
    ) {
    }

    public static function fromRequest(
        array $sortable,
        string $defaultSort,
        string $defaultDir = 'asc',
        int $defaultPerPage = 25,
        ?array $query = null
    ): self {
        $query ??= $_GET;
        $page = filter_var($query['page'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = (int) filter_var($query['per_page'] ?? null, FILTER_VALIDATE_INT);
        $sort = is_string($query['sort'] ?? null) ? $query['sort'] : '';
        $dir = is_string($query['dir'] ?? null) ? strtolower($query['dir']) : '';
        $search = is_string($query['q'] ?? null) ? mb_substr(trim($query['q']), 0, self::SEARCH_MAX) : '';
        $sortKey = isset($sortable[$sort]) ? $sort : $defaultSort;

        return new self(
            $sortable,
            $page === false ? 1 : $page,
            in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : $defaultPerPage,
            $sortKey,
            in_array($dir, ['asc', 'desc'], true) ? $dir : ($sortKey === $defaultSort ? $defaultDir : 'asc'),
            $search,
            ($query['partial'] ?? '') === '1',
            $query
        );
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function search(): string
    {
        return $this->search;
    }

    public function sortKey(): string
    {
        return $this->sortKey;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /** Pedido so do pedaco da tabela (live-grid.js). */
    public function isPartial(): bool
    {
        return $this->partial;
    }

    /** "u.name DESC" (+ ", desempate"), so com expressoes do mapa $sortable. */
    public function orderBy(string $tieBreaker = ''): string
    {
        $sql = ($this->sortable[$this->sortKey] ?? $this->sortKey) . ' ' . strtoupper($this->direction);

        return $tieBreaker === '' ? $sql : $sql . ', ' . $tieBreaker;
    }

    /** URL da listagem com $changes aplicados (null remove o parametro); nunca leva partial. */
    public function url(array $changes = [], ?string $path = null): string
    {
        $query = array_merge($this->query, $changes);
        unset($query['partial']);
        $query = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');
        $path ??= parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        return $query === [] ? $path : $path . '?' . http_build_query($query);
    }

    /** Cabecalho <th> clicavel: alterna asc/desc na coluna ativa e volta para a pagina 1. */
    public function sortLink(string $label, string $key, string $class = ''): string
    {
        $active = $this->sortKey === $key;
        $next = $active && $this->direction === 'asc' ? 'desc' : 'asc';
        $icon = $active ? ($this->direction === 'asc' ? 'sort-up' : 'sort-down') : 'sort';
        $ariaSort = $active ? ($this->direction === 'asc' ? 'ascending' : 'descending') : 'none';

        return '<th class="grid-sortable ' . e($class) . '" aria-sort="' . $ariaSort . '">'
            . '<a href="' . e($this->url(['sort' => $key, 'dir' => $next, 'page' => null])) . '" data-grid-link>'
            . e($label) . ' <span class="grid-sort-icon' . ($active ? ' active' : '') . '">' . Icon::svg($icon) . '</span></a></th>';
    }
}
