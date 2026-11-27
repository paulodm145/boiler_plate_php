# Fase 1 — Base e correções: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Boilerplate funcionando de ponta a ponta em PHP 8.2, com CSRF, log de erros, cabeçalhos de segurança, SMTP pelo `.env`, `.htaccess` endurecido e instalador sem admin obrigatório.

**Architecture:** A maior parte é portar código já testado do mydaily (`~/Projetos/mydaily`) e do lorencine (`~/Projetos/lorencine/sistema`, fork deste repo a partir do `e2aa83d`; use `git -C ~/Projetos/lorencine/sistema show <commit>` para ver os diffs citados). Cada tarefa é um commit na branch `feature/fase-1-base-e-correcoes`.

**Tech Stack:** PHP 8.2 + Apache + MySQL 8 (Docker), PHP puro, runner de testes próprio (`scripts/test_install.php`: `it()`, `assertSame()`, `assertTrue()`, `assertContains()`, `assertThrows()`, `tempDir()`).

**Spec:** `docs/superpowers/specs/2026-11-27-fase-1-base-e-correcoes-design.md`

## Global Constraints

- PHP mínimo `8.2.0`. Código novo pode usar sintaxe 8.x; código antigo só muda se der erro ou deprecation.
- Portas e nomes do Docker ficam iguais: `8002:80`, `3308:3306`, `prb_admin_php_*`.
- Nada específico de outro projeto (nome "mydaily", "Lorencini", tenant, impersonação, "legado") entra no código, nos textos ou nos nomes de arquivo de log.
- Todo teste novo é registrado com `require_once` em `scripts/test_install.php`.
- Suíte: `docker compose exec --user www-data app php scripts/test_install.php`, rodada sozinha.
- Lint: `docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli php -l <arquivo>`.
- Rotas do instalador continuam **sem** CSRF (protegidas pela `INSTALL_KEY`).
- Commits **atômicos e semânticos, no infinitivo** (`feat(x): adicionar ...`, `fix(x): corrigir ...`): um commit por ação média/grande, sem commits de ajuste pequeno ou supérfluo. Terminam com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- **Nenhum acento em código**: nomes de classes, métodos, funções, variáveis, constantes, chaves de array, rotas e arquivos são ASCII; prefira inglês. Acento só em texto para o usuário e em documentação.

## Review Focus

1. **POST via `fetch` sem corpo de formulário** (polling do `/admin/atualizar` e do `/install/migrate/run`): o de admin precisa mandar `X-CSRF-Token` e continuar funcionando; o do instalador não pode passar a exigir token. Teste na Task 1 cobre o middleware aceitando o cabeçalho; verificação manual cobre os dois fluxos na Task 8.
2. **Exceção depois que já houve saída** (headers enviados no meio de uma view): o `ErrorHandler` não pode lançar de novo nem mostrar página em branco. Teste "render com headers ja enviados nao lanca" na Task 3.
3. **Log em pasta sem permissão** (hospedagem com `storage/logs` não gravável): a requisição segue, a linha vai para `error_log`. Teste "pasta nao gravavel nao lanca" na Task 3, rodado como `www-data`.
4. **URL de redefinição de senha no log** (`/admin/redefinir-senha/{token}`): o token de 64 hex vira `:token`. Teste de `LogPath::mask` na Task 3 com esse caminho exato.
5. **`SMTP_PASSWORD` definido mas vazio no `.env`**: não pode apagar a senha salva na tela. Teste na Task 5.

---

### Task 1: CSRF

**Files:**
- Create: `app/Core/Csrf.php`, `app/Core/Middleware/VerifyCsrfToken.php` (copiar de `~/Projetos/lorencine/sistema/app/Core/`, iguais)
- Modify: `app/Views/admin/settings.php` (falta `Csrf::field()`; ver lorencine `3dfd27f`), `app/Views/admin/update.php` e `public/assets/js/migrate-progress.js` (token no cabeçalho; ver lorencine `892f129`)
- Test: `scripts/tests/csrf_test.php` (novo), `scripts/tests/csrf_forms_test.php` (copiar do lorencine)

**Interfaces:**
- Produces: `Csrf::token(): string`, `Csrf::field(): string` (input hidden `_csrf`), `Csrf::verify(?string $token): bool`; `VerifyCsrfToken::handle(array $routeParams): ?string` → `null` ou `Router::HALT`. Aceita `$_POST['_csrf']` ou `$_SERVER['HTTP_X_CSRF_TOKEN']`.

- [ ] **Step 1: Escrever os testes**

`scripts/tests/csrf_test.php` (cada caso limpa `$_POST`, `$_SERVER['HTTP_X_CSRF_TOKEN']` e `$_SESSION['csrf_token']`; capture a saída do `ErrorPage::forbidden()` com `ob_start()`/`ob_end_clean()`):
- `it('token e estavel na sessao e tem 64 hex')`: `Csrf::token() === Csrf::token()` e `preg_match('/^[0-9a-f]{64}$/', ...)`.
- `it('sem token o middleware para a requisicao')`: `assertSame(Router::HALT, (new VerifyCsrfToken())->handle([]))`.
- `it('token errado para a requisicao')`: `$_POST['_csrf'] = 'x'` → `HALT`.
- `it('token certo no POST segue')`: `$_POST['_csrf'] = Csrf::token()` → `null`.
- `it('token certo no cabecalho X-CSRF-Token segue')`: `$_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::token()` → `null`.
- `it('field() escapa e usa o nome _csrf')`: `assertContains('name="_csrf"', Csrf::field())`.

Registrar `csrf_test.php` e `csrf_forms_test.php` em `scripts/test_install.php`.

- [ ] **Step 2: Rodar e ver falhar**: `docker compose exec --user www-data app php scripts/test_install.php`. Esperado: fatal/falha por classe `App\Core\Csrf` inexistente.
- [ ] **Step 3: Copiar as duas classes do lorencine.**
- [ ] **Step 4: Rodar.** Esperado: `csrf_test` passa; `csrf_forms_test` falha apontando `app/Views/admin/settings.php (form 1)`.
- [ ] **Step 5: Incluir `<?= \App\Core\Csrf::field() ?>` no form de `settings.php`; aplicar o diff do `892f129` em `update.php` (expõe o token, ex. `data-csrf`) e `migrate-progress.js` (envia `X-CSRF-Token` só quando o token existe, para o instalador seguir sem ele).**
- [ ] **Step 6: Rodar a suíte.** Esperado: tudo verde.
- [ ] **Step 7: Commit** `fix(csrf): adicionar Csrf e VerifyCsrfToken que faltavam no boilerplate`.

### Task 2: PHP 8.2

**Files:**
- Modify: `docker/php/Dockerfile` (diff do lorencine `648fd16` só na parte de PHP: `FROM php:8.2-apache`, remover o bloco `archive.debian.org`, `gd --with-freetype --with-jpeg --with-webp`; comentário genérico: "Alvo de producao: PHP 8.2 + Apache + MySQL. Ao trocar a versao, ajuste tambem app/Core/Requirements.php (MINIMUM_PHP)."), `app/Core/Requirements.php:7`
- Test: `scripts/tests/requirements_test.php`

- [ ] **Step 1: Teste** `it('exige PHP 8.2.0 ou superior')`: o item de PHP de `Requirements` tem `label === 'PHP 8.2.0 ou superior'` (use o método público que o teste atual já usa para listar os checks).
- [ ] **Step 2: Rodar e ver falhar** (label ainda `7.2.0`).
- [ ] **Step 3: `MINIMUM_PHP = '8.2.0'` e Dockerfile.** Rebuild: `docker compose up -d --build`; `docker compose exec app php -v` mostra `PHP 8.2`.
- [ ] **Step 4: Lint geral**: `find app scripts public routes.php index.php -name '*.php' -print0 | xargs -0 -n1 docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli php -l | grep -v '^No syntax errors'`. Esperado: saída vazia.
- [ ] **Step 5: Suíte como `www-data`.** Corrigir qualquer deprecation/erro do 8.x que aparecer (mudança mínima, no arquivo que a causou). Esperado: verde e sem `Deprecated:` na saída.
- [ ] **Step 6: Commit** `chore(php): subir o boilerplate para PHP 8.2`.

### Task 3: Logger, LogPath e ErrorHandler

**Files:**
- Create: `app/Helpers/LogPath.php`, `app/Core/Logger.php`, `app/Core/ErrorHandler.php` (de `~/Projetos/mydaily/app/...`)
- Modify: `app/Core/ErrorPage.php` (portar do mydaily o `wantsJson()` e o `request_id` na 500), `app/Views/errors/500.php` (bloco "Código do erro" como em `mydaily/app/Views/errors/500.php:19-21`), `public/index.php` (trocar o `set_exception_handler` + `register_shutdown_function` inline), `scripts/migrate.php` (chamar `ErrorHandler::register`), `.env.example`, `.env.docker`, `.gitignore` (`/storage/logs/*`, `!/storage/logs/.gitkeep`)
- Create: `storage/logs/.gitkeep`
- Test: `scripts/tests/logger_test.php`, `scripts/tests/error_handler_test.php`, `scripts/tests/log_path_test.php` (portar do mydaily)

**Interfaces:**
- Produces: `Logger::configure(string $directory, string $environment = 'production', string $minLevel = 'warning', int $retentionDays = 14): void`, `Logger::configureFromEnv(string $rootPath): void` (lê `APP_ENV`, `LOG_LEVEL`, `LOG_RETENTION_DAYS`), `Logger::debug|info|warning|error|critical(string $message, array $context = []): void`, `Logger::requestId(): string`, `Logger::reset(): void`; `ErrorHandler::register(string $rootPath): void`, `ErrorHandler::showErrorsOnScreen(bool $show): void`; `LogPath::mask(string $path): string`; `ErrorPage::wantsJson(): bool`.

Adaptações obrigatórias ao portar:
- Arquivo de log: `storage/logs/app-YYYY-MM-DD.log` (não `mydaily-`).
- Provedor de contexto do `ErrorHandler`: só `user_uuid` quando `Auth::check()`; remover impersonação.
- `public/index.php`: `ErrorHandler::register($rootPath)` logo após o autoloader; depois de `Env::load()`, `Logger::configureFromEnv($rootPath)` e `ErrorHandler::showErrorsOnScreen(Env::get('APP_DEBUG', 'false') === 'true')`.
- `.env.example` e `.env.docker`: `LOG_LEVEL=debug` e `LOG_RETENTION_DAYS=14`, com comentário `# debug|info|warning|error|critical; padrao: debug com APP_DEBUG=true, senao warning`.

- [ ] **Step 1: Portar os três testes**, ajustando o nome de arquivo para `app-` e removendo casos de impersonação. Acrescentar em `log_path_test.php`: `it('token do link de redefinicao vira :token')` → `LogPath::mask('/admin/redefinir-senha/' . str_repeat('a1', 32))` é `'/admin/redefinir-senha/:token'`; e `it('uuid continua no caminho')` → `/admin/users/<uuid>/edit` sem mudança.
- [ ] **Step 2: Rodar e ver falhar** (classes inexistentes).
- [ ] **Step 3: Portar as classes com as adaptações acima.**
- [ ] **Step 4: Rodar como `www-data`.** Esperado: verde, incluindo "pasta nao gravavel nao lanca" e "render com headers ja enviados nao lanca".
- [ ] **Step 5: Verificar na web**: rota temporária local (não commitar) que lança `RuntimeException` → com `APP_DEBUG=false` a resposta é 500 com "Código do erro: <8 hex>" e o mesmo id aparece em `storage/logs/app-<data UTC>.log`.
- [ ] **Step 6: Commit** `feat(log): adicionar Logger, ErrorHandler e request_id na pagina 500`.

### Task 4: Cabeçalhos de segurança e `.htaccess` da raiz endurecido

**Files:**
- Create: `app/Http/SecurityHeaders.php` (de `mydaily/app/Http/SecurityHeaders.php`, **sem** `Csp`/nonce/Report-Only)
- Modify: `public/index.php` (`SecurityHeaders::send()` depois de `Auth::start()`), `.htaccess` (também as regras de bloqueio do lorencine `faebe28`) e `public/.htaccess` (bloco `<IfModule mod_headers.c>` com `X-Frame-Options "SAMEORIGIN"`, `X-Content-Type-Options "nosniff"`, `Referrer-Policy "strict-origin-when-cross-origin"`, como `mydaily/public/.htaccess:30-32`)
- Test: `scripts/tests/security_headers_test.php`

**Interfaces:**
- Produces: `SecurityHeaders::send(): void`, `SecurityHeaders::headersFor(bool $https): array<string,string>`.

- [ ] **Step 1: Testes**:
  - `it('toda resposta trava plugin, base, moldura e formulario para fora')`: `headersFor(false)['Content-Security-Policy'] === "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'"`, `Permissions-Policy === 'camera=(), microphone=(), geolocation=(), payment=(), usb=()'`, `Cross-Origin-Opener-Policy === 'same-origin'`.
  - `it('hsts so sob https, sem subdominios nem preload')`: ausente com `false`; `'max-age=31536000'` com `true`.
  - `it('sem CSP de scripts nesta fase')`: nenhuma chave `Content-Security-Policy-Report-Only`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar e ligar no `index.php` e nos `.htaccess`.**
- [ ] **Step 4: Suíte verde; `curl -sI localhost:8002/admin/login` mostra os três cabeçalhos do `.htaccess` e os do PHP.**
- [ ] **Step 5: Aplicar no `.htaccess` da raiz o bloqueio do lorencine `faebe28` e verificar** num Apache servindo a raiz: `docker run --rm -d -p 8099:80 -v "$(pwd):/var/www/html" --name ht php:8.2-apache sh -c 'a2enmod rewrite headers && sed -i "s/AllowOverride None/AllowOverride All/" /etc/apache2/apache2.conf && apache2-foreground'`; `curl -s -o /dev/null -w '%{http_code}\n'` em `/lib/PHPMailer/PHPMailer.php`, `/docs/superpowers/plans/`, `/routes.php`, `/CLAUDE.md`, `/.env`, `/docker-compose.yml` → todos `403`; `/assets/css/app.css` → `200`. `docker stop ht`. Se não der para montar o cenário, registrar no commit "verificado só por leitura".
- [ ] **Step 6: Commit** `feat(seguranca): adicionar cabecalhos de seguranca e bloquear arquivos fora de public/`.

### Task 5: SMTP pelo `.env`

**Files:**
- Modify: `app/Core/Mail/SmtpMailer.php`, `app/Controllers/AdminSettingController.php`, `app/Views/admin/settings.php`, `.env.example` (diff do lorencine `8665116`; `SMTP_FROM_NAME="App Boilerplate"`)
- Test: `scripts/tests/mailer_test.php` (casos do lorencine `8665116`)

**Interfaces:**
- Produces: `SmtpMailer::ENV_KEYS` (setting → variável `SMTP_*`), `SmtpMailer::resolveConfig(array $settings): array`, `SmtpMailer::envOverriddenKeys(): string[]`. A view recebe `$envOverridden` (lista de chaves) e mostra "Definido no .env" no campo e o deixa `readonly`.

- [ ] **Step 1: Portar os testes do lorencine e acrescentar** `it('SMTP_PASSWORD vazio no .env nao apaga a senha salva')`: com `SMTP_PASSWORD=''` no `Env`, `resolveConfig(['smtp_password' => 'salva', ...])['smtp_password'] === 'salva'`; e `it('criptografia invalida vira tls')`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Aplicar o diff.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(email): permitir configurar o SMTP pelo .env`.

### Task 6: Instalador sem administrador obrigatório

**Files:**
- Modify: `app/Core/InstallRoutes.php`, `app/Controllers/InstallController.php`, `app/Views/install/admin.php`, `app/Views/install/done.php` (diff do lorencine `19bbfb8`; comentário e textos sem "legado": "O banco ja tem N usuario(s). Voce pode concluir sem criar outro administrador.")
- Test: verificação manual (controller), `UserRepository::count()` já existe.

- [ ] **Step 1: Aplicar o diff com os textos genéricos.**
- [ ] **Step 2: Verificar**: `docker compose down -v && docker compose up -d --build`, instalar até o passo 4 **sem** usuários → o botão "Concluir sem criar administrador" não aparece e `POST /install/admin/skip` reexibe o passo com "Nenhum usuario cadastrado. Crie o administrador."; inserir um usuário pelo `mysql` e recarregar → botão aparece e conclui em `/install/done`.
- [ ] **Step 3: Commit** `feat(instalador): tornar opcional o administrador quando ja ha usuarios`.

### Task 7: CLAUDE.md e verificação de ponta a ponta

**Files:**
- Modify: `CLAUDE.md`

Mudanças:
- Comandos: "PHP 8.2 + Apache + MySQL 8"; lint com `php:8.2-cli`.
- "Coding constraints": alvo PHP 8.2; remover a lista de sintaxe proibida; "Código novo pode usar typed properties, `match`, `readonly`, `str_contains`, arrow functions etc. Não refatore código antigo só para modernizar."
- Architecture → `App\Core`: acrescentar `Csrf`, `Logger`, `ErrorHandler`, `Model`/`QueryBuilder`/`Relations`; `App\Http\SecurityHeaders`; `App\Helpers` (por ora só `LogPath`).
- Nova regra: "QueryBuilder/Model para consultas simples; queries complexas (vários joins, agregações, subqueries) usam SQL puro via `App\Database\Database`."
- Nova regra: "Todo POST fora do instalador leva `VerifyCsrfToken`; todo `<form method="post">` leva `Csrf::field()` (garantido por `scripts/tests/csrf_forms_test.php`); `fetch` manda `X-CSRF-Token`."
- Log: `storage/logs/app-YYYY-MM-DD.log`, `LOG_LEVEL`, `LOG_RETENTION_DAYS`, `request_id` na 500.
- SMTP: `SMTP_*` no `.env` tem precedência sobre a tela de Configurações.
- Nova seção "Regras de código e commits":
  - **Proibido acento em código**: classes, métodos, funções, variáveis, constantes, propriedades, chaves de array, rotas, nomes de arquivo e de tabela/coluna são ASCII. Acento só em texto exibido ao usuário, mensagens, comentários de documentação e `docs/`. Prefira nomes em inglês.
  - Commits atômicos (um por ação média/grande, sem commits supérfluos) e semânticos com o verbo no infinitivo: `feat(escopo): adicionar ...`, `fix(escopo): corrigir ...`, `docs: documentar ...`.

**Test:** `scripts/tests/ascii_identifiers_test.php` (novo)

- [ ] **Step 1: Teste** `it('nenhum identificador de codigo tem acento')`: percorre `app/`, `scripts/`, `public/`, `routes.php` (exceto `lib/` e `app/Views/`), roda `token_get_all()` em cada `.php` e junta `arquivo:linha:token` de todo token `T_STRING`, `T_VARIABLE` ou `T_NAME_QUALIFIED` que case com `/[^\x00-\x7F]/`; `assertSame([], $found)`. Registrar em `scripts/test_install.php`. Rodar: se falhar, renomear os identificadores apontados (e seus usos) antes de seguir.
- [ ] **Step 1b: Editar o CLAUDE.md.**
- [ ] **Step 2: Suíte como `www-data`, sozinha.** Esperado: verde.
- [ ] **Step 3: Ponta a ponta com `curl -c/-b jar`** (instalação limpa, `docker compose down -v`): instalar; login; criar, editar e excluir usuário; salvar configurações; "esqueci a senha" (sem SMTP: e-mail não sai e a linha aparece no log); `/admin/atualizar` (polling com `X-CSRF-Token` responde 200); `POST /admin/login` sem `_csrf` → `403`.
- [ ] **Step 4: Commit** `docs(claude): documentar PHP 8.2, CSRF, log, seguranca, QueryBuilder e regras de codigo`.
- [ ] **Step 5: Marcar os itens da Fase 1 em `docs/superpowers/specs/2026-11-27-atualizacao-do-boilerplate-roteiro.md` e commitar junto.**
