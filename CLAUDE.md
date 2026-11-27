# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

`prb_admin_php` is a **boilerplate**, not a product: a hand-rolled PHP MVC core extracted from two sibling projects (`blog`, paulorb.dev, and `mydaily`, a multi-tenant daily-standup SaaS) that both target the same deploy constraint — shared, FTP-only hosting with no SSH, no Composer, no Node. It ships a working `/admin` panel (login, user CRUD, settings) and a visual `/install` wizard, and exists so a new PHP-puro project can start from a proven core instead of re-deriving install flow, routing, auth and migrations from scratch. When this repo is copied to bootstrap a new project, update this file's "What this project is" section to describe *that* project — the rest (Architecture, Coding constraints, gotchas) stays valid as inherited documentation, the same way `mydaily`'s own CLAUDE.md credits `blog` as its boilerplate.

There is no product-specific domain logic here on purpose. `App\Controllers`/`App\Repositories` beyond `User`/`Setting` are meant to be added per-project; `App\Core` is the part meant to stay stable across projects unless you're deliberately improving the boilerplate itself.

## Commands

```bash
# Dev environment (PHP 8.2 + Apache + MySQL 8, Docker)
docker compose up -d --build       # build + start; first run lands on /install
docker compose logs -f app         # tail app logs
docker compose down                # stop, keep data
docker compose down -v             # stop, wipe DB volume

# Inside the app container
docker compose exec app php scripts/migrate.php      # run pending migrations (CLI, outside /install)
docker compose exec app php scripts/test_install.php # run the test suite (see below)

# No Docker
php -S localhost:8000 -t public public/dev-router.php
```

App runs at `localhost:8002`, MySQL at `localhost:3308` in this boilerplate — pick different host ports per project cloned from it, so more than one can run at once without a port clash (see how `blog`/8000/3306 and `mydaily`/8001/3307 already coexist).

### Tests

There's no Composer/PHPUnit — `scripts/test_install.php` is a self-contained runner (defines `it()`/`assertSame()`/etc., no external deps) that requires each file in `scripts/tests/`.

```bash
docker compose exec app php scripts/test_install.php
```

Two things to know before trusting a run:
- **Writable-permission tests** (`Requirements`, `EnvWriter`) need to run as a non-root user, or `chmod 0555` has no effect and those assertions pass vacuously: `docker compose exec --user www-data app php scripts/test_install.php`.
- **DB-backed tests** (`Migrator`, `UserRepository`, etc.) run against `TEST_DB_DATABASE` (default `app_test`) — `testDatabase()` in `scripts/test_install.php` refuses to run against `app`/`db`/the configured `DB_DATABASE` as a safety check. Create the DB once and grant the app's MySQL user access to it (the default `user`/`password` account from `docker-compose.yml` only has privileges on `app` by default):
  ```bash
  docker compose exec db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS app_test; GRANT ALL PRIVILEGES ON app_test.* TO 'user'@'%'; FLUSH PRIVILEGES;"
  ```
- **Never run the suite concurrently against the same `TEST_DB_DATABASE`.** Every fixture's `testDatabase()` call does `DROP TABLE IF EXISTS` + `CREATE TABLE` for whatever that test file needs — two `scripts/test_install.php` processes running at once race on the same schema and produce failures that look real but vanish on the next solo run. If a run reports failures, re-run once, alone, before trusting the result.

Controllers are thin HTTP glue over well-tested repositories and generally aren't unit-tested themselves — verify them end-to-end with `curl` (cookie jar for session state) against a running `docker compose` stack instead. There's no lint script configured; `php -l` per file is the practical syntax check (see the PHP 8.2 constraint below).

## Architecture

Hand-rolled MVC, zero third-party runtime dependencies, zero build step — deliberately, because the deploy target is FTP-only shared hosting with no SSH/Composer/Node. Everything under `App\` autoloads via a tiny PSR-4-ish autoloader (`App\Core\Autoloader`), not Composer.

**Exception:** `lib/PHPMailer/` (PHPMailer v7.1.1 — `Exception.php`, `PHPMailer.php`, `SMTP.php`) is vendored manually, not via Composer, not autoloaded by `App\Core\Autoloader` (its `PHPMailer\PHPMailer\*` namespace is outside the `App\` prefix the autoloader resolves — only `App\Mail\SmtpTransport` uses it, with an explicit `require_once` of the 3 files, PHPMailer's own documented no-Composer usage). This is a deliberate exception to "zero third-party dependencies," decided with the project owner: native `mail()` can't do authenticated SMTP relay, which the password-reset e-mail flow needs. Updating PHPMailer means re-downloading the 3 files by hand from a specific tag — there's no `composer update` here. The second vendored library is **FPDF 1.86** in `lib/fpdf/` (origin and SHA-256 in `lib/fpdf/README.md`), used only by `App\Helpers\Export\Pdf\PdfDocument`. `lib/` is denied to the web (`lib/.htaccess` + the root `.htaccess`).

**Request flow:** `public/index.php` is the single entry point (document root is `public/`, but the root `.htaccess`/`index.php` shim covers hosts that serve from the project root instead — it just `require`s `public/index.php`). It: registers `ErrorHandler` → loads `.env` (and configures `Logger` from it) → starts the session → sends `SecurityHeaders` → checks `App\Core\InstallState::isInstalled()` and hands off to `App\Core\InstallRoutes` if not → builds an `App\Core\Router` and registers every route by `require`-ing `routes.php` (a plain script at the project root that returns a `function (Router $router): void` — no route files/attributes beyond this single one, no auto-discovery) → dispatches. There's no DI container; controllers are instantiated fresh per request with no constructor arguments.

**Layers:**
- `App\Controllers` — one class per feature area, HTTP-flow only: read the request through a `FormRequest`, call a Service, pick the response. See `AdminUserController` as the reference.
- `App\Http\Requests` — one `FormRequest` per form/endpoint (format rules only). See "Validation" below.
- `App\Services\<Area>\<Name>Service` — business rules, transactions (`Database::transaction()`, re-entrant), orchestration of repositories and e-mail. Return an `App\Services\ServiceResult` (`ok($data)` / `fail('snake_case_code', 'mensagem')`). Dependencies are optional constructor args (`?UserRepository $users = null`) so tests inject them; the acting user comes in as a parameter (`delete($uuid, $actor)`).
- `App\Validation` — `Validator`, `ErrorBag`, `ValidationException` (usable outside HTTP too).
- `App\Mail` — `Mailable`, `Mailer`, `Message`, transports (`SmtpTransport`, `LogTransport`, `ArrayTransport`) and `SmtpConfig`. See "E-mail".
- `App\Storage` — `Disk`/`LocalDisk`, `StoragePath`, `StoredFile`, `MimeTypes`; plus `App\Services\Storage\UploadService` and `App\Http\FileResponse`. See "Files".
- `App\Repositories` — thin query wrappers around `App\Database\Database` (a PDO wrapper: `fetch`/`fetchAll`/`execute`). Repositories return plain arrays; for simple lookups they may use the `Model`/`QueryBuilder` layer below, and complex queries are written as plain SQL. Each repository takes an optional `Database` in its constructor (`?Database $database = null`) so tests can inject an isolated connection.
- `App\Core` — the framework-shaped pieces: `Router` (with a middleware pipeline, see below), `Auth`, `Csrf`, `Env`/`EnvWriter` (hand-rolled `.env` parser/writer), `View` (plain `require` of a PHP template, no template engine), `Migrator`, `InstallState`, `Requirements`, `Logger`/`ErrorHandler` (see Errors & logging), `ErrorPage` (HTML or JSON depending on the request), `Icon`. Generic utilities don't belong here — they go to `App\Helpers`.
- `App\Core\Model` + `QueryBuilder` + `Relations\*` (`HasOne`, `HasMany`, `BelongsTo`, `BelongsToMany`) — a small active-record layer for **simple** queries (find by key, filtered lists, simple relations); see `App\Models\User` and `scripts/tests/model_test.php`. **Complex queries (several joins, aggregations, subqueries, reports) use plain SQL through `App\Database\Database`** inside a repository — don't stretch the QueryBuilder to cover them.
- `App\Http` — pieces that know about the HTTP request but not about business rules: `SecurityHeaders`, `FormRequest`, `ValidationResponder`, `Form`, `Flash`, `JsonResponse`, `ListQuery`.
- `App\Helpers` — pure utilities: no request, no business rules, no state. See "Helpers: what already exists" below before writing text, date, number or input handling by hand.
- `App\Core\Middleware` — `MiddlewareInterface::handle(array $routeParams): ?string` (`null` = continue, a string = redirect to that Location, `Router::HALT` = response already sent, e.g. by `ErrorPage::forbidden()` — stop, don't redirect). `Router::get/post($path, $handler, $middleware = [])` takes an array of middleware — either a class name (instantiated with no constructor args) or an already-built instance for middleware that needs a parameter (follow `RequireRole::organizer()`-style static factories if you add one). `Router::resolve()` does the matching + runs the middleware chain with no side effects (no `header()`, no instantiating the controller) and returns a result array; `Router::dispatch()` acts on that result. This separation is what makes the middleware chain unit-testable — see `scripts/tests/router_middleware_test.php`. Only `/admin/*` routes use `RequireAuth::class` today; add more middlewares under `App\Core\Middleware` the same way (e.g. a `RequireRole` or a tenant resolver, if the project you're building on this needs one).
- `App\Views` — plain PHP templates. The install wizard uses the `ob_start()` → build `$content` → `require '.../layout.php'` pattern. Admin pages `require partials/shell-top.php` then `partials/shell-bottom.php` around inline markup — see any `app/Views/admin/*.php`. Public pages do the same with `partials/site-top.php`/`site-bottom.php`.

Who may do what:

| Layer | May | May not |
|---|---|---|
| Controller | read the request via a `FormRequest`, call a Service, choose the response (view, redirect, JSON), `Flash` | SQL, business rules, `new Database()`, private validation methods |
| FormRequest | **format** rules (`required`, `email`, `cpf`, `unique`...) and input normalization (`prepare()`/`transform()`) | business rules (who may, state, limits) |
| Service | business rules, transactions, orchestrate repositories and e-mail, return `ServiceResult` | superglobals, `header()`, `echo`, session |
| Repository | SQL (QueryBuilder for simple, plain SQL for complex) | rules, session |
| View | display, escaping with `e()` | queries, rules |

**Install & migrations:** no seeded account anywhere in the code. First request when `.env` lacks `APP_INSTALLED=true` is captured entirely by `InstallRoutes`/`InstallController`, a 5-step wizard gated by `.env.install`'s `INSTALL_KEY` (session flags gate each step server-side) that: checks `Requirements`, writes `.env` via `EnvWriter`, runs `database/migrations/*.sql` one at a time through `Migrator` (polled from JS, each migration's result individually visible), and creates the first user. Post-launch schema changes ship as new numbered `database/migrations/NNN_*.sql` files; `AdminUpdateController` (`/admin/atualizar`, requires login) re-runs the same `Migrator::runNextPending()` polling loop from inside the app — the mechanism that applies a migration in production after an FTP upload, since there's no CLI access there.

**CSRF:** every POST route outside the installer gets `VerifyCsrfToken::class` in its middleware list, and every `<form method="post">` includes `<?= \App\Core\Csrf::field() ?>` (enforced by `scripts/tests/csrf_forms_test.php`). `fetch` calls send the token in the `X-CSRF-Token` header (`Csrf::token()`; see `migrate-progress.js`). The installer routes have no CSRF on purpose — they're gated by the `INSTALL_KEY`.

**Errors & logging:** `ErrorHandler::register()` runs first in `public/index.php` (and in `scripts/migrate.php`): uncaught exceptions, PHP warnings and fatals go to `storage/logs/app-YYYY-MM-DD.log` (UTC date) through `Logger`, which redacts sensitive keys (`password`, `token`, `secret`, `csrf`, ...) at any depth and masks tokens in URL paths (`LogPath::mask()` → `:token`). The 500 page shows the request's `request_id` (same id as the log line); JSON requests get `{"error": "server_error", "request_id": "..."}`. Details only reach the screen with `APP_DEBUG=true`. Tune with `LOG_LEVEL` (`debug`…`critical`; default `debug` with `APP_DEBUG=true`, else `warning`) and `LOG_RETENTION_DAYS` (default 14).

**Security headers:** `SecurityHeaders::send()` adds a CSP without script rules (`object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'`), `Permissions-Policy`, COOP and HSTS (HTTPS only); `X-Frame-Options`, `nosniff` and `Referrer-Policy` come from both `.htaccess` files so static files get them too. The root `.htaccess` also refuses `app/`, `database/`, `scripts/`, `storage/`, `lib/`, `docs/`, `docker/`, `routes.php`, hidden files and `*.md|yml|yaml|sql|sh|lock|example|docker` for hosts that serve the project root.

**E-mail:** one class per e-mail type, Laravel-Mailable style, sent through `App\Mail\Mailer`:

```php
final class InvoiceMail extends Mailable
{
    public function __construct(private readonly array $invoice) {}
    public function subject(): string { return 'Sua fatura ' . $this->invoice['code']; }
    protected function view(): string { return 'invoice'; }                    // app/Views/emails/invoice.php
    protected function data(): array { return ['invoice' => $this->invoice]; }
    protected function attachments(): array { return [['content' => $pdf, 'name' => 'fatura.pdf', 'mime' => 'application/pdf']]; }
}

Mailer::send((new InvoiceMail($invoice))->to($client['email'], $client['name']));   // bool, never throws
```

- The template is rendered inside `app/Views/emails/_layout.php` (inline CSS, tables — e-mail clients ignore external CSS) and the plain-text part is generated from it (`Mailable::textFromHtml()`, links become `text (url)`) unless `text()` is overridden. Escape with `e()` in templates.
- `Message` validates addresses and strips `\r`/`\n` from subject and names (header injection).
- Transport: `MAIL_TRANSPORT=smtp|log` in `.env`; empty = `smtp` if a host is configured, else `log` (the e-mail goes to `storage/logs` instead of vanishing; outside `APP_DEBUG` 40+-char tokens in the body are masked so log readers can't use reset links). Failures return `false` and log a warning. Tests: `$fake = Mailer::fake(); ... $fake->last()->subject` and `Mailer::reset()`.
- SMTP settings come from the admin settings page with `SMTP_*` in `.env` taking precedence field by field (`App\Mail\SmtpConfig`; the page tags those fields `.env`). `SMTP_PASSWORD` only applies together with `SMTP_HOST`, and with it the port and encryption also come from `.env` (default `587`/`tls`, never unencrypted) — otherwise anyone who can edit the settings page could point the host elsewhere and receive the `.env` password.

**Auth:** `App\Core\Auth` is a static wrapper over `$_SESSION['user']` (`id`, `uuid`, `name`, `email`, `is_admin`). This boilerplate ships a single flat role (any logged-in user can reach `/admin/*`) — `UserRepository::verifyPassword()` also transparently upgrades legacy 32-char MD5 hashes to `password_hash()` on successful login, useful if `users` ever gets seeded/imported data from elsewhere. If the project built on this needs real roles or multi-tenancy, follow the pattern `mydaily` used on top of the same boilerplate: a `Tenant` static registry (same shape as `Auth`) set only by a `ResolveTenant` middleware, tenant-scoped repositories taking the tenant id as a **required, non-nullable constructor argument**, and a `RequireRole` middleware built the same way as `RequireAuth`.

**Public identifiers:** internal `id INT AUTO_INCREMENT` shouldn't leave the data layer — avoid it in URLs, forms, or responses for anything sensitive. `App\Helpers\Uuid::v4()` is there for tables that need a public identifier; `App\Helpers\Text::slugify()` (transliterates accents rather than stripping them) is there for human-readable slugs when a table wants nicer URLs instead of a uuid.

## Helpers: what already exists

Before writing text, date, number or input handling by hand, look here. `App\Helpers` is pure (no request, no business rules, no state). Simple helpers have **no unit tests by the owner's decision** — the code that uses them (validation rules, services) is tested at its own level.

| Class | What for | Example |
|---|---|---|
| `Str` | UTF-8-safe text, Laravel-style (`after`, `before`, `between`, `contains`, `limit`, `words`, `squish`, `ascii`, `slug`, `camel`, `snake`, `kebab`, `mask`, `digits`, `alnum`, `random`...) | `Str::limit($title, 60)` |
| `Arr` | Nested arrays with dot keys, and lists of DB rows (`get`, `set`, `has`, `only`, `except`, `pluck`, `keyBy`, `groupBy`, `sortBy`, `sum`...) | `Arr::pluck($users, 'name', 'uuid')` |
| `Number` | Brazilian number format without `intl` (`format`, `percentage`, `fileSize`, `abbreviate`, `parse`, `clamp`) | `Number::format($kg, 3) . ' kg'` → `1.234,567 kg` |
| `Text` | Public slug (transliterates accents), truncation, plain-text excerpt of HTML | `Text::slugify('Ação')` → `acao` |
| `Mask` | Pattern masks (`Mask::CPF`, `CNPJ`, `CEP`, `PHONE`, `MOBILE` or `'##.###'`); letters pass, for the alphanumeric CNPJ | `Mask::apply($digits, Mask::CEP)` |
| `Lgpd` | Hide personal data **on display only** (`cpf`, `cnpj`, `email`, `phone`, `name`, `cep`, `ip`, `hide`) | `Lgpd::email('ana@x.com')` |
| `Br\Cpf`, `Br\Cnpj`, `Br\Cep`, `Br\Phone` | `clean`, `isValid`, `format` (+ `Cnpj::isAlphanumeric`, `checkDigits`; `Phone::isMobile`) | `Cnpj::isValid('12.ABC.345/01DE-35')` |
| `Br\Date` | `dd/mm/aaaa` ↔ `aaaa-mm-dd`; impossible dates → `null` | `Br\Date::parse('24/11/2026')` |
| `DateOnly` | Calendar dates (`Y-m-d`) without timezone: `normalize`, `shift`, `daysBetween` | `DateOnly::shift($day, 7)` |
| `DateFormat` | Portuguese dates: `full` (`24 de novembro de 2026`), `long` (`terça-feira, 24 de nov de 2026`), `numeric` (`24/11/2026`), `short`, `shortRange` | `DateFormat::full($date)` |
| `LocalTime` | App local time ↔ UTC of the DB (`isClockTime`, `toUtc`, `fromUtc`) | `LocalTime::toUtc('2026-11-24 09:00')` |
| `Duration` | Minutes → label | `Duration::label(90)` → `1h30` |
| `MoneyFormat` | Cents → money text (BRL default, USD) | `MoneyFormat::format(123456)` → `R$ 1.234,56` |
| `Input` | Normalize posted values: `string`, `nullableString`, `nullableInt`, `accessEmail`, `isDigitsOrBlank`, `time` (→ `HH:MM:SS` or `null`), `duration`, `money` (→ cents), `cents`, `decimal` (BR text → decimal string), `decimalFromNumberField` (`<input type="number">`, dot decimal), `date` (BR → `Y-m-d`) | `Input::money('1.234,56')` → `123456`; `Input::decimal('1.234,567', 3)` → `'1234.567'` |
| `HtmlSanitizer` | Allowlist cleanup of rich-editor HTML before saving; `isSafeLink` | `HtmlSanitizer::clean($html)` |
| `Color` | Only `#rgb`/`#rrggbb` from the DB reaches a `style=` | `Color::hexOr($color, '#6c757d')` |
| `Url` | Absolute URL from `APP_URL` (e-mails) | `Url::absolute('/admin/login')` |
| `Uuid` | UUID v4 from `random_bytes()` (no `uuid` extension) — public identifier instead of the auto-increment id | `Uuid::v4()` |
| `Asset` | Cache-busted asset URL (`?v=filemtime`) | `Asset::url('css/app.css')` |
| `LogPath` | Mask tokens in a URL path before logging | `LogPath::mask($path)` |
| `e()` | Global HTML escape for views (`app/functions.php`); `null` → `''` | `<?= e($user['name']) ?>` |

Rules:
- **Money** is stored as integer cents (`Input::money` in, `MoneyFormat` out). **Decimals and weights** travel as decimal strings (`Input::decimal`, `DECIMAL` column). Never `float` on the way to the database.
- **`Input::decimal` reads Brazilian text** (`1.234` = one thousand two hundred thirty-four). A `<input type="number">` always posts a dot decimal (`1.234` = 1,234), so read those with `Input::decimalFromNumberField` — mixing them up silently multiplies by 1000. Prefer masked text inputs (BR format) for money, weight and decimals.
- **CNPJ is alphanumeric text** (IN RFB 2.229/2024: 12 `A-Z0-9` + 2 check digits) — never store it as a number.
- **Every new view escapes with `e()`.** Replace existing `htmlspecialchars(...)` calls with `e()` only when touching that view for another reason.

## Validation (FormRequest)

A form is validated by a `FormRequest` in `app/Http/Requests/`; the controller doesn't write `if ($error) { render... }`:

```php
final class StoreClientRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'cnpj' => 'required|cnpj|unique:clients,cnpj',
            'born_on' => 'nullable|date_br',
            'credit' => 'nullable|money',
        ];
    }

    public function attributes(): array { return ['name' => 'nome', 'born_on' => 'data de nascimento']; }
    public function redirectTo(): string { return '/admin/clients/create'; }

    // After validation: convert BR input to the DB format.
    protected function transform(array $data): array
    {
        $data['born_on'] = Br\Date::parse($data['born_on'] ?? null);
        $data['credit'] = isset($data['credit']) ? Input::money($data['credit']) : null;

        return $data;
    }
}

// Controller
$data = StoreClientRequest::validate();          // invalid → back to the form with errors + old input
$result = $this->clients->create($data);         // Service
```

- On failure it throws `ValidationException`, handled by `Router::dispatch()` (`ValidationResponder`): JSON requests get **422** `{"message": "...", "errors": {"field": ["..."]}}`; forms get the errors and the typed input (never `password*` or files) in the session for **one** request and a 303 back to the `Referer` — **only if it's the same host**, else `redirectTo()`.
- A request that needs route context takes it in the constructor and is used as an instance: `(new UpdateUserRequest($uuid))->validated()` (e.g. `unique:users,email,<uuid>,uuid` to ignore the record itself — ignore by public uuid, not internal id).
- A business-rule refusal on a field (Service returned `email_taken`): `throw ValidationException::withMessages(['email' => $result->message()], $data, '/form')`.
- **Rules:** `required`, `nullable`, `string`, `email`, `min`, `max`, `between` (text counts characters; with `numeric`/`integer` compares the value; files compare KB), `in`, `numeric`, `integer`, `regex` (use the array form if the regex has `|`), `confirmed` (`<field>_confirmation`), `same`, `date` (`Y-m-d`), `before`/`after` (a `Y-m-d` literal, or another field **that has rules**), `unique:table,column[,ignore[,idColumn]]`, `exists:table,column`, `file`, `mimes:jpg,png,pdf` (type detected **by content**, `finfo`), `max_size:kb`. Brazilian: `cpf`, `cnpj` (alphanumeric), `cep`, `phone`, `date_br`, `money`, `decimal_br[:scale]`, `weight` (scale 3). Messages in pt-BR with `:attribute`; override per `'field.rule'` in `messages()`.
- A new rule is a `protected function ruleName(mixed $value, array $params, string $field): bool` + its message in `Validator::MESSAGES`, or `Validator::extend('name', fn ($value, $params, $data) => ..., 'Mensagem :attribute.')` in the project.
- `unique`/`exists` table and column names come from code and are checked against `^[a-z_][a-z0-9_]*$`. Files only pass if they came from an HTTP upload (`is_uploaded_file`), so a JSON body imitating `$_FILES` can't point at a server file.

**In the views** (`app/functions.php`, `App\Http\Form`): `old('field', $default)`, `errors()->first('field')`, `Form::invalid('field')` (` is-invalid` on the input), `Form::feedback('field')` (`<div class="invalid-feedback">`), the summary partial `admin/partials/form-errors.php` and `Flash::success()`/`error()` (shown by `shell-top.php`). Validation state lives one request (`flash_validation_state()` in `public/index.php`).

**New-form checklist:** summary at the top (`form-errors.php`) **and** each field red with its message below (`Form::invalid` + `Form::feedback`); `<span class="text-danger">*</span>` on required labels; `value="<?= e(old('field', $current)) ?>"`; `Csrf::field()`; `novalidate` on the `<form>` so the server messages are the ones shown; masks and date picker on BR fields (phase 6). Existing forms may keep only the summary.

## Listing (pagination)

Every list uses the same query parameters: `page`, `per_page` (`10|25|50|100`, default 25), `sort` (only keys of the sortable map), `dir` (`asc|desc`), `q` (search, ≤ 100 chars) and `partial=1` (only the table fragment). Invalid values silently fall back to the default.

```php
// Repository
public const SORTABLE = ['name' => 'name', 'created' => 'created_at'];   // URL key => SQL expression

public function paginate(ListQuery $list): Page
{
    $sql = 'SELECT uuid, name, created_at FROM clients';
    $params = [];
    if ($list->search() !== '') {
        $sql .= ' WHERE name LIKE :q_name OR cnpj LIKE :q_cnpj';           // one placeholder per occurrence
        $params = ['q_name' => '%' . $list->search() . '%', 'q_cnpj' => '%' . $list->search() . '%'];
    }
    return Paginator::sql($this->database, $sql, $params, $list, 'id ASC');  // or Model::query()->...->paginate($list)
}

// Controller
$list = ListQuery::fromRequest(ClientRepository::SORTABLE, 'name');
$data = ['page' => $this->clients->list($list), 'list' => $list];
$list->isPartial() ? View::render('admin/partials/clients-table', $data) : View::render('admin/clients', $data + ...);
```

- `Page` has `items`, `total`, `page`, `perPage`, `pages`, `from`, `to`, `window()` and `meta()` (for JSON). A page past the last one shows the last one.
- `ORDER BY` only ever comes from the sortable map; `LIMIT`/`OFFSET` are integers built into the SQL string.
- Views: `$list->sortLink('Nome', 'name')` for sortable `<th>`, `admin/partials/pagination.php`, `admin/partials/page-size.php`, and `public/assets/js/live-grid.js` (wrap in `data-live-grid` with a GET `form[data-grid-filters]` and `[data-grid-results]`; without JS the page still works). See `app/Views/admin/users.php`.
- `public/assets/js/admin-table.js` (everything client-side) is still there for **small** lists (dozens of rows); anything that can grow uses `ListQuery`.

## Files (upload & download)

```php
$result = (new UploadService())->store($_FILES['contract'] ?? null, 'contracts', 5 * 1024 * 1024, MimeTypes::DOCUMENTS);
// ok: ['path' => 'contracts/2026/11/<uuid>.pdf', 'disk' => 'local', 'mime', 'size', 'original_name']  → save in the row
// fail: missing_file | upload_failed | file_too_large | invalid_type | could_not_save

FileResponse::send(new StoredFile(new LocalDisk($root . '/storage/uploads'), $row['path'], $row['mime'], $row['original_name'], inline: true));
```

- The type is **always** detected from the content (`finfo`, `App\Storage\MimeTypes`) against the caller's allowlist (`MimeTypes::IMAGES`, `DOCUMENTS`, `SPREADSHEETS`, or your own `mime => ext` map); the browser's `type` and the file name's extension are never trusted. Size is the real file size. The stored name is `<uuid>.<ext>` under `storage/uploads/<folder>/<YYYY>/<MM>/` (outside the web, `storage/uploads/.htaccess` denies all); the cleaned original name (`FileName::clean`) is only for display/download. `move_uploaded_file` guarantees the file came from this request's upload.
- `FileResponse` is the **only** way a stored file reaches the browser: `nosniff`, CSP `sandbox`, RFC 6266 `Content-Disposition` (`ContentDisposition::header`), and `inline` only for raster images and PDF — SVG/HTML always download. Check permission before calling it.
- Validation side: `'contract' => 'required|file|mimes:pdf|max_size:5120'` (same `MimeTypes` map). Multiple files in one field: `UploadedFiles::fromField($_FILES['docs'])`.
- `UploadService::removeStaleStaging(time() - 3600)` cleans interrupted uploads (cron).

## Export (CSV, PDF, Excel)

`App\Helpers\Export\{CsvExport, PdfExport, XlsxExport}` share one base (`Export`): header + rows in, file out (`->download('name')` or `->toString()`). The Service builds the rows (business decision), the controller only hands the file over. Rows must match the header length (else `InvalidArgumentException`), and **formula protection is always on** in CSV and XLSX (text starting with `= + - @`, tab or CR gets a `'`). `XlsxExport` has typed columns (`->types([2 => 'money', 3 => 'date'])`) and needs the `zip` extension. Full guide: `docs/exportacao.md`.

## Coding constraints

The production hosting target is **PHP 8.2** (Apache + MySQL). New code may use typed properties, constructor promotion, `match`, `readonly`, enums, nullsafe `?->`, arrow functions, `str_contains`/`str_starts_with` etc. Don't refactor existing code just to modernize it — change old code only when it's being touched for another reason or when it raises an error/deprecation on 8.2. Syntax check:

```bash
docker run --rm -v "$(pwd):/app" -w /app php:8.2-cli php -l <file>
```

**No accents in code, ever.** Class, method, function, variable, constant, property, array key, route, file, table and column names are plain ASCII (enforced for PHP identifiers by `scripts/tests/ascii_identifiers_test.php`). Accents belong only in text shown to the user, messages and documentation. Prefer English names for new code.

**Commits** are atomic — one per medium/large unit of work, no tiny or superfluous commits — and semantic, with the verb in the Portuguese infinitive: `feat(escopo): adicionar ...`, `fix(escopo): corrigir ...`, `docs: documentar ...`, `chore(escopo): subir ...`.

Follow PSR-12, 4-space indentation, namespaces matching path (`App\Controllers\UserController` → `app/Controllers/UserController.php`). `PascalCase` classes, `camelCase` methods/variables, `snake_case` DB tables/columns.

Routes are hand-registered in `routes.php` (project root, required by `public/index.php`), resolved through `App\Core\Router` — no filenames or query-string IDs in URLs, and route params meant to be public should be public identifiers (`{userUuid}`, `{slug}`), never internal ids.

## Gotchas already discovered on top of this boilerplate

**Adding a column via `ALTER TABLE` migration:** if the migration backfills existing rows (e.g. `UPDATE teams SET x = created_by_user_id WHERE x IS NULL`), that backfill only covers rows that already existed — it does **not** make the corresponding `Repository::create()` populate the column for *new* rows going forward. Update the `INSERT` too, in the same commit as the migration. The same applies to `scripts/tests/*_test.php` fixtures — each test file declares its own inline `CREATE TABLE` (there's no shared schema loader), so a new column needs adding to every fixture that creates that table, not just the real migration, or those tests silently run against a stale schema (`SELECT *` on a missing column just omits the key rather than erroring in PHP).

**MySQL `UNIQUE KEY` + `NULL` + generated columns don't compose the way you'd expect:** MySQL treats `NULL` as distinct in every row of a `UNIQUE KEY`, so a dedupe key over nullable columns does **not** stop two rows that are both `NULL` in that column from being inserted twice. The "obvious" fix (`STORED` generated shadow columns coalescing `NULL` to a sentinel) runs into a second wall: MySQL refuses `ON DELETE CASCADE` on any column a `STORED` generated column depends on (`errno 1215`). Don't reach for a DB-level uniqueness constraint over nullable columns without checking this first; verify any such migration by actually running it, not just by reading the SQL.

**`GROUP_CONCAT` gotcha:** MySQL does not guarantee row order inside `GROUP_CONCAT()` unless you give it an explicit `ORDER BY` (`GROUP_CONCAT(body ORDER BY position SEPARATOR ' ')`) — without it, concatenation order is whatever the storage engine happens to return, which silently scrambles anything relying on sequence. Caught by a test, not by inspection — write one whenever a query concatenates ordered child rows.

**Timezone gotcha (any `expires_at`/absolute-instant column):** `docker/php/php.ini` sets a local `date.timezone` for display purposes, but the MySQL container's `NOW()` is UTC. `date('Y-m-d H:i:s', $ts)` in PHP therefore produces a string offset from what MySQL considers "now" — a token computed as "expires in 30 min" can come out already in the past. Always generate these with `gmdate()`, and compare in SQL against `UTC_TIMESTAMP()` (or, in PHP, construct `DateTime` objects with an explicit `new DateTimeZone('UTC')`), never bare `NOW()`/`date()`. Also use `DATETIME`, not `TIMESTAMP`, for any such column — `TIMESTAMP` maxes out in 2038.

**PDO native-prepare gotchas (`PDO::ATTR_EMULATE_PREPARES => false` in `App\Database\Database`):** two things that work fine with emulated prepares silently misbehave here. (1) The same named placeholder can't be reused twice in one query (`WHERE name LIKE :q OR slug LIKE :q` errors/misbinds) — give each occurrence its own name bound to the same value (`:query_name`, `:query_slug`, ...). (2) `LIMIT`/`OFFSET` bound as query parameters are unreliable across MySQL versions with native prepares — build them into the SQL string directly instead, which is safe as long as the values are already typed `int` in the calling method's signature.

Never commit real `.env`/`.env.install`, DB dumps, or `vendor/`/`node_modules/` (none currently exist, but the `.gitignore` already excludes them if introduced). `.env.docker` (dev-only, disposable credentials) is the one `.env*` file meant to be committed — see `.gitignore`.
