<?php

declare(strict_types=1);

echo "\nCSRF nos formularios\n";

it('todo formulario POST fora do instalador envia o token CSRF', function (): void {
    // As rotas POST do admin passam por VerifyCsrfToken: um <form method="post">
    // sem Csrf::field() devolve 403 ao salvar (foi o bug da tela Configuracoes).
    // O instalador fica de fora: suas rotas nao usam CSRF (protegidas pela INSTALL_KEY).
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app/Views'));
    $missing = [];

    foreach ($views as $file) {
        if ($file->getExtension() !== 'php' || strpos($file->getPathname(), '/Views/install/') !== false) {
            continue;
        }

        preg_match_all('#<form\b[^>]*method="post"[^>]*>(.*?)</form>#is', file_get_contents($file->getPathname()), $forms);

        foreach ($forms[1] as $index => $body) {
            if (strpos($body, 'Csrf::field()') === false) {
                $missing[] = str_replace(dirname(__DIR__, 2) . '/', '', $file->getPathname()) . ' (form ' . ($index + 1) . ')';
            }
        }
    }

    assertSame([], $missing, 'formularios POST sem token CSRF');
});
