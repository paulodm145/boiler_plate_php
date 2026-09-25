<?php
/** @var string $title */
$includeFontAwesome = $includeFontAwesome ?? false;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="robots" content="noindex,nofollow">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php if ($includeFontAwesome): ?>
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
    <?php endif; ?>
    <link href="/assets/css/app.css?v=<?= @filemtime(dirname(__DIR__, 4) . '/public/assets/css/app.css') ?: '1' ?>" rel="stylesheet">
</head>
