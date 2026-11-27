<?php require __DIR__ . '/partials/shell-top.php'; ?>
            <div class="mb-4">
                <h1 class="h3 mb-1">Configurações</h1>
                <p class="text-secondary mb-0">Identidade da aplicação exibida no cabeçalho, rodapé e páginas públicas.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">Configurações salvas com sucesso.</div>
            <?php endif; ?>

            <form method="post" action="/admin/settings">
                <?= \App\Core\Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label" for="site_name">Nome da aplicação</label>
                    <input class="form-control" id="site_name" name="site_name" type="text"
                        value="<?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="site_description">Descrição</label>
                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <h2 class="h5 mb-3">E-mail (SMTP)</h2>
                <p class="text-secondary small">Usado pra enviar o link de redefinição de senha. Deixe a senha em branco pra manter a atual.</p>
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label" for="smtp_host">Servidor SMTP</label>
                        <input class="form-control" id="smtp_host" name="smtp_host" type="text"
                            value="<?= htmlspecialchars($settings['smtp_host'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_port">Porta</label>
                        <input class="form-control" id="smtp_port" name="smtp_port" type="text"
                            value="<?= htmlspecialchars($settings['smtp_port'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_encryption">Criptografia</label>
                        <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?= $settings['smtp_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS</option>
                            <option value="ssl" <?= $settings['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $settings['smtp_encryption'] === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_username">Usuário</label>
                        <input class="form-control" id="smtp_username" name="smtp_username" type="text"
                            value="<?= htmlspecialchars($settings['smtp_username'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="smtp_password">Senha</label>
                        <input class="form-control" id="smtp_password" name="smtp_password" type="password" value="">
                    </div>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_from_address">E-mail do remetente</label>
                        <input class="form-control" id="smtp_from_address" name="smtp_from_address" type="email"
                            value="<?= htmlspecialchars($settings['smtp_from_address'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="smtp_from_name">Nome do remetente</label>
                        <input class="form-control" id="smtp_from_name" name="smtp_from_name" type="text"
                            value="<?= htmlspecialchars($settings['smtp_from_name'], ENT_QUOTES, 'UTF-8') ?>">
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
