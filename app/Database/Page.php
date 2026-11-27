<?php

namespace App\Database;

/** Uma pagina de resultados e sua posicao no total (Paginator::sql, QueryBuilder::paginate). */
final class Page
{
    public readonly int $pages;
    public readonly int $from;
    public readonly int $to;

    /** @param array $items linhas (arrays) ou models da pagina atual */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage
    ) {
        $this->pages = self::pagesFor($total, $perPage);
        $this->from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
        $this->to = $total === 0 ? 0 : min($total, $page * $perPage);
    }

    /** Numero de paginas (no minimo 1, mesmo sem registros). */
    public static function pagesFor(int $total, int $perPage): int
    {
        return max(1, (int) ceil($total / max(1, $perPage)));
    }

    /** Numeros de pagina para os links, com null no lugar de "...": [1, null, 4, 5, 6, null, 10]. */
    public function window(int $around = 2): array
    {
        $numbers = [];

        for ($number = 1; $number <= $this->pages; $number++) {
            if ($number === 1 || $number === $this->pages || abs($number - $this->page) <= $around) {
                $numbers[] = $number;
            } elseif (end($numbers) !== null) {
                $numbers[] = null;
            }
        }

        return $numbers;
    }

    /** Meta da pagina para respostas JSON. */
    public function meta(): array
    {
        return [
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'pages' => $this->pages,
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
