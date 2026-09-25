<?php
/** @var string $title */
use App\Core\Asset;

$includeFontAwesome = $includeFontAwesome ?? false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <?php if ($includeFontAwesome): ?>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet" integrity="sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI" crossorigin="anonymous">
    <?php endif; ?>
    <link href="/assets/css/app.css?v=<?= Asset::version('assets/css/app.css') ?>" rel="stylesheet">
</head>
