<?php

declare(strict_types=1);

use App\Helpers\LogPath;

echo "\nLogPath\n";

it('token do link de redefinicao vira :token', function (): void {
    assertSame('/admin/redefinir-senha/:token', LogPath::mask('/admin/redefinir-senha/' . str_repeat('a1', 32)));
});

it('token no meio do caminho vira :token', function (): void {
    assertSame('/convite/:token/aceitar', LogPath::mask('/convite/' . str_repeat('f0', 32) . '/aceitar'));
});

it('uuid continua no caminho', function (): void {
    $path = '/admin/users/0f8fad5b-d9cb-469f-a165-70867728950e/edit';

    assertSame($path, LogPath::mask($path));
});
