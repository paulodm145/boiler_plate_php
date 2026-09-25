<?php require __DIR__ . '/partials/shell-top.php'; ?>
            <div class="mb-4">
                <h1 class="h3 mb-1">Olá, <?= htmlspecialchars(explode('@', $user['email'] ?? '')[0] ?? '', ENT_QUOTES, 'UTF-8') ?> 👋</h1>
                <p class="text-secondary mb-0">Visão geral da aplicação.</p>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <a class="btn btn-primary" href="/admin/users/create"><i class="fa-solid fa-plus me-1"></i> Novo usuário</a>
                <a class="btn btn-outline-secondary" href="/admin/settings"><i class="fa-solid fa-gear me-1"></i> Configurações</a>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <a href="/admin/users" class="dash-stat">
                        <span class="dash-stat-value"><?= $userCount ?></span>
                        <span class="dash-stat-label">Usuários</span>
                    </a>
                </div>
            </div>

            <?php if (count($pendingMigrationNames) > 0): ?>
                <div class="content-surface p-3 p-md-4 mb-4" style="border-color:#f0ad4e">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h2 class="h6 mb-2"><i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> <?= count($pendingMigrationNames) ?> migration(s) pendente(s)</h2>
                            <ul class="mb-0 text-secondary small">
                                <?php foreach (array_slice($pendingMigrationNames, 0, 5) as $migration): ?>
                                    <li><?= htmlspecialchars($migration, ENT_QUOTES, 'UTF-8') ?></li>
                                <?php endforeach; ?>
                                <?php if (count($pendingMigrationNames) > 5): ?>
                                    <li>e mais <?= count($pendingMigrationNames) - 5 ?>…</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <a class="btn btn-warning text-nowrap" href="/admin/atualizar">Atualizar banco</a>
                    </div>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
</body>
</html>
