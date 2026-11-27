<?php

namespace App\Validation;

/** Mensagens de validacao por campo: ['email' => ['Informe um e-mail valido.', ...]]. */
final class ErrorBag
{
    /** @param array<string, string[]> $messages */
    public function __construct(private readonly array $messages = [])
    {
    }

    public function has(string $field): bool
    {
        return ($this->messages[$field] ?? []) !== [];
    }

    public function first(string $field): ?string
    {
        return $this->messages[$field][0] ?? null;
    }

    /** @return string[] */
    public function get(string $field): array
    {
        return $this->messages[$field] ?? [];
    }

    /** @return array<string, string[]> */
    public function all(): array
    {
        return $this->messages;
    }

    /** Todas as mensagens numa lista so, na ordem dos campos. */
    public function flatten(): array
    {
        return array_merge([], ...array_values($this->messages));
    }

    public function any(): bool
    {
        return $this->messages !== [];
    }

    public function count(): int
    {
        return count($this->flatten());
    }
}
