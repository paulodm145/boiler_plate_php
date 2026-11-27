<?php $flashMessage = \App\Http\Flash::pull(); ?>
<?php if ($flashMessage !== null): ?>
    <div class="alert alert-<?= e($flashMessage['type']) ?> alert-dismissible fade show" role="alert">
        <?= e($flashMessage['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
    </div>
<?php endif; ?>
