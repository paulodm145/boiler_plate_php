<?php

declare(strict_types=1);

echo "\nIdentificadores sem acento\n";

it('nenhum identificador de codigo tem acento', function (): void {
    // Regra do CLAUDE.md: classes, metodos, funcoes, variaveis e constantes sao
    // ASCII. Acento so em texto para o usuario e em documentacao.
    $root = dirname(__DIR__, 2);
    $paths = [$root . '/routes.php'];

    foreach (['app', 'scripts', 'public'] as $dir) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && !str_contains($file->getPathname(), '/app/Views/')) {
                $paths[] = $file->getPathname();
            }
        }
    }

    $identifierTokens = [T_STRING, T_VARIABLE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];
    $found = [];

    foreach ($paths as $path) {
        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], $identifierTokens, true) && preg_match('/[^\x00-\x7F]/', $token[1]) === 1) {
                $found[] = str_replace($root . '/', '', $path) . ':' . $token[2] . ':' . $token[1];
            }
        }
    }

    assertSame([], $found, 'identificadores com acento');
});
