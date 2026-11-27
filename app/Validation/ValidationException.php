<?php

namespace App\Validation;

use RuntimeException;

/**
 * Entrada invalida. Lancada pelo FormRequest (ou pelo controller, com
 * withMessages(), quando um Service recusa um campo) e tratada pelo
 * Router::dispatch() via App\Http\ValidationResponder: o controller nao
 * escreve "if ($erro) { render... }".
 */
final class ValidationException extends RuntimeException
{
    public function __construct(
        private readonly ErrorBag $errorBag,
        private readonly array $oldInput = [],
        private readonly string $target = '/'
    ) {
        parent::__construct('Os dados informados são inválidos.');
    }

    /** Erro de regra de negocio num campo: withMessages(['email' => 'Este e-mail já está em uso.'], $old, '/form'). */
    public static function withMessages(array $messages, array $old = [], string $redirectTo = '/'): self
    {
        $normalized = [];

        foreach ($messages as $field => $message) {
            $normalized[$field] = array_values((array) $message);
        }

        return new self(new ErrorBag($normalized), self::withoutSecrets($old), $redirectTo);
    }

    /** Tira da entrada o que nunca deve voltar para o formulario: senhas (password*) e arquivos enviados. */
    public static function withoutSecrets(array $input): array
    {
        $kept = [];

        foreach ($input as $key => $value) {
            $isSecret = str_starts_with((string) $key, 'password');
            $isUpload = is_array($value) && isset($value['tmp_name']);

            if (!$isSecret && !$isUpload) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    public function errors(): ErrorBag
    {
        return $this->errorBag;
    }

    public function old(): array
    {
        return $this->oldInput;
    }

    public function redirectTo(): string
    {
        return $this->target;
    }
}
