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
