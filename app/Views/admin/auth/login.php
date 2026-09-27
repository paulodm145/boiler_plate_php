<?php require dirname(__DIR__) . '/partials/head.php'; ?>
<body class="admin-login-shell">
    <main class="container py-5">
        <div class="row justify-content-center align-items-center" style="min-height:85vh">
            <div class="col-md-5">
                <div class="admin-login-brand text-center mb-4">Admin</div>
                <section class="content-surface p-4 p-md-5">
                    <h1 class="h4 mb-4">Entrar no admin</h1>

                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <form method="post" action="/admin/login">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input class="form-control" id="email" type="email" name="email" required autofocus>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="password">Senha</label>
                            <input class="form-control" id="password" type="password" name="password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Entrar</button>
                    </form>
                    <p class="text-center mt-3 mb-0"><a href="/admin/esqueci-senha">Esqueci minha senha</a></p>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
