<?php

namespace App\Core\Middleware;

use App\Core\Auth;
use App\Core\Router;

/**
 * RequireAuth para endpoints chamados por fetch(): sem login responde 401 JSON
 * em vez de redirecionar (o fetch seguiria o redirect e quebraria o
 * response.json()). Vem antes de VerifyCsrfToken: sessao expirada tambem nao
 * tem token, e o JS precisa do 401 para mandar a pessoa fazer login de novo.
 */
class RequireAuthJson implements MiddlewareInterface
{
    public function handle(array $routeParams): ?string
    {
        if (Auth::check()) {
            return null;
        }

        http_response_code(401);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode(['error' => 'session_expired']);

        return Router::HALT;
    }
}
