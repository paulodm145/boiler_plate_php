<?php
/**
 * Tabela de usuarios (o pedaco que o live-grid.js troca com ?partial=1).
 * Recebe $page (App\Database\Page) e $list (App\Http\ListQuery).
 */
?>
<div class="table-responsive admin-table-wrap">
    <table class="table admin-table align-middle mb-0">
        <thead class="admin-table-head">
            <tr>
                <?= $list->sortLink('Nome', 'name') ?>
                <?= $list->sortLink('E-mail', 'email') ?>
                <?= $list->sortLink('Criado em', 'created') ?>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($page->items as $listedUser): ?>
                <tr>
                    <td><?= e($listedUser['name']) ?></td>
                    <td><?= e($listedUser['email']) ?></td>
                    <td><?= e(\App\Helpers\Br\Date::format($listedUser['created_at'])) ?></td>
                    <td class="text-end admin-actions">
                        <a class="admin-action-link" href="/admin/users/<?= e($listedUser['uuid']) ?>/edit">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($page->items === []): ?>
                <tr><td colspan="4" class="text-center text-secondary py-4">Nenhum usuário encontrado.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/pagination.php'; ?>
