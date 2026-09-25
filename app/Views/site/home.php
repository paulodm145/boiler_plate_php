<?php
$active = 'home';
require dirname(__DIR__) . '/partials/site-top.php';
?>
    <main class="container-lg py-5">
        <div class="mx-auto text-center" style="max-width:640px">
            <h1 class="mb-3" style="font-size:2.2rem;font-weight:700"><?= htmlspecialchars($settings['site_name'], ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="text-muted mb-4"><?= htmlspecialchars($settings['site_description'], ENT_QUOTES, 'UTF-8') ?></p>
            <a class="btn btn-primary" href="/admin">Entrar no admin</a>
        </div>
    </main>
<?php require dirname(__DIR__) . '/partials/site-bottom.php'; ?>
