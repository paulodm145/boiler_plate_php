<?php require __DIR__ . '/partials/shell-top.php'; ?>
            <div class="mb-4">
                <h1 class="h3 mb-1">Configurações</h1>
                <p class="text-secondary mb-0">Identidade da aplicação exibida no cabeçalho, rodapé e páginas públicas.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success">Configurações salvas com sucesso.</div>
            <?php endif; ?>

            <form method="post" action="/admin/settings">
                <div class="mb-3">
                    <label class="form-label" for="site_name">Nome da aplicação</label>
                    <input class="form-control" id="site_name" name="site_name" type="text"
                        value="<?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="site_description">Descrição</label>
                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a class="btn btn-outline-secondary" href="/admin">Cancelar</a>
                    <button class="btn btn-primary" type="submit">Salvar</button>
                </div>
            </form>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
</body>
</html>
