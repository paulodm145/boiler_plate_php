<?php

namespace App\Helpers;

/**
 * Arrays, no estilo do Arr do Laravel. Chaves com ponto ("user.name")
 * descem nos arrays aninhados.
 */
final class Arr
{
    private function __construct()
    {
    }

    /** Valor em $key (com notacao de ponto) ou $default. */
    public static function get(array $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) {
            return $array;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /** Grava $value em $key (com notacao de ponto), criando os niveis que faltam. */
    public static function set(array &$array, string $key, mixed $value): array
    {
        $segments = explode('.', $key);
        $current = &$array;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current = $value;

        return $array;
    }

    /** true se todas as $keys existem (com notacao de ponto), mesmo valendo null. */
    public static function has(array $array, string|array $keys): bool
    {
        $keys = (array) $keys;

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $marker = new \stdClass();

            if (self::get($array, $key, $marker) === $marker) {
                return false;
            }
        }

        return true;
    }

    /** Remove $keys (com notacao de ponto). */
    public static function forget(array &$array, string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            $segments = explode('.', $key);
            $last = array_pop($segments);
            $current = &$array;

            foreach ($segments as $segment) {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    continue 2;
                }

                $current = &$current[$segment];
            }

            unset($current[$last]);
            unset($current);
        }
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /** Primeiro item (ou o primeiro que passa em $callback). */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /** Ultimo item (ou o ultimo que passa em $callback). */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        return self::first(array_reverse($array, true), $callback, $default);
    }

    /** Valores de $value em cada linha; com $key, indexados por ela. */
    public static function pluck(array $rows, string $value, ?string $key = null): array
    {
        $result = [];

        foreach ($rows as $row) {
            $item = self::get((array) $row, $value);

            if ($key === null) {
                $result[] = $item;
                continue;
            }

            $result[self::get((array) $row, $key)] = $item;
        }

        return $result;
    }

    /** Linhas indexadas por $key (a ultima vence em caso de repeticao). */
    public static function keyBy(array $rows, string $key): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[self::get((array) $row, $key)] = $row;
        }

        return $result;
    }

    /** Linhas agrupadas por $key, mantendo a ordem. */
    public static function groupBy(array $rows, string $key): array
    {
        $result = [];

        foreach ($rows as $row) {
            $result[self::get((array) $row, $key)][] = $row;
        }

        return $result;
    }

    /** Itens que passam em $callback($value, $key), mantendo as chaves. */
    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function whereNotNull(array $array): array
    {
        return array_filter($array, static fn (mixed $value): bool => $value !== null);
    }

    /** null vira [], um valor solto vira [valor], array fica como esta. */
    public static function wrap(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /** Achata ate $depth niveis (INF = todos), descartando as chaves. */
    public static function flatten(array $array, float $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth <= 1) {
                array_push($result, ...array_values($item));
            } else {
                array_push($result, ...self::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    /** Linhas ordenadas por $key (estavel), sem manter as chaves. */
    public static function sortBy(array $rows, string $key, bool $descending = false): array
    {
        $rows = array_values($rows);
        usort($rows, static function (mixed $a, mixed $b) use ($key, $descending): int {
            $order = self::get((array) $a, $key) <=> self::get((array) $b, $key);

            return $descending ? -$order : $order;
        });

        return $rows;
    }

    /** Soma de $key nas linhas (sem $key, soma os proprios valores). */
    public static function sum(array $rows, ?string $key = null): int|float
    {
        $values = $key === null ? $rows : self::pluck($rows, $key);

        return array_sum(array_map(static fn (mixed $value): int|float => is_numeric($value) ? $value + 0 : 0, $values));
    }

    /** Array aninhado em uma linha so, com chaves de ponto (["a" => ["b" => 1]] => ["a.b" => 1]). */
    public static function dot(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && $value !== []) {
                $result += self::dot($value, $prefix . $key . '.');
                continue;
            }

            $result[$prefix . $key] = $value;
        }

        return $result;
    }

    /** Array com chaves que nao sao 0..n-1. */
    public static function isAssoc(array $array): bool
    {
        return !array_is_list($array);
    }
}
