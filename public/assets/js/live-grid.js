// Grid com busca ao vivo, ordenacao e paginacao no estilo do DataTables
// (modo server-side): o servidor continua filtrando/ordenando/paginando, e
// este script so busca o pedaco da tabela (?partial=1) e troca na tela,
// sem recarregar a pagina. Sem JS, a mesma tela funciona como antes.
//
// Marcacao esperada:
//   <div data-live-grid>
//     <form data-grid-filters method="get"> ...inputs... </form>
//     <div data-grid-results> ...tabela + paginacao... </div>
//   </div>
// Links de ordenacao/paginacao dentro dos resultados levam [data-grid-link].
(function () {
    'use strict';

    function init(grid) {
        var form = grid.querySelector('form[data-grid-filters]');
        var results = grid.querySelector('[data-grid-results]');
        var controller = null;
        var timer = null;

        if (!form || !results) {
            return;
        }

        // Camada de "Carregando..." por cima da tabela. Fica num wrapper em
        // volta dos resultados (que sao substituidos a cada busca), entao nao
        // some quando a tabela nova chega.
        var wrap = document.createElement('div');
        wrap.className = 'grid-results-wrap';
        results.parentNode.insertBefore(wrap, results);
        wrap.appendChild(results);

        var overlay = document.createElement('div');
        overlay.className = 'grid-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        var box = document.createElement('div');
        box.className = 'grid-overlay-box';
        var spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm';
        var label = document.createElement('span');
        label.textContent = 'Carregando…';
        box.appendChild(spinner);
        box.appendChild(label);
        overlay.appendChild(box);
        wrap.appendChild(overlay);

        // Anuncio para leitores de tela.
        var live = document.createElement('div');
        live.className = 'visually-hidden';
        live.setAttribute('aria-live', 'polite');
        wrap.appendChild(live);

        var overlayTimer = null;

        function showLoading() {
            results.classList.add('grid-loading');
            results.setAttribute('aria-busy', 'true');
            live.textContent = 'Carregando…';
            window.clearTimeout(overlayTimer);
            // Pequeno atraso: respostas rapidas nao fazem a tela piscar.
            overlayTimer = window.setTimeout(function () { overlay.classList.add('show'); }, 150);
        }

        function hideLoading() {
            window.clearTimeout(overlayTimer);
            overlay.classList.remove('show');
            results.classList.remove('grid-loading');
            results.removeAttribute('aria-busy');
        }

        function urlFromForm() {
            var params = new URLSearchParams();
            new FormData(form).forEach(function (value, key) {
                if (String(value).trim() !== '') {
                    params.append(key, value);
                }
            });
            // Mantem a ordenacao atual ao mudar filtros; volta para a pagina 1.
            var current = new URLSearchParams(window.location.search);
            ['sort', 'dir'].forEach(function (key) {
                if (current.has(key) && !params.has(key)) {
                    params.set(key, current.get(key));
                }
            });
            var query = params.toString();
            return window.location.pathname + (query ? '?' + query : '');
        }

        function load(url) {
            if (controller) {
                controller.abort();
            }
            controller = new AbortController();
            var current = controller;
            showLoading();

            var partialUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'partial=1';

            fetch(partialUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.redirected || !response.ok) {
                    // Sessao expirada ou erro: navega de verdade para mostrar a pagina certa.
                    window.location.href = url;
                    return null;
                }
                return response.text();
            }).then(function (html) {
                if (html === null) {
                    return;
                }
                // HTML gerado pelo proprio servidor, com os valores ja escapados.
                results.replaceChildren(document.createRange().createContextualFragment(html));
                window.history.replaceState(null, '', url);
                var count = results.textContent.match(/([\d.]+) registro\(s\)/);
                live.textContent = count ? count[1] + ' registro(s) encontrado(s)' : 'Lista atualizada';
            }).catch(function (error) {
                if (error.name !== 'AbortError') {
                    window.location.href = url;
                }
            }).finally(function () {
                // So a requisicao mais recente desliga o carregamento (uma
                // cancelada nao pode esconder o loading da que esta em curso).
                if (current === controller) {
                    hideLoading();
                }
            });
        }

        form.addEventListener('input', function (event) {
            if (event.target.matches('input[type="search"], input[type="text"]')) {
                window.clearTimeout(timer);
                showLoading(); // feedback imediato, enquanto espera a pessoa parar de digitar
                timer = window.setTimeout(function () { load(urlFromForm()); }, 300);
            }
        });

        form.addEventListener('change', function (event) {
            if (!event.target.matches('input[type="search"], input[type="text"]')) {
                load(urlFromForm());
            }
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            window.clearTimeout(timer);
            load(urlFromForm());
        });

        results.addEventListener('click', function (event) {
            var link = event.target.closest('a[data-grid-link]');
            if (!link || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }
            event.preventDefault();
            load(link.getAttribute('href'));
        });
    }

    document.querySelectorAll('[data-live-grid]').forEach(init);

    // Botao "Exportar Excel": a busca ao vivo muda a URL sem recarregar, entao
    // o link e montado na hora do clique com os filtros/ordenacao atuais.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-grid-export]');
        if (!link) {
            return;
        }
        var params = new URLSearchParams(window.location.search);
        ['page', 'per_page', 'partial'].forEach(function (key) { params.delete(key); });
        var base = link.getAttribute('href').split('?')[0];
        var query = params.toString();
        link.setAttribute('href', base + (query ? '?' + query : ''));
    });
})();
