<?php

declare(strict_types=1);

/*
 * Funcoes globais do app, carregadas logo apos o autoloader (public/index.php,
 * scripts/migrate.php e scripts/test_install.php). Mantenha isto pequeno:
 * utilitario novo vai para App\Helpers.
 */

if (!function_exists('e')) {
    /** Escape de HTML para views: e($valor). null vira ''. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('flash_validation_state')) {
    /**
     * Uma vez por requisicao (public/index.php, logo apos Auth::start()): os erros e a
     * entrada gravados pelo ValidationResponder passam a valer nesta requisicao e
     * somem da sessao, entao aparecem uma vez so.
     */
    function flash_validation_state(): void
    {
        $_SESSION['_errors_current'] = $_SESSION['_errors'] ?? [];
        $_SESSION['_old_current'] = $_SESSION['_old'] ?? [];
        unset($_SESSION['_errors'], $_SESSION['_old']);
    }
}

if (!function_exists('old')) {
    /** Valor digitado no envio anterior que falhou na validacao: old('email', $user['email']). */
    function old(string $field, mixed $default = ''): mixed
    {
        return $_SESSION['_old_current'][$field] ?? $default;
    }
}

if (!function_exists('errors')) {
    /** Erros de validacao do envio anterior: errors()->first('email'). */
    function errors(): \App\Validation\ErrorBag
    {
        return new \App\Validation\ErrorBag($_SESSION['_errors_current'] ?? []);
    }
}
