<?php require __DIR__ . '/partials/shell-top.php'; ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Usuários</h1>
                <a class="btn btn-primary" href="/admin/users/create">Novo usuário</a>
            </div>
            <div data-live-grid>
                <form class="row g-2 align-items-end mb-3" method="get" action="/admin/users" data-grid-filters>
                    <div class="col-md-6">
                        <label class="form-label small mb-1" for="q">Buscar</label>
                        <input class="form-control" id="q" name="q" type="search" value="<?= e($list->search()) ?>" placeholder="Nome ou e-mail">
                    </div>
                    <div class="col-6 col-md-3">
                        <?php require __DIR__ . '/partials/page-size.php'; ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <button class="btn btn-outline-secondary w-100" type="submit">Filtrar</button>
                    </div>
                </form>
                <div data-grid-results>
                    <?php require __DIR__ . '/partials/users-table.php'; ?>
                </div>
            </div>
<?php require __DIR__ . '/partials/shell-bottom.php'; ?>
<script src="<?= e(\App\Helpers\Asset::url('js/live-grid.js')) ?>"></script>
</body>
</html>
