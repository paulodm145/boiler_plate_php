    <footer class="site-foot py-4 mt-5">
        <div class="container d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <span><?= htmlspecialchars($settings['site_name'] ?? 'App Boilerplate', ENT_QUOTES, 'UTF-8') ?> © <?= date('Y') ?></span>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            var root = document.documentElement;
            var toggle = document.getElementById('theme-toggle');
            var sunIcon = document.getElementById('theme-icon-sun');
            var moonIcon = document.getElementById('theme-icon-moon');

            function syncIcon() {
                var isDark = root.getAttribute('data-theme') === 'dark';
                sunIcon.classList.toggle('d-none', !isDark);
                moonIcon.classList.toggle('d-none', isDark);
            }

            syncIcon();

            if (toggle) {
                toggle.addEventListener('click', function () {
                    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                    root.setAttribute('data-theme', next);
                    syncIcon();
                    try {
                        localStorage.setItem('theme', next);
                    } catch (e) {
                        /* localStorage indisponível: tema não persiste, mas segue funcionando */
                    }
                });
            }
        })();
    </script>
</body>
</html>
