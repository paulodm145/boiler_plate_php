<?php

declare(strict_types=1);

use App\Controllers\AdminAuthController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminPasswordResetController;
use App\Controllers\AdminSettingController;
use App\Controllers\AdminUpdateController;
use App\Controllers\AdminUserController;
use App\Controllers\ErrorController;
use App\Controllers\HomeController;
use App\Core\Middleware\RequireAuth;
use App\Core\Middleware\RequireAuthJson;
use App\Core\Middleware\VerifyCsrfToken;
use App\Core\Router;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);
    // Alvo do ErrorDocument 403 do .htaccess — ver App\Controllers\ErrorController.
    $router->get('/erro-acesso-negado', [ErrorController::class, 'forbidden']);

    $router->get('/admin/login', [AdminAuthController::class, 'loginForm']);
    $router->post('/admin/login', [AdminAuthController::class, 'login'], [VerifyCsrfToken::class]);
    $router->post('/admin/logout', [AdminAuthController::class, 'logout'], [VerifyCsrfToken::class]);
    $router->get('/admin/esqueci-senha', [AdminPasswordResetController::class, 'forgotForm']);
    $router->post('/admin/esqueci-senha', [AdminPasswordResetController::class, 'forgotSubmit'], [VerifyCsrfToken::class]);
    $router->get('/admin/redefinir-senha/{token}', [AdminPasswordResetController::class, 'resetForm']);
    $router->post('/admin/redefinir-senha/{token}', [AdminPasswordResetController::class, 'resetSubmit'], [VerifyCsrfToken::class]);
    $router->get('/admin', [AdminDashboardController::class, 'index'], [RequireAuth::class]);
    $router->get('/admin/users', [AdminUserController::class, 'index'], [RequireAuth::class]);
    $router->get('/admin/users/create', [AdminUserController::class, 'create'], [RequireAuth::class]);
    $router->post('/admin/users', [AdminUserController::class, 'store'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/users/{userUuid}/edit', [AdminUserController::class, 'edit'], [RequireAuth::class]);
    $router->post('/admin/users/{userUuid}/edit', [AdminUserController::class, 'update'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->post('/admin/users/{userUuid}/delete', [AdminUserController::class, 'delete'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/settings', [AdminSettingController::class, 'edit'], [RequireAuth::class]);
    $router->post('/admin/settings', [AdminSettingController::class, 'update'], [VerifyCsrfToken::class, RequireAuth::class]);
    $router->get('/admin/atualizar', [AdminUpdateController::class, 'show'], [RequireAuth::class]);
    // Chamada so via fetch() pelo polling de public/assets/js/migrate-progress.js:
    // RequireAuthJson devolve 401 JSON ("sessao expirou, faca login") em vez do
    // redirect HTML do RequireAuth, e vem antes do CSRF porque sessao expirada
    // tambem nao tem token.
    $router->post('/admin/atualizar/run', [AdminUpdateController::class, 'run'], [RequireAuthJson::class, VerifyCsrfToken::class]);
};
