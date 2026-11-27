<?php

declare(strict_types=1);

use App\Core\ErrorHandler;
use App\Core\ErrorPage;
use App\Core\Logger;

echo "\nErrorHandler / ErrorPage\n";

it('wantsJson detecta Content-Type json ja enviado', function (): void {
    assertTrue(ErrorPage::wantsJson([], ['Content-Type: application/json; charset=utf-8']));
    assertTrue(ErrorPage::wantsJson([], ['content-type: APPLICATION/JSON']));
    assertFalse(ErrorPage::wantsJson([], ['Content-Type: text/html']));
});

it('wantsJson detecta Accept e X-Requested-With', function (): void {
    assertTrue(ErrorPage::wantsJson(['HTTP_ACCEPT' => 'application/json, text/plain'], []));
    assertTrue(ErrorPage::wantsJson(['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], []));
    assertFalse(ErrorPage::wantsJson(['HTTP_ACCEPT' => 'text/html'], []));
});

it('handleError loga warning e devolve false', function (): void {
    $dir = tempDir();
    Logger::reset();
    Logger::configure($dir, 'testing', 'debug');

    assertFalse(ErrorHandler::handleError(E_WARNING, 'cuidado', __FILE__, 10));
    assertTrue(strpos((string) file_get_contents($dir . '/app-' . gmdate('Y-m-d') . '.log'), 'WARNING: cuidado') !== false);
});

it('handleError ignora erro suprimido com @', function (): void {
    $dir = tempDir();
    Logger::reset();
    Logger::configure($dir, 'testing', 'debug');

    $previous = error_reporting(0);
    $result = ErrorHandler::handleError(E_WARNING, 'silenciado', __FILE__, 10);
    error_reporting($previous);

    assertFalse($result);
    assertFalse(is_file($dir . '/app-' . gmdate('Y-m-d') . '.log'));
});

it('render com headers ja enviados nao lanca', function (): void {
    ob_start();
    echo '';
    ErrorPage::render(500, 'Erro interno');
    $out = ob_get_clean();

    assertTrue($out !== '');
});

it('na web os erros so aparecem na tela com APP_DEBUG; senao vao so para o log', function (): void {
    $before = ini_get('display_errors');

    try {
        ErrorHandler::showErrorsOnScreen(false);
        assertSame('0', ini_get('display_errors'));
        ErrorHandler::showErrorsOnScreen(true);
        assertSame('1', ini_get('display_errors'));
    } finally {
        ini_set('display_errors', (string) $before);
    }
});

it('no CLI uma excecao vai para o stderr com codigo de saida 1, sem HTML', function (): void {
    // scripts/migrate.php registra o ErrorHandler: com o banco fora do ar ele
    // nao pode imprimir a pagina 500 e sair com 0 (deploy acharia que deu certo).
    $root = dirname(__DIR__, 2);
    $script = tempDir() . '/boom.php';
    file_put_contents($script, '<?php require ' . var_export($root . '/app/Core/Autoloader.php', true) . ';'
        . '(new App\Core\Autoloader(' . var_export($root . '/app', true) . '))->register();'
        . 'App\Core\ErrorHandler::register(sys_get_temp_dir());'
        . 'throw new RuntimeException("banco fora do ar");');

    $process = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    assertSame(1, $exitCode);
    assertContains('RuntimeException: banco fora do ar', $stderr);
    assertFalse(stripos($stdout, '<html') !== false || stripos($stdout, '<!doctype') !== false, 'sem pagina HTML no stdout');
});
