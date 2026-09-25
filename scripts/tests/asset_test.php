<?php

declare(strict_types=1);

use App\Core\Asset;

echo "\nAsset\n";

it('version() devolve o mtime de um arquivo existente em public/', function (): void {
    $version = Asset::version('assets/css/app.css');

    assertTrue(ctype_digit($version), 'esperava um timestamp numerico, recebido ' . var_export($version, true));
    assertTrue((int) $version > 0);
});

it('version() cai pra "1" quando o arquivo nao existe', function (): void {
    assertSame('1', Asset::version('assets/css/nao-existe-xyz.css'));
});

it('version() aceita o path com ou sem barra inicial', function (): void {
    assertSame(Asset::version('assets/css/app.css'), Asset::version('/assets/css/app.css'));
});
