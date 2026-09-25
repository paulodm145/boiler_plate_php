<?php

declare(strict_types=1);

use App\Core\Middleware\MiddlewareInterface;
use App\Core\Router;

echo "\nRouter (middleware)\n";

class TestPassMiddleware implements MiddlewareInterface
{
    public static $calls = 0;

    public function handle(array $routeParams): ?string
    {
        self::$calls++;

        return null;
    }
}

class TestDenyMiddleware implements MiddlewareInterface
{
    public function handle(array $routeParams): ?string
    {
        return '/admin/login';
    }
}

class TestHaltMiddleware implements MiddlewareInterface
{
    public function handle(array $routeParams): ?string
    {
        return Router::HALT;
    }
}

class TestDummyController
{
    public static $lastParams = null;

    public function show(...$params): void
    {
        self::$lastParams = $params;
    }
}

it('rota sem middleware resolve para dispatch, como antes', function (): void {
    $router = new Router();
    $router->get('/sem-middleware/{id}', [TestDummyController::class, 'show']);

    $result = $router->resolve('GET', '/sem-middleware/42');

    assertSame('dispatch', $result['action']);
    assertSame(['42'], $result['params']);
});

it('middleware que devolve null deixa a cadeia seguir até o dispatch', function (): void {
    TestPassMiddleware::$calls = 0;
    $router = new Router();
    $router->get('/com-pass', [TestDummyController::class, 'show'], [TestPassMiddleware::class]);

    $result = $router->resolve('GET', '/com-pass');

    assertSame(1, TestPassMiddleware::$calls);
    assertSame('dispatch', $result['action']);
});

it('middleware que devolve uma url gera action=redirect com essa location', function (): void {
    $router = new Router();
    $router->get('/protegida', [TestDummyController::class, 'show'], [TestDenyMiddleware::class]);

    $result = $router->resolve('GET', '/protegida');

    assertSame('redirect', $result['action']);
    assertSame('/admin/login', $result['location']);
});

it('a segunda middleware nao roda se a primeira nega', function (): void {
    TestPassMiddleware::$calls = 0;
    $router = new Router();
    $router->get('/nega-primeiro', [TestDummyController::class, 'show'], [
        TestDenyMiddleware::class, TestPassMiddleware::class,
    ]);

    $router->resolve('GET', '/nega-primeiro');

    assertSame(0, TestPassMiddleware::$calls);
});

it('middleware que devolve Router::HALT nao despacha nem redireciona', function (): void {
    $router = new Router();
    $router->get('/halt', [TestDummyController::class, 'show'], [TestHaltMiddleware::class]);

    $result = $router->resolve('GET', '/halt');

    assertSame('halt', $result['action']);
    assertSame(null, $result['location']);
});

it('parametros nomeados da rota chegam a middleware por nome', function (): void {
    class TestCaptureMiddleware implements MiddlewareInterface
    {
        public static $received = null;

        public function handle(array $routeParams): ?string
        {
            self::$received = $routeParams;

            return null;
        }
    }

    $router = new Router();
    $router->get('/usuarios/{userId}', [TestDummyController::class, 'show'], [TestCaptureMiddleware::class]);
    $router->resolve('GET', '/usuarios/42');

    assertSame(['userId' => '42'], TestCaptureMiddleware::$received);
});
