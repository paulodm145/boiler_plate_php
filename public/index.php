<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Autoloader;
use App\Core\ErrorPage;
use App\Core\Env;
use App\Core\InstallState;
use App\Core\InstallRoutes;
use App\Core\Router;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Core/Autoloader.php';

$autoloader = new Autoloader($rootPath . '/app');
$autoloader->register();

set_exception_handler(function (\Throwable $exception): void {
    ErrorPage::serverError();
});

// set_exception_handler nao pega Fatal Error "de verdade" (out of memory,
// erro de sintaxe num arquivo carregado depois deste ponto). Sem isso, um
// fatal vira tela em branco quando display_errors esta desligado (padrao
// recomendado em producao) — o shutdown function abaixo garante que pelo
// menos a nossa pagina de erro apareca, e mostra o detalhe se APP_DEBUG=true.
register_shutdown_function(function () use ($rootPath): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error === null || !in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    if (Env::get('APP_DEBUG', 'false') === 'true') {
        echo '<pre style="white-space:pre-wrap;padding:2rem;font-family:monospace;font-size:.85rem">';
        echo htmlspecialchars($error['message'] . "\nem " . $error['file'] . ':' . $error['line'], ENT_QUOTES, 'UTF-8');
        echo '</pre>';

        return;
    }

    ErrorPage::serverError();
});

Env::load($rootPath . '/.env');

// Precisa iniciar a sessao aqui, bem cedo — antes de qualquer output.
Auth::start();

if (!(new InstallState($rootPath))->isInstalled()) {
    InstallRoutes::handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

    return;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestPath === '/install' || strpos($requestPath, '/install/') === 0) {
    ErrorPage::forbidden();

    return;
}

$router = new Router();
(require $rootPath . '/routes.php')($router);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
