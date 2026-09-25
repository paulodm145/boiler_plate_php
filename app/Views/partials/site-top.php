<?php

use App\Core\Asset;
use App\Core\Env;
use App\Core\Text;

$siteName = $settings['site_name'] ?? 'App Boilerplate';
$active = $active ?? '';
$canonical = $canonical ?? null;
$ogType = $ogType ?? 'website';
$robots = $robots ?? null;
$metaDescription = Text::truncate($description ?? '', 155);

$appUrl = rtrim((string) Env::get('APP_URL', ''), '/');
$pageUrl = $canonical ?? $appUrl;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($robots !== null): ?>
        <meta name="robots" content="<?= htmlspecialchars($robots, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if ($canonical !== null): ?>
        <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>

    <meta property="og:site_name" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">

    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/favicon-512.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <script>
        (function () {
            try {
                var saved = localStorage.getItem('theme');
                var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet" integrity="sha384-PPIZEGYM1v8zp5Py7UjFb79S58UeqCL9pYVnVPURKEqvioPROaVAJKKLzvH2rDnI" crossorigin="anonymous">
    <link href="/assets/css/site-theme.css?v=<?= Asset::version('assets/css/site-theme.css') ?>" rel="stylesheet">
</head>
<body>
    <header class="site-head py-3">
        <nav class="navbar navbar-expand-md container">
            <a class="brand navbar-brand" href="/"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#site-nav" aria-controls="site-nav" aria-expanded="false" aria-label="Abrir menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="site-nav">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-md-4 gap-2 py-3 py-md-0">
                    <a class="navlink<?= $active === 'home' ? ' active' : '' ?>" href="/">Início</a>
                    <button class="icon-btn" id="theme-toggle" type="button" aria-label="Alternar tema claro/escuro" title="Alternar tema">
                        <i class="fa-solid fa-sun" id="theme-icon-sun"></i>
                        <i class="fa-solid fa-moon d-none" id="theme-icon-moon"></i>
                    </button>
                </div>
            </div>
        </nav>
    </header>
