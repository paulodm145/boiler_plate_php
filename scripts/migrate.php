<?php

declare(strict_types=1);

use App\Core\Autoloader;
use App\Core\Env;
use App\Core\Migrator;
use App\Database\Database;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/app/Core/Autoloader.php';

$autoloader = new Autoloader($rootPath . '/app');
$autoloader->register();

Env::load($rootPath . '/.env');

$results = (new Migrator(Database::shared(), $rootPath . '/database/migrations'))->run();

foreach ($results as $result) {
    if ($result['status'] === 'executed') {
        echo "Executed {$result['migration']}\n";
    } elseif ($result['status'] === 'skipped') {
        echo "Skipping {$result['migration']}\n";
    } elseif ($result['status'] === 'empty') {
        echo "Skipping empty migration {$result['migration']}\n";
    } else {
        fwrite(STDERR, "Failed {$result['migration']}: {$result['error']}\n");
        exit(1);
    }
}

echo "Migrations completed\n";
