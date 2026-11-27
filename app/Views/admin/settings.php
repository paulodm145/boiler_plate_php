<?php
use App\Http\Form;

/** Selo nos campos de SMTP que vem do .env (a tela nao os controla). */
$envBadge = static function (string $field) use ($smtpFromEnv): string {
    return in_array($field, $smtpFromEnv ?? [], true) ? ' <span class="badge text-bg-info">.env</span>' : '';
};
$value = static fn (string $field): string => e(old($field, $settings[$field]));

require __DIR__ . '/partials/shell-top.php';
?>
            <div class="mb-4">
                <h1 class="h3 mb-1">Configurações</h1>
                <p class="text-secondary mb-0">Identidade da aplicação exibida no cabeçalho, rodapé e páginas públicas.</p>
            </div>

            <?php require __DIR__ . '/partials/form-errors.php'; ?>

            <form method="post" action="/admin/settings" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label" for="site_name">Nome da aplicação <span class="text-danger">*</span></label>
                    <input class="form-control<?= Form::invalid('site_name') ?>" id="site_name" name="site_name" type="text" maxlength="120"
                        value="<?= $value('site_name') ?>" required>
                    <?= Form::feedback('site_name') ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="site_description">Descrição</label>
                    <textarea class="form-control<?= Form::invalid('site_description') ?>" id="site_description" name="site_description" rows="3" maxlength="255"><?= $value('site_description') ?></textarea>
                    <?= Form::feedback('site_description') ?>
                </div>

                <h2 class="h5 mb-3">E-mail (SMTP)</h2>
                <p class="text-secondary small">Usado para enviar o link de redefinição de senha. Deixe a senha em branco para manter a atual.</p>
                <?php if (($smtpFromEnv ?? []) !== []): ?>
                    <div class="alert alert-info small">
                        Parte do SMTP está definida no arquivo <code>.env</code> do servidor, que tem prioridade sobre esta tela.
                        Os campos marcados com <span class="badge text-bg-info">.env</span> não mudam ao salvar aqui.
                    </div>
                <?php endif; ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label" for="smtp_host">Servidor SMTP<?= $envBadge('smtp_host') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_host') ?>" id="smtp_host" name="smtp_host" type="text" value="<?= $value('smtp_host') ?>">
                        <?= Form::feedback('smtp_host') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_port">Porta<?= $envBadge('smtp_port') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_port') ?>" id="smtp_port" name="smtp_port" type="text" inputmode="numeric" value="<?= $value('smtp_port') ?>">
                        <?= Form::feedback('smtp_port') ?>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_encryption">Criptografia<?= $envBadge('smtp_encryption') ?></label>
                        <?php $encryption = old('smtp_encryption', $settings['smtp_encryption']); ?>
                        <select class="form-select<?= Form::invalid('smtp_encryption') ?>" id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?= $encryption === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
                            <option value="ssl" <?= $encryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $encryption === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                        <?= Form::feedback('smtp_encryption') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_username">Usuário<?= $envBadge('smtp_username') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_username') ?>" id="smtp_username" name="smtp_username" type="text" value="<?= $value('smtp_username') ?>">
                        <?= Form::feedback('smtp_username') ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_password">Senha<?= $envBadge('smtp_password') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_password') ?>" id="smtp_password" name="smtp_password" type="password" value="" autocomplete="new-password">
                        <?= Form::feedback('smtp_password') ?>
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_from_address">E-mail do remetente<?= $envBadge('smtp_from_address') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_from_address') ?>" id="smtp_from_address" name="smtp_from_address" type="email" value="<?= $value('smtp_from_address') ?>">
                        <?= Form::feedback('smtp_from_address') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_from_name">Nome do remetente<?= $envBadge('smtp_from_name') ?></label>
                        <input class="form-control<?= Form::invalid('smtp_from_name') ?>" id="smtp_from_name" name="smtp_from_name" type="text" value="<?= $value('smtp_from_name') ?>">
                        <?= Form::feedback('smtp_from_name') ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="/admin">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Salvar</button>
                </div>
            </form>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
</body>
</html>
