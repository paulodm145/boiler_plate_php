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
