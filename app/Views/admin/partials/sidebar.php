<?php

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');

$isActive = function (string $prefix) use ($currentPath): bool {
    if ($prefix === '/admin') {
        return $currentPath === '/admin';
    }

    return strpos($currentPath, $prefix) === 0;
};

$navItems = [
    ['href' => '/admin', 'icon' => 'gauge', 'label' => 'Painel', 'prefix' => '/admin'],
    ['href' => '/admin/users', 'icon' => 'users', 'label' => 'Usuários', 'prefix' => '/admin/users'],
    ['href' => '/admin/settings', 'icon' => 'gear', 'label' => 'Configurações', 'prefix' => '/admin/settings'],
];
?>
<div class="offcanvas-lg offcanvas-start admin-sidebar" tabindex="-1" id="adminSidebar" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title" id="adminSidebarLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <a class="admin-brand d-none d-lg-flex" href="/admin">Admin</a>
        <nav class="admin-nav">
            <?php foreach ($navItems as $navItem): ?>
                <a class="admin-nav-link<?= $isActive($navItem['prefix']) ? ' active' : '' ?>" href="<?= $navItem['href'] ?>">
                    <span class="admin-nav-label">
                        <?= \App\Core\Icon::svg($navItem['icon'], ['class' => 'admin-nav-icon']) ?>
                        <?= $navItem['label'] ?>
                    </span>
                    <?php if (!empty($navItem['badge'])): ?>
                        <span class="badge bg-danger rounded-pill"><?= (int) $navItem['badge'] ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-sidebar-footer">
            <?php if (($user['email'] ?? '') !== ''): ?>
                <div class="admin-sidebar-user"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="/admin/logout">
                <?= \App\Core\Csrf::field() ?>
                <button class="btn btn-outline-light btn-sm w-100" type="submit">
                    <?= \App\Core\Icon::svg('arrow-right-from-bracket', ['class' => 'me-1']) ?> Sair
                </button>
            </form>
        </div>
    </div>
</div>
