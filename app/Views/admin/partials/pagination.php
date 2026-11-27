<?php
/**
 * Rodape de listagem: "N registro(s) · página X de Y" + links. Recebe
 * $page (App\Database\Page) e $list (App\Http\ListQuery).
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <p class="text-secondary small mb-0">
        <?php if ($page->total === 0): ?>
            Nenhum registro encontrado.
        <?php else: ?>
            <?= e(\App\Helpers\Number::format($page->total)) ?> registro(s) · exibindo <?= $page->from ?>–<?= $page->to ?> · página <?= $page->page ?> de <?= $page->pages ?>
        <?php endif; ?>
    </p>
    <?php if ($page->pages > 1): ?>
        <nav aria-label="Paginação">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item<?= $page->page === 1 ? ' disabled' : '' ?>">
                    <a data-grid-link class="page-link" href="<?= e($list->url(['page' => max(1, $page->page - 1)])) ?>">Anterior</a>
                </li>
                <?php foreach ($page->window() as $number): ?>
                    <?php if ($number === null): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php else: ?>
                        <li class="page-item<?= $number === $page->page ? ' active' : '' ?>">
                            <a data-grid-link class="page-link" href="<?= e($list->url(['page' => $number])) ?>"><?= $number ?></a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li class="page-item<?= $page->page === $page->pages ? ' disabled' : '' ?>">
                    <a data-grid-link class="page-link" href="<?= e($list->url(['page' => min($page->pages, $page->page + 1)])) ?>">Próxima</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
