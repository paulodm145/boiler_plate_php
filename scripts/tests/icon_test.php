<?php

declare(strict_types=1);

use App\Core\Icon;

echo "\nIcon\n";

it('svg() devolve um <svg> com o path do icone', function (): void {
    $svg = Icon::svg('plus');

    assertContains('<svg', $svg);
    assertContains('viewBox="0 0 448 512"', $svg);
    assertContains('<path d=', $svg);
});

it('svg() aceita atributos extras (id, class) sem sobrescrever os fixos', function (): void {
    $svg = Icon::svg('sun', ['id' => 'theme-icon-sun', 'class' => 'd-none']);

    assertContains('id="theme-icon-sun"', $svg);
    assertContains('class="d-none"', $svg);
    assertContains('fill="currentColor"', $svg);
});

it('svg() lanca excecao pra um nome de icone desconhecido', function (): void {
    assertThrows(function (): void {
        Icon::svg('nao-existe');
    });
});
