<?php

declare(strict_types=1);

use App\Database\Database;

echo "\nDatabase\n";

it('shared() devolve sempre a mesma instancia dentro do processo', function (): void {
    assertTrue(Database::shared() === Database::shared());
});
