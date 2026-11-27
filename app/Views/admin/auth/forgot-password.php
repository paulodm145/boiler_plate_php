<?php require dirname(__DIR__) . '/partials/head.php'; ?>
<body class="admin-login-shell">
    <main class="container py-5">
        <div class="row justify-content-center align-items-center" style="min-height:85vh">
            <div class="col-md-5">
                <div class="admin-login-brand text-center mb-4">Admin</div>
                <section class="content-surface p-4 p-md-5">
                    <h1 class="h4 mb-4">Esqueci minha senha</h1>

                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php require dirname(__DIR__) . '/partials/form-errors.php'; ?>

                    <?php if ($message !== null): ?>
                        <div class="alert alert-success"><?= e($message) ?></div>
                    <?php else: ?>
                        <form method="post" action="/admin/esqueci-senha" novalidate>
                            <?= \App\Core\Csrf::field() ?>
                            <div class="mb-4">
                                <label class="form-label" for="email">E-mail</label>
                                <input class="form-control<?= \App\Http\Form::invalid('email') ?>" id="email" type="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
                                <?= \App\Http\Form::feedback('email') ?>
                            </div>
                            <button class="btn btn-primary w-100" type="submit">Enviar link de redefinição</button>
                        </form>
                    <?php endif; ?>

                    <p class="text-center mt-3 mb-0"><a href="/admin/login">Voltar ao login</a></p>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
