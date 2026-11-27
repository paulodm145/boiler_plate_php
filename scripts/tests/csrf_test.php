<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Middleware\VerifyCsrfToken;
use App\Core\Router;

echo "\nCSRF\n";

function csrfReset(): void
{
    $_POST = [];
    unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SESSION['csrf_token']);
}

function csrfHandle(): ?string
{
    ob_start();
    $result = (new VerifyCsrfToken())->handle([]);
    ob_end_clean();

    return $result;
}

it('token e estavel na sessao e tem 64 hex', function (): void {
    csrfReset();
    $token = Csrf::token();

    assertSame($token, Csrf::token());
    assertSame(1, preg_match('/^[0-9a-f]{64}$/', $token));
});

it('sem token o middleware para a requisicao', function (): void {
    csrfReset();
    Csrf::token();

    assertSame(Router::HALT, csrfHandle());
});

it('token errado para a requisicao', function (): void {
    csrfReset();
    Csrf::token();
    $_POST['_csrf'] = 'x';

    assertSame(Router::HALT, csrfHandle());
});

it('token certo no POST segue', function (): void {
    csrfReset();
    $_POST['_csrf'] = Csrf::token();

    assertSame(null, csrfHandle());
});

it('token certo no cabecalho X-CSRF-Token segue', function (): void {
    csrfReset();
    $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token();

    assertSame(null, csrfHandle());
});

it('field() usa o nome _csrf com o token da sessao', function (): void {
    csrfReset();

    assertContains('name="_csrf"', Csrf::field());
    assertContains('value="' . Csrf::token() . '"', Csrf::field());
});

csrfReset();
