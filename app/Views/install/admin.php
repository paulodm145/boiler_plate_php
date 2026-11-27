<?php

/** @var array $values */
/** @var string|null $error */
/** @var int $existingUsers */

$field = function (string $key) use ($values): string {
    return htmlspecialchars((string) ($values[$key] ?? ''), ENT_QUOTES, 'UTF-8');
};

ob_start();
?>
<h2>Conta de administrador</h2>
<?php if ($existingUsers > 0): ?>
    <p>O banco ja tem <strong><?= $existingUsers ?> usuario(s)</strong> . Voce pode concluir sem criar outro administrador, ou cadastrar um novo abaixo.</p>
    <form method="post" action="/install/admin/skip">
        <div class="actions">
            <button type="submit" class="button">Concluir sem criar administrador</button>
        </div>
    </form>
    <hr>
<?php else: ?>
    <p>Esta e a conta que voce usara para entrar no painel.</p>
<?php endif; ?>

<?php if ($error !== null): ?>
    <p class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="post" action="/install/admin">
    <div class="field">
        <label for="name">Nome</label>
        <input type="text" id="name" name="name" value="<?= $field('name') ?>" required autofocus>
    </div>
    <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= $field('email') ?>" required autocomplete="off">
    </div>
    <div class="field">
        <label for="password">Senha</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
        <p class="field__hint">Minimo de 8 caracteres.</p>
    </div>
    <div class="field">
        <label for="password_confirmation">Repita a senha</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
    </div>
    <div class="actions">
        <button type="submit" class="button">Criar conta e concluir</button>
    </div>
</form>
<?php
$content = ob_get_clean();

require __DIR__ . '/layout.php';
