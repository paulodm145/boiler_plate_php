<?php

namespace App\Core\Middleware;

use App\Core\Csrf;
use App\Core\ErrorPage;
use App\Core\Router;

/**
 * Registrada explicitamente em cada rota POST (nao globalmente), pra ficar
 * visivel em public/index.php quais rotas escrevem estado. Aceita o token
 * tanto em $_POST['_csrf'] (forms normais, via Csrf::field()) quanto no
 * header X-CSRF-Token (fetch dos scripts em public/assets/js/, que mandam
 * body application/x-www-form-urlencoded proprio ou nenhum body).
 */
class VerifyCsrfToken implements MiddlewareInterface
{
    public function handle(array $routeParams): ?string
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            ErrorPage::forbidden();

            return Router::HALT;
        }

        return null;
    }
}
