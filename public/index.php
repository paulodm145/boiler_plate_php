<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Autoloader;
use App\Core\ErrorPage;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\InstallState;
use App\Core\InstallRoutes;
use App\Core\Logger;
use App\Core\Router;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Core/Autoloader.php';

$autoloader = new Autoloader($rootPath . '/app');
$autoloader->register();

// Cedo, antes do .env: excecao, erro PHP e fatal vao para o log e para a
// pagina 500 com request_id (nada de tela em branco nem caminho de arquivo
// vazando com display_errors ligado na hospedagem).
ErrorHandler::register($rootPath);

Env::load($rootPath . '/.env');
Logger::configureFromEnv($rootPath);
ErrorHandler::showErrorsOnScreen(Env::get('APP_DEBUG', 'false') === 'true');

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
