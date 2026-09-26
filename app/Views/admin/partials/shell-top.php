<?php require __DIR__ . '/head.php'; ?>
<body>
<div class="admin-shell">
    <?php require __DIR__ . '/sidebar.php'; ?>
    <div class="admin-main">
        <div class="admin-topbar d-lg-none">
            <button class="admin-topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Abrir menu">
                <?= \App\Core\Icon::svg('bars') ?>
            </button>
            <span class="admin-topbar-brand">Admin</span>
        </div>
        <main class="admin-content">
            <?php /** @var int $pendingCount vem do Controller, ver App\Core\InstallState::pendingCount() */ ?>
            <?php if ($pendingCount > 0): ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center">
                    <span><?= $pendingCount ?> migration(s) pendente(s) apos o ultimo envio de arquivos.</span>
                    <a class="btn btn-sm btn-warning" href="/admin/atualizar">Atualizar banco</a>
                </div>
            <?php endif; ?>
            <section class="content-surface p-4 p-md-5">
