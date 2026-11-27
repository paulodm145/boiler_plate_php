# Fase 1 — Base e correções (design)

Data: 2026-11-27. Roteiro: `2026-11-27-atualizacao-do-boilerplate-roteiro.md`.

## Intenção

Deixar o boilerplate **funcionando e em PHP 8.2**, com as correções estruturais que o lorencine e o mydaily precisaram fazer em cima dele, antes de qualquer peça nova. Hoje o boilerplate nem completa um login: o `routes.php` usa `App\Core\Middleware\VerifyCsrfToken`, mas as classes `Csrf` e `VerifyCsrfToken` nunca foram commitadas.

Sucesso quer dizer:
- `docker compose up -d --build` sobe em PHP 8.2;
- o `/install` completa;
- login, CRUD de usuário, configurações, recuperação de senha e `/admin/atualizar` funcionam de ponta a ponta;
- a suíte passa como `www-data`.

## Objetivos

1. PHP 8.2 no ambiente, nos requisitos e na documentação.
2. CSRF funcionando em todo POST fora do instalador, com um teste que impede regressão.
3. `.htaccess` da raiz que não serve nada fora de `public/` quando o document root é a raiz do projeto.
4. SMTP configurável pelo `.env`, com precedência sobre a tela de Configurações.
5. Log de erros próprio e página 500 com `request_id`.
6. Cabeçalhos de segurança básicos em toda resposta.
7. Instalador que permite concluir sem criar administrador quando o banco já tem usuários.

## Não-objetivos

- Reescrever o código existente para sintaxe 8.x. O código antigo continua como está, e só o código novo usa 8.2 livremente (mesma postura da D-26 do mydaily).
- CSP de scripts com nonce. O ponto fica em aberto e é decidido na fase 6, junto com os componentes JS.
- Helpers, Services, FormRequest e paginação, que são das fases 2 e 3. A exceção é o `LogPath`, que entra já agora porque o `ErrorHandler` depende dele.

## Anti-objetivos

- Quebrar o fluxo de instalação ou de atualização por FTP. `/admin/atualizar` precisa continuar aplicando migrations sem CLI.
- Deixar dado sensível no log: senhas, tokens e o token de reset na URL.

## Itens

### 1. PHP 8.2
- `docker/php/Dockerfile`: aplicar o diff do lorencine (`FROM php:8.2-apache`, retirar o bloco do archive.debian.org e usar a nova sintaxe do `gd --with-freetype --with-jpeg --with-webp`) e manter o comentário genérico (sem "Lorencini").
- `docker-compose.yml`: **sem mudança** de portas e nomes (8002/3308, `prb_admin_php_*`).
- `App\Core\Requirements::MINIMUM_PHP = '8.2.0'`, ajustando o teste de requisitos se ele fixar a versão.
- CLAUDE.md, seção "Coding constraints":
  - o alvo passa a ser PHP 8.2;
  - sai a lista de sintaxe proibida;
  - entra "o código novo pode usar typed properties, `match`, `readonly`, `str_contains` etc.; não refatorar código antigo só para modernizar";
  - o comando de checagem passa a ser `docker run --rm -v "$(pwd):/app" php:8.2-cli php -l <file>`.
- `php -l` em PHP 8.2 sobre todo `app/`, `scripts/`, `public/` e `routes.php`. Deprecations do 8.x que aparecerem na suíte são corrigidas.

### 2. CSRF
- Trazer `app/Core/Csrf.php` e `app/Core/Middleware/VerifyCsrfToken.php` como estão no lorencine. Os dois vieram do mydaily. O token aceita `_csrf` no POST ou o cabeçalho `X-CSRF-Token`.
- `app/Views/admin/settings.php`: incluir o `Csrf::field()` que está faltando (lorencine `3dfd27f`).
- `app/Views/admin/update.php` + `public/assets/js/migrate-progress.js`: enviar o token no cabeçalho `X-CSRF-Token`, como no lorencine `892f129`.
- Trazer `scripts/tests/csrf_forms_test.php` (todo `<form method="post">` fora de `Views/install/` precisa ter `Csrf::field()`) e registrá-lo em `scripts/test_install.php`.
- Teste do middleware: sem token dá `HALT`, token errado dá `HALT`, token certo por POST ou por cabeçalho dá `null`.
- As rotas do instalador continuam sem CSRF, porque são protegidas pela `INSTALL_KEY`.

### 3. `.htaccess` da raiz
Aplicar o diff do lorencine `faebe28`:
- negar `lib|docs|docker` além de `app|database|scripts|storage`;
- negar arquivos ocultos, exceto `/.well-known/`;
- negar `routes.php` e `*.md|yml|yaml|sql|sh|lock|example|docker`.

A verificação é manual, com `curl`, contra um Apache servindo da raiz, ou registrada como verificada só por leitura se não houver como montar esse cenário.

### 4. SMTP pelo `.env`
- `App\Core\Mail\SmtpMailer`: `ENV_KEYS`, `resolveConfig()` e `envOverriddenKeys()` como no lorencine `8665116`. `SMTP_*` tem precedência campo a campo, e a criptografia inválida volta para `tls`.
- `.env.example`: bloco `SMTP_*` comentado, com `SMTP_FROM_NAME` genérico.
- Tela de Configurações: aviso nos campos que estão vindo do `.env`.
- Trazer `scripts/tests/mailer_test.php`.
- A abstração Mailable fica para a fase 4. Aqui só muda de onde vem a configuração.

### 5. Logger e ErrorHandler
- Trazer `App\Core\Logger` e `App\Core\ErrorHandler` do mydaily, e `App\Helpers\LogPath`, que é dependência e cria a pasta `app/Helpers`.
  - log diário em `storage/logs/`;
  - chaves sensíveis ocultas (`password`, `token`, `secret`...);
  - tokens no caminho da URL viram `:token`;
  - limpeza dos arquivos antigos;
  - uma exceção não tratada vai para o log e mostra uma página 500 com o `request_id`;
  - os detalhes aparecem na tela só com `APP_DEBUG=true`.
- `public/index.php`: `ErrorHandler::register()`, `Logger::configureFromEnv()` e `ErrorHandler::showErrorsOnScreen()`, na mesma ordem do mydaily.
- Retirar do código trazido o que é específico do mydaily (contexto de tenant, impersonação). O provedor de contexto fica só com usuário, método e caminho.
- Variáveis novas do `.env` (nível e dias de retenção, conforme o `configureFromEnv` do mydaily) vão para `.env.example` e `.env.docker`.
- `storage/logs/` precisa estar no `.gitignore` e negada pela web (já está coberta por `storage/` no `.htaccess`).
- Trazer os testes do mydaily para `Logger` e `LogPath`, se existirem. Se não existirem, escrever testes para a ocultação de chaves sensíveis e para o `LogPath::mask`.

### 6. Cabeçalhos de segurança
- `App\Http\SecurityHeaders` (cria `app/Http`), adaptado do mydaily **sem a parte de nonce**:
  - `Content-Security-Policy: object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'`;
  - `Permissions-Policy`;
  - `Cross-Origin-Opener-Policy: same-origin`;
  - `Strict-Transport-Security: max-age=31536000` só em HTTPS.
- Teste de `headersFor()` (HSTS só em HTTPS).
- Conferir se `X-Frame-Options`, `nosniff` e `Referrer-Policy` já estão no `.htaccess`, e incluir os que faltarem.
- Conferir que o `form-action 'self'` não quebra nenhum formulário existente, já que todos postam no mesmo host.

### 7. Instalador sem administrador obrigatório
- Diff do lorencine `19bbfb8`: rota `POST /install/admin/skip`, `InstallController::skipAdmin()` (só quando `UserRepository::count() > 0`), botão no passo 4 e texto em `done.php`.
- Textos genéricos: "o banco já tem usuários", sem citar sistema legado.
- Conferir se `UserRepository::count()` existe. Se não existir, criar.

### 8. CLAUDE.md
- PHP 8.2 (item 1).
- Documentar o `App\Core\Model`, o `QueryBuilder` e as `Relations`, que existem e não estão descritos. Política: **o QueryBuilder serve para consultas simples; queries complexas (joins múltiplos, agregações, subqueries) usam SQL puro pelo `Database`**.
- `Csrf`/`VerifyCsrfToken`: todo POST fora do instalador leva o middleware, e todo form leva `Csrf::field()`.
- `Logger`/`ErrorHandler`, `SecurityHeaders` e o SMTP pelo `.env`.

## Ordem sugerida

CSRF (2) primeiro, porque destrava o login e, com ele, a verificação de ponta a ponta. Depois PHP 8.2 (1), por ser a mudança de ambiente que tudo o mais vai usar. Em seguida 5, 6, 4, 7, 3 e 8. Cada item é um commit.

## Verificação

- `docker compose exec --user www-data app php scripts/test_install.php` verde, rodado sozinho.
- `php -l` em PHP 8.2 sem erro em todos os arquivos.
- Com `curl` e um cookie jar:
  - instalação completa;
  - login;
  - criar, editar e excluir usuário;
  - salvar configurações;
  - "esqueci a senha" e redefinição;
  - `/admin/atualizar`;
  - POST sem token dá 403;
  - uma exceção forçada gera a página 500 com `request_id` e a linha no log.

## Decisões do Claude (confira ao ler)

- Sem CSP de nonce nesta fase, que fica para a fase 6.
- O `LogPath` entra antes da fase 2 por ser dependência.
- O código antigo não é modernizado. Só se corrige o que der erro ou deprecation no 8.2.
- Portas e nomes do Docker ficam como estão.
