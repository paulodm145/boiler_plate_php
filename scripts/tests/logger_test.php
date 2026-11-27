<?php

declare(strict_types=1);

use App\Core\Logger;

echo "\nLogger\n";

function loggerFile(string $dir): string
{
    return $dir . '/app-' . gmdate('Y-m-d') . '.log';
}

function loggerSetup(string $level = 'debug'): string
{
    $dir = tempDir();
    Logger::reset();
    Logger::configure($dir, 'testing', $level);

    return $dir;
}

it('grava em app-YYYY-MM-DD.log com data UTC e formato [iso8601] env.LEVEL: mensagem {json}', function (): void {
    $dir = loggerSetup();
    Logger::error('falhou');

    assertTrue(is_file(loggerFile($dir)));
    assertTrue(
        preg_match(
            '/^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00\] testing\.ERROR: falhou \{.*"request_id":"[0-9a-f]{8}".*\}$/m',
            (string) file_get_contents(loggerFile($dir))
        ) === 1
    );
});

it('respeita o nivel minimo', function (): void {
    $dir = loggerSetup('warning');
    Logger::info('a');
    assertFalse(is_file(loggerFile($dir)));
    Logger::warning('b');
    assertTrue(is_file(loggerFile($dir)));
    assertContains('WARNING: b', (string) file_get_contents(loggerFile($dir)));
});

it('mascara chaves sensiveis em qualquer profundidade', function (): void {
    $dir = loggerSetup();
    Logger::error('x', [
        'password' => 'abc',
        'nested' => ['api_token' => 't', 'csrf' => 'c', 'Authorization' => 'x', 'ok' => 'visivel'],
    ]);
    $content = (string) file_get_contents(loggerFile($dir));

    assertSame(4, substr_count($content, '"[redacted]"'));
    assertContains('visivel', $content);
    // Com aspas: o request_id da linha (hex aleatorio) as vezes contem "abc".
    assertFalse(strpos($content, '"abc"') !== false);
});

it('mascara segredo aninhado em listas', function (): void {
    $dir = loggerSetup();
    Logger::error('x', ['request' => ['password' => 'abc', 'items' => [['token' => 't']]]]);
    $content = (string) file_get_contents(loggerFile($dir));

    assertSame(2, substr_count($content, '"[redacted]"'));
});

it('inclui o contexto automatico de addContext', function (): void {
    $dir = loggerSetup();
    Logger::addContext(['user_uuid' => 'u-1', 'area' => 'admin']);
    Logger::error('x');
    $content = (string) file_get_contents(loggerFile($dir));

    assertContains('"user_uuid":"u-1"', $content);
    assertContains('"area":"admin"', $content);
});

it('provider e chamado a cada escrita', function (): void {
    $dir = loggerSetup();
    $calls = 0;
    Logger::setContextProvider(function () use (&$calls): array {
        $calls++;

        return ['user_uuid' => 'u-' . $calls];
    });
    Logger::error('a');
    Logger::error('b');
    $content = (string) file_get_contents(loggerFile($dir));

    assertSame(2, $calls);
    assertContains('"user_uuid":"u-1"', $content);
    assertContains('"user_uuid":"u-2"', $content);
});

it('exception no contexto vira classe/arquivo relativo/linha + bloco [stacktrace]', function (): void {
    $dir = loggerSetup();
    Logger::error('x', ['exception' => new RuntimeException('boom')]);
    $content = (string) file_get_contents(loggerFile($dir));

    assertContains('"exception":"RuntimeException"', $content);
    assertContains('"file":"scripts/tests/logger_test.php"', $content);
    $lines = explode("\n", $content);
    assertSame('[stacktrace]', $lines[1]);
});

it('apaga arquivos mais velhos que a retencao na primeira escrita do dia', function (): void {
    $dir = tempDir();
    $old = $dir . '/app-' . gmdate('Y-m-d', time() - 20 * 86400) . '.log';
    $recent = $dir . '/app-' . gmdate('Y-m-d', time() - 5 * 86400) . '.log';
    file_put_contents($old, "x\n");
    file_put_contents($recent, "x\n");
    Logger::reset();
    Logger::configure($dir, 'testing', 'debug', 14);
    Logger::error('x');

    assertFalse(is_file($old));
    assertTrue(is_file($recent));
});

it('pasta nao gravavel nao lanca', function (): void {
    $dir = loggerSetup();
    chmod($dir, 0555);
    $previous = ini_set('error_log', '/dev/null');
    Logger::error('x');
    ini_set('error_log', (string) $previous);

    assertFalse(Logger::isWritable());
    chmod($dir, 0755);
});

it('mensagem com quebra de linha nao forja linhas novas', function (): void {
    $dir = loggerSetup();
    Logger::error("a\n[2026-01-01T00:00:00+00:00] production.ERROR: forjado");
    $content = (string) file_get_contents(loggerFile($dir));

    assertSame(1, preg_match_all('/^\[/m', $content));
    assertContains('a\n[2026', $content);
});

function loggerThrowWithSecret(string $secret): void
{
    throw new RuntimeException('falha');
}

it('stacktrace nao inclui argumentos das funcoes', function (): void {
    $dir = loggerSetup();
    try {
        loggerThrowWithSecret('segredo-123');
    } catch (RuntimeException $e) {
        Logger::error('x', ['exception' => $e]);
    }
    $content = (string) file_get_contents(loggerFile($dir));

    assertFalse(strpos($content, 'segredo-123') !== false);
    assertContains('loggerThrowWithSecret()', $content);
    assertContains('{main}', $content);
});

it('sem configure() a linha vai para error_log', function (): void {
    Logger::reset();
    $file = tempDir() . '/php.log';
    $previous = ini_set('error_log', $file);
    Logger::error('boot falhou');
    ini_set('error_log', (string) $previous);

    assertContains('production.ERROR: boot falhou', (string) @file_get_contents($file));
});
