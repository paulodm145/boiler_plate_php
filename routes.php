<?php

declare(strict_types=1);

use App\Controllers\AdminAuthController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminSettingController;
use App\Controllers\AdminUpdateController;
use App\Controllers\AdminUserController;
use App\Controllers\ErrorController;
use App\Controllers\HomeController;
use App\Core\Middleware\RequireAuth;
use App\Core\Middleware\VerifyCsrfToken;
use App\Core\Router;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    // Alvo do ErrorDocument 403 do .htaccess — ver App\Controllers\ErrorController.
    $router->get('/erro-acesso-negado', [ErrorController::class, 'forbidden']);

    $router->get('/admin/login', [AdminAuthController::class, 'loginForm']);
    $router->post('/admin/login', [AdminAuthController::class, 'login'], [VerifyCsrfToken::class]);
    $router->post('/admin/logout', [AdminAuthController::class, 'logout'], [VerifyCsrfToken::class]);
    $router->get('/admin', [AdminDashboardController::class, 'index'], [RequireAuth::class]);
    $router->get('/admin/users', [AdminUserController::class, 'index'], [RequireAuth::class]);
    $router->get('/admin/users/create', [AdminUserController::class, 'create'], [RequireAuth::class]);
    $router->post('/admin/users', [AdminUserController::class, 'store'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/users/{id}/edit', [AdminUserController::class, 'edit'], [RequireAuth::class]);
    $router->post('/admin/users/{id}/edit', [AdminUserController::class, 'update'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->post('/admin/users/{id}/delete', [AdminUserController::class, 'delete'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/settings', [AdminSettingController::class, 'edit'], [RequireAuth::class]);
    $router->post('/admin/settings', [AdminSettingController::class, 'update'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/atualizar', [AdminUpdateController::class, 'show'], [RequireAuth::class]);
    $router->post('/admin/atualizar/run', [AdminUpdateController::class, 'run'], [VerifyCsrfToken::class, RequireAuth::class]);
};
