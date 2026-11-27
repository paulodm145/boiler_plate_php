<?php
use App\Http\Form;

$action = $isNew ? '/admin/users' : '/admin/users/' . $adminUser['uuid'] . '/edit';
require __DIR__ . '/partials/shell-top.php';
?>
            <h1 class="h3 mb-4"><?= $isNew ? 'Novo usuário' : 'Editar usuário' ?></h1>
            <?php require __DIR__ . '/partials/form-errors.php'; ?>
            <form method="post" action="<?= e($action) ?>" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label" for="name">Nome <span class="text-danger">*</span></label>
                    <input class="form-control<?= Form::invalid('name') ?>" id="name" name="name" maxlength="120"
                        value="<?= e(old('name', $adminUser['name'])) ?>" required>
                    <?= Form::feedback('name') ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="email">E-mail <span class="text-danger">*</span></label>
                    <input class="form-control<?= Form::invalid('email') ?>" id="email" name="email" type="email" maxlength="180"
                        value="<?= e(old('email', $adminUser['email'])) ?>" required>
                    <?= Form::feedback('email') ?>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="password">
                            Senha <?= $isNew ? '<span class="text-danger">*</span>' : '<span class="text-secondary small">(deixe em branco para manter)</span>' ?>
                        </label>
                        <input class="form-control<?= Form::invalid('password') ?>" id="password" name="password" type="password"
                            autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                        <?= Form::feedback('password') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirme a senha <?= $isNew ? '<span class="text-danger">*</span>' : '' ?></label>
                        <input class="form-control<?= Form::invalid('password') ?>" id="password_confirmation" name="password_confirmation" type="password"
                            autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <div>
                        <?php if (!$isNew): ?>
                            <button class="btn btn-outline-danger" type="submit" form="delete-user-form">Excluir</button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="/admin/users">Cancelar</a>
                        <button class="btn btn-primary" type="submit">Salvar</button>
                    </div>
                </div>
            </form>
            <?php if (!$isNew): ?>
                <form id="delete-user-form" method="post" action="/admin/users/<?= e($adminUser['uuid']) ?>/delete" onsubmit="return confirm('Excluir este usuário?');"><?= \App\Core\Csrf::field() ?></form>
            <?php endif; ?>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
</body>
</html>
