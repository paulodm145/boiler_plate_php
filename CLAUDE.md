# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this project is

`prb_admin_php` is a **boilerplate**, not a product: a hand-rolled PHP MVC core extracted from two sibling projects (`blog`, paulorb.dev, and `mydaily`, a multi-tenant daily-standup SaaS) that both target the same deploy constraint — shared, FTP-only hosting with no SSH, no Composer, no Node. It ships a working `/admin` panel (login, user CRUD, settings) and a visual `/install` wizard, and exists so a new PHP-puro project can start from a proven core instead of re-deriving install flow, routing, auth and migrations from scratch. When this repo is copied to bootstrap a new project, update this file's "What this project is" section to describe *that* project — the rest (Architecture, Coding constraints, gotchas) stays valid as inherited documentation, the same way `mydaily`'s own CLAUDE.md credits `blog` as its boilerplate.

There is no product-specific domain logic here on purpose. `App\Controllers`/`App\Repositories` beyond `User`/`Setting` are meant to be added per-project; `App\Core` is the part meant to stay stable across projects unless you're deliberately improving the boilerplate itself.

## Commands

```bash
# Dev environment (PHP 7.2 + Apache + MySQL 8, Docker)
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

Controllers are thin HTTP glue over well-tested repositories and generally aren't unit-tested themselves — verify them end-to-end with `curl` (cookie jar for session state) against a running `docker compose` stack instead. There's no lint script configured; `php -l` per file is the practical syntax check (see PHP 7.2 constraint below).

## Architecture

Hand-rolled MVC, zero third-party runtime dependencies, zero build step — deliberately, because the deploy target is FTP-only shared hosting with no SSH/Composer/Node. Everything under `App\` autoloads via a tiny PSR-4-ish autoloader (`App\Core\Autoloader`), not Composer.

**Request flow:** `public/index.php` is the single entry point (document root is `public/`, but the root `.htaccess`/`index.php` shim covers hosts that serve from the project root instead — it just `require`s `public/index.php`). It: loads `.env` → starts the session → checks `App\Core\InstallState::isInstalled()` and hands off to `App\Core\InstallRoutes` if not → builds an `App\Core\Router` and registers every route by `require`-ing `routes.php` (a plain script at the project root that returns a `function (Router $router): void` — no route files/attributes beyond this single one, no auto-discovery) → dispatches. There's no DI container; controllers are instantiated fresh per request with no constructor arguments.

**Layers:**
- `App\Controllers` — one class per feature area, HTTP-flow only.
- `App\Repositories` — thin query wrappers around `App\Database\Database` (a PDO wrapper: `fetch`/`fetchAll`/`execute`). No ORM, no query builder, no models — repositories return plain arrays. Each repository takes an optional `Database` in its constructor (`?Database $database = null`) so tests can inject an isolated connection.
- `App\Core` — the framework-shaped pieces: `Router` (with a middleware pipeline, see below), `Auth`, `Env`/`EnvWriter` (hand-rolled `.env` parser/writer), `View` (plain `require` of a PHP template, no template engine), `Migrator`, `InstallState`, `Requirements`, `Text::slugify()`/`Text::truncate()`, `Uuid::v4()` (random-bytes based, no PHP `uuid` extension needed — reach for it when an entity needs a public identifier that shouldn't be a guessable auto-increment id).
- `App\Core\Middleware` — `MiddlewareInterface::handle(array $routeParams): ?string` (`null` = continue, a string = redirect to that Location, `Router::HALT` = response already sent, e.g. by `ErrorPage::forbidden()` — stop, don't redirect). `Router::get/post($path, $handler, $middleware = [])` takes an array of middleware — either a class name (instantiated with no constructor args) or an already-built instance for middleware that needs a parameter (follow `RequireRole::organizer()`-style static factories if you add one). `Router::resolve()` does the matching + runs the middleware chain with no side effects (no `header()`, no instantiating the controller) and returns a result array; `Router::dispatch()` acts on that result. This separation is what makes the middleware chain unit-testable — see `scripts/tests/router_middleware_test.php`. Only `/admin/*` routes use `RequireAuth::class` today; add more middlewares under `App\Core\Middleware` the same way (e.g. a `RequireRole` or a tenant resolver, if the project you're building on this needs one).
- `App\Views` — plain PHP templates. The install wizard uses the `ob_start()` → build `$content` → `require '.../layout.php'` pattern. Admin pages `require partials/shell-top.php` then `partials/shell-bottom.php` around inline markup — see any `app/Views/admin/*.php`. Public pages do the same with `partials/site-top.php`/`site-bottom.php`.

**Install & migrations:** no seeded account anywhere in the code. First request when `.env` lacks `APP_INSTALLED=true` is captured entirely by `InstallRoutes`/`InstallController`, a 5-step wizard gated by `.env.install`'s `INSTALL_KEY` (session flags gate each step server-side) that: checks `Requirements`, writes `.env` via `EnvWriter`, runs `database/migrations/*.sql` one at a time through `Migrator` (polled from JS, each migration's result individually visible), and creates the first user. Post-launch schema changes ship as new numbered `database/migrations/NNN_*.sql` files; `AdminUpdateController` (`/admin/atualizar`, requires login) re-runs the same `Migrator::runNextPending()` polling loop from inside the app — the mechanism that applies a migration in production after an FTP upload, since there's no CLI access there.

**Auth:** `App\Core\Auth` is a static wrapper over `$_SESSION['user']` (`id`, `uuid`, `name`, `email`, `is_admin`). This boilerplate ships a single flat role (any logged-in user can reach `/admin/*`) — `UserRepository::verifyPassword()` also transparently upgrades legacy 32-char MD5 hashes to `password_hash()` on successful login, useful if `users` ever gets seeded/imported data from elsewhere. If the project built on this needs real roles or multi-tenancy, follow the pattern `mydaily` used on top of the same boilerplate: a `Tenant` static registry (same shape as `Auth`) set only by a `ResolveTenant` middleware, tenant-scoped repositories taking the tenant id as a **required, non-nullable constructor argument**, and a `RequireRole` middleware built the same way as `RequireAuth`.

**Public identifiers:** internal `id INT AUTO_INCREMENT` shouldn't leave the data layer — avoid it in URLs, forms, or responses for anything sensitive. `App\Core\Uuid::v4()` is there for tables that need a public identifier; `App\Core\Text::slugify()` (transliterates accents rather than stripping them) is there for human-readable slugs when a table wants nicer URLs instead of a uuid.

## Coding constraints

The default production hosting target is **PHP 7.2**, chosen because that's the common denominator on cheap shared hosting with no upgrade path — this is more restrictive than "old PHP" generally implies. If the project you're building doesn't have that constraint, relax it deliberately (and update this section) rather than by accident. Avoid: typed properties (`private string $x;`, PHP 7.4+), constructor property promotion, attributes, union types, `match`, named arguments, `readonly`, nullsafe `?->`, `str_contains`/`str_starts_with`/`str_ends_with`, `??=`, arrow functions (`fn()`, PHP 7.4+). Typed parameters and return types (`string $x`, `: void`, `?array`) are fine — the restriction is specifically on class properties and 7.4+/8-only syntax. Before assuming a construct is safe, check it:

```bash
docker run --rm -v "$(pwd):/app" php:7.2-cli php -l <file>
```

Follow PSR-12, 4-space indentation, namespaces matching path (`App\Controllers\UserController` → `app/Controllers/UserController.php`). `PascalCase` classes, `camelCase` methods/variables, `snake_case` DB tables/columns.

Routes are hand-registered in `routes.php` (project root, required by `public/index.php`), resolved through `App\Core\Router` — no filenames or query-string IDs in URLs, and route params meant to be public should be public identifiers (`{userUuid}`, `{slug}`), never internal ids.

## Gotchas already discovered on top of this boilerplate

**Adding a column via `ALTER TABLE` migration:** if the migration backfills existing rows (e.g. `UPDATE teams SET x = created_by_user_id WHERE x IS NULL`), that backfill only covers rows that already existed — it does **not** make the corresponding `Repository::create()` populate the column for *new* rows going forward. Update the `INSERT` too, in the same commit as the migration. The same applies to `scripts/tests/*_test.php` fixtures — each test file declares its own inline `CREATE TABLE` (there's no shared schema loader), so a new column needs adding to every fixture that creates that table, not just the real migration, or those tests silently run against a stale schema (`SELECT *` on a missing column just omits the key rather than erroring in PHP).

**MySQL `UNIQUE KEY` + `NULL` + generated columns don't compose the way you'd expect:** MySQL treats `NULL` as distinct in every row of a `UNIQUE KEY`, so a dedupe key over nullable columns does **not** stop two rows that are both `NULL` in that column from being inserted twice. The "obvious" fix (`STORED` generated shadow columns coalescing `NULL` to a sentinel) runs into a second wall: MySQL refuses `ON DELETE CASCADE` on any column a `STORED` generated column depends on (`errno 1215`). Don't reach for a DB-level uniqueness constraint over nullable columns without checking this first; verify any such migration by actually running it, not just by reading the SQL.

**`GROUP_CONCAT` gotcha:** MySQL does not guarantee row order inside `GROUP_CONCAT()` unless you give it an explicit `ORDER BY` (`GROUP_CONCAT(body ORDER BY position SEPARATOR ' ')`) — without it, concatenation order is whatever the storage engine happens to return, which silently scrambles anything relying on sequence. Caught by a test, not by inspection — write one whenever a query concatenates ordered child rows.

**Timezone gotcha (any `expires_at`/absolute-instant column):** `docker/php/php.ini` sets a local `date.timezone` for display purposes, but the MySQL container's `NOW()` is UTC. `date('Y-m-d H:i:s', $ts)` in PHP therefore produces a string offset from what MySQL considers "now" — a token computed as "expires in 30 min" can come out already in the past. Always generate these with `gmdate()`, and compare in SQL against `UTC_TIMESTAMP()` (or, in PHP, construct `DateTime` objects with an explicit `new DateTimeZone('UTC')`), never bare `NOW()`/`date()`. Also use `DATETIME`, not `TIMESTAMP`, for any such column — `TIMESTAMP` maxes out in 2038.

**PDO native-prepare gotchas (`PDO::ATTR_EMULATE_PREPARES => false` in `App\Database\Database`):** two things that work fine with emulated prepares silently misbehave here. (1) The same named placeholder can't be reused twice in one query (`WHERE name LIKE :q OR slug LIKE :q` errors/misbinds) — give each occurrence its own name bound to the same value (`:query_name`, `:query_slug`, ...). (2) `LIMIT`/`OFFSET` bound as query parameters are unreliable across MySQL versions with native prepares — build them into the SQL string directly instead, which is safe as long as the values are already typed `int` in the calling method's signature.

Never commit real `.env`/`.env.install`, DB dumps, or `vendor/`/`node_modules/` (none currently exist, but the `.gitignore` already excludes them if introduced). `.env.docker` (dev-only, disposable credentials) is the one `.env*` file meant to be committed — see `.gitignore`.
