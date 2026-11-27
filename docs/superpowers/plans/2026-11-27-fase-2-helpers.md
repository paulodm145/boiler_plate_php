# Fase 2: Helpers PHP, plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `App\Helpers` com os utilitários puros do mydaily (texto, arrays, números, máscaras, documentos BR, datas, dinheiro, entrada), mais `Br\Date`, `Input::decimal` e `Input::date`, e `Core\Text`/`Uuid`/`Asset` movidos para lá.

**Architecture:** Portar de `~/Projetos/mydaily/app/Helpers/` (PHP 8.2), ajustando namespace, comentários que citam o mydaily (equipe, reunião, specs de lá) e nomes. Classes `final` com construtor privado e métodos estáticos, como no mydaily. Sem testes unitários (decisão do dono). A verificação é `php -l` + suíte + um smoke script descartável.

**Tech Stack:** PHP 8.2, sem dependências.

**Spec:** `docs/superpowers/specs/2026-11-27-fase-2-helpers-design.md`

## Global Constraints

- Sem testes unitários para os helpers. Não portar os `*_test.php` do mydaily.
- Puro: nenhum helper lê `$_POST`, `$_GET`, `$_SESSION`, banco ou chama `header()`. Exceções: `Url::absolute` lê `APP_URL` via `Env` e `Asset` lê `filemtime`.
- Dinheiro em centavos (`int`); decimal e peso como string decimal. Nada de `float` no caminho de leitura para gravação.
- CNPJ alfanumérico (12 `A-Z0-9` + 2 dígitos), sempre texto.
- Identificadores ASCII, em inglês. O `scripts/tests/ascii_identifiers_test.php` precisa continuar verde.
- Comentários e docblocks sem referência a mydaily, equipe, tenant ou specs de lá.
- Commits atômicos, semânticos, no infinitivo, terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Suíte: `docker compose exec -T --user www-data app php scripts/test_install.php`. Lint: `docker compose exec -T app php -l <arquivo>`.
- Smoke: `$SP/helpers_smoke.php` no scratchpad (fora do repo, nunca commitado). Começa carregando `/var/www/html/app/Core/Autoloader.php` + `register()` e `/var/www/html/app/functions.php`, e define `check($actual, $expected)`, que imprime `ok`/`FALHOU <esperado> <recebido>` com `var_export`. Cada tarefa acrescenta suas linhas `check(...)`. Roda com `docker compose exec -T app php < $SP/helpers_smoke.php`. Esperado: nenhuma linha `FALHOU`.

## Review Focus

1. **CNPJ com letras minúsculas e pontuação** (`12.abc.345/01de-35`): `clean` → `12ABC34501DE35`, e `isValid` decide pelo valor limpo. Smoke na Task 3.
2. **Dinheiro ambíguo** (`1.234.56`, `12,345`): `Input::money` devolve `null`, nunca um valor errado. Smoke na Task 4.
3. **Decimal com mais casas que a escala** (`Input::decimal('1,2345', 3)`): devolve `null`, sem truncar nem arredondar em silêncio. Smoke na Task 4.
4. **Data BR impossível** (`31/02/2026`, `29/02/2025`): `Br\Date::parse` devolve `null`. Smoke na Task 3.
5. **`Asset::url` de arquivo inexistente**: devolve `?v=1`, sem warning. Smoke na Task 1.

---

### Task 1: mover `Text`, `Uuid` e `Asset` para `App\Helpers` e criar `e()`

**Files:**
- Create: `app/Helpers/Text.php` (conteúdo de `app/Core/Text.php` + `plainExcerpt` do mydaily), `app/Helpers/Uuid.php` (de `app/Core/Uuid.php`), `app/Helpers/Asset.php` (de `app/Core/Asset.php`, mais o `url()` do mydaily), `app/functions.php`
- Delete: `app/Core/Text.php`, `app/Core/Uuid.php`, `app/Core/Asset.php`
- Modify (use statements): `app/Repositories/UserRepository.php`, `app/Core/Model.php` (passa a importar `App\Helpers\Uuid`), `app/Views/partials/site-top.php`, `app/Views/admin/partials/head.php`, `scripts/tests/user_password_test.php`, `scripts/tests/asset_test.php`
- Modify (carregar `functions.php` logo após o autoloader): `public/index.php`, `scripts/migrate.php`, `scripts/test_install.php`

**Interfaces:**
- Produces: `Text::slugify(string): string`, `Text::truncate(string, int): string`, `Text::plainExcerpt(string $html, int $max): string`; `Uuid::v4(): string`; `Asset::version(string $path): string` (como hoje), `Asset::url(string $path): string` (`'/assets/' . $path . '?v=' . filemtime`, caminho relativo a `public/assets/`, `'1'` se não existir); `e(mixed $value): string` = `htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`, com `null` → `''`, declarada com `if (!function_exists('e'))`.

- [ ] **Step 1: Trocar os `use` dos testes** `asset_test.php` e `user_password_test.php` para `App\Helpers\...`.
- [ ] **Step 2: Rodar a suíte.** Esperado: falhas com `Class "App\Helpers\Asset" not found` e `Class "App\Helpers\Uuid" not found`.
- [ ] **Step 3: Criar os três arquivos em `app/Helpers/`, apagar os de `app/Core/`, atualizar os usos e criar `app/functions.php` carregado nos três pontos de entrada.**
- [ ] **Step 4: Verificar**: `grep -rn 'Core\\\\Text\|Core\\\\Uuid\|Core\\\\Asset' app scripts routes.php public` volta vazio; suíte verde; `curl -s -o /dev/null -w '%{http_code}' localhost:8002/install` = `200` (o layout do instalador carrega); smoke: `check(e(null), '')`, `check(e('<a "x">'), '&lt;a &quot;x&quot;&gt;')`, `check(App\Helpers\Asset::url('css/nao-existe.css'), '/assets/css/nao-existe.css?v=1')`, `check(preg_match('/\?v=\d+$/', App\Helpers\Asset::url('css/app.css')), 1)`.
- [ ] **Step 5: Commit** `refactor(helpers): mover Text, Uuid e Asset para App\Helpers e adicionar e()`.

### Task 2: texto, arrays, números, máscaras e saída segura

**Files:**
- Create (portar do mydaily): `app/Helpers/Str.php`, `Arr.php`, `Number.php`, `Mask.php`, `Lgpd.php`, `HtmlSanitizer.php`, `Color.php`, `Url.php`

**Interfaces:**
- Consumes: `Text::slugify` (Task 1), que `Str::slug` delega.
- Produces: as assinaturas do mydaily, sem mudança. `Mask::CPF`, `CNPJ`, `CEP`, `PHONE`, `MOBILE`; `Mask::apply(string, string): string` usa `Str::alnum`, então letras do CNPJ passam; `Number::format(int|float, int $decimals = 0): string` no formato BR; `Url::absolute(string $path): string` a partir de `Env::get('APP_URL')`.

- [ ] **Step 1: Portar os 8 arquivos**, limpando os comentários conforme as Global Constraints.
- [ ] **Step 2: Lint dos 8 arquivos.** Esperado: `No syntax errors detected` em cada um.
- [ ] **Step 3: Smoke**: `check(App\Helpers\Str::slug('Ação Rápida'), 'acao-rapida')`, `check(App\Helpers\Arr::get(['a' => ['b' => 1]], 'a.b'), 1)`, `check(App\Helpers\Number::format(1234.5, 2), '1.234,50')`, `check(App\Helpers\Number::format(1234.567, 3) . ' kg', '1.234,567 kg')`, `check(App\Helpers\Mask::apply('12abc34501de35', App\Helpers\Mask::CNPJ), '12.abc.345/01de-35')` (a máscara não muda caixa; o `Br\Cnpj::format` da Task 3 deixa maiúsculo), `check(str_contains(App\Helpers\Lgpd::email('paulo@exemplo.com'), 'paulo@'), false)` + `check(str_ends_with(App\Helpers\Lgpd::email('paulo@exemplo.com'), '@exemplo.com'), true)`, `check(App\Helpers\HtmlSanitizer::isSafeLink('javascript:alert(1)'), false)`, `check(App\Helpers\Color::hexOr('red', '#000000'), '#000000')`. Suíte verde (inclui o teste ASCII).
- [ ] **Step 4: Commit** `feat(helpers): adicionar Str, Arr, Number, Mask, Lgpd, HtmlSanitizer, Color e Url`.

### Task 3: documentos e datas brasileiras

**Files:**
- Create (portar): `app/Helpers/Br/Cpf.php`, `Br/Cnpj.php`, `Br/Cep.php`, `Br/Phone.php`
- Create (novo): `app/Helpers/Br/Date.php`

**Interfaces:**
- Consumes: `Mask` (Task 2).
- Produces: as assinaturas do mydaily para `Cpf`, `Cnpj`, `Cep` e `Phone` (`clean`, `isValid`, `format`, e `isAlphanumeric`/`checkDigits`/`isMobile` onde houver). Novo `Br\Date`:
  - `parse(?string $value): ?string`: `dd/mm/aaaa` → `aaaa-mm-dd`; aceita `aaaa-mm-dd` já válida e a devolve igual; `null` para vazio, formato errado ou data inexistente. Valide com `checkdate()`, sem `DateTime` permissivo.
  - `format(?string $value): string`: `aaaa-mm-dd` ou `aaaa-mm-dd HH:MM:SS` → `dd/mm/aaaa`; `null`/`''` → `''`; o que não for data devolve `''`.

- [ ] **Step 1: Portar os 4 e escrever `Br\Date`.**
- [ ] **Step 2: Lint dos 5.**
- [ ] **Step 3: Smoke**:
  - `check(App\Helpers\Br\Cnpj::clean('12.abc.345/01de-35'), '12ABC34501DE35')`;
  - um CNPJ alfanumérico válido gerado com `Cnpj::checkDigits('12ABC34501DE')` dá `isValid === true`, e trocar um dígito verificador dá `false`;
  - `check(App\Helpers\Br\Cnpj::isValid('11.222.333/0001-81'), true)`;
  - `check(App\Helpers\Br\Cpf::isValid('529.982.247-25'), true)`, `check(App\Helpers\Br\Cpf::isValid('111.111.111-11'), false)`;
  - `check(App\Helpers\Br\Cep::format('29100000'), '29100-000')`;
  - `check(App\Helpers\Br\Date::parse('24/11/2026'), '2026-11-24')`, `check(App\Helpers\Br\Date::parse('2026-11-24'), '2026-11-24')`, `check(App\Helpers\Br\Date::parse('31/02/2026'), null)`, `check(App\Helpers\Br\Date::parse('29/02/2025'), null)`, `check(App\Helpers\Br\Date::parse('29/02/2028'), '2028-02-29')`, `check(App\Helpers\Br\Date::parse('24-11-2026'), null)`;
  - `check(App\Helpers\Br\Date::format('2026-11-24 13:00:00'), '24/11/2026')`, `check(App\Helpers\Br\Date::format(null), '')`.

  Suíte verde.
- [ ] **Step 4: Commit** `feat(helpers): adicionar validadores e formatos brasileiros (CPF, CNPJ alfanumerico, CEP, telefone e data)`.

### Task 4: datas, dinheiro e leitura de entrada

**Files:**
- Create (portar): `app/Helpers/DateOnly.php`, `DateFormat.php`, `LocalTime.php` (já usa `date_default_timezone_get()`, sem `AppTimezone`), `Duration.php`, `MoneyFormat.php`, `Input.php` (sem `teamSchedule`, `workdayHours` e `daysOfWeek`)

**Interfaces:**
- Consumes: `Br\Date::parse` (Task 3).
- Produces:
  - `MoneyFormat::format(int $cents, string $currency = 'BRL'): string`;
  - `Input::money(mixed $value, string $currency = 'BRL'): ?int` (BRL ganha padrão, como no `MoneyFormat`);
  - `Input::cents(mixed $value): int` (como no mydaily);
  - os demais `Input` do mydaily (`string`, `nullableString`, `nullableInt`, `accessEmail`, `isDigitsOrBlank`, `time`, `duration`);
  - **novos:**
    - `Input::decimal(mixed $value, int $scale): ?string`: aceita `1.234,567` (milhar `.` e decimal `,`), `1234,567` e `1234.567` (ponto como decimal só sem vírgula e sem grupo de milhar). Devolve string canônica com ponto, sem zeros à esquerda e sem completar casas (`'1.234,5'` → `'1234.5'`). Devolve `null` para vazio, mais casas que `$scale`, mais de 15 dígitos inteiros ou formato ambíguo/inválido. Sinal `-` opcional no início. Sem `float`: parse por regex e string.
    - `Input::date(mixed $value): ?string`, que delega a `Br\Date::parse`.

- [ ] **Step 1: Portar os 6 e escrever `Input::decimal` e `Input::date`.**
- [ ] **Step 2: Lint dos 6.**
- [ ] **Step 3: Smoke**:
  - `check(App\Helpers\MoneyFormat::format(123456), 'R$ 1.234,56')`, `check(App\Helpers\MoneyFormat::format(-50), '-R$ 0,50')`;
  - `check(App\Helpers\Input::money('1.234,56'), 123456)`, `check(App\Helpers\Input::money('42,5'), 4250)`, `check(App\Helpers\Input::money('1.234.56'), null)`, `check(App\Helpers\Input::money('12,345'), null)`;
  - `check(App\Helpers\Input::decimal('1.234,567', 3), '1234.567')`, `check(App\Helpers\Input::decimal('1234.5', 3), '1234.5')`, `check(App\Helpers\Input::decimal('1,2345', 3), null)`, `check(App\Helpers\Input::decimal('-0,5', 2), '-0.5')`, `check(App\Helpers\Input::decimal('', 2), null)`, `check(App\Helpers\Input::decimal('1.234.5', 2), null)`;
  - `check(App\Helpers\Input::date('24/11/2026'), '2026-11-24')`;
  - `check(App\Helpers\Duration::label(90), '1h30')`;
  - `check(App\Helpers\DateFormat::long('2026-11-24'), '24 de novembro de 2026')`.

  Suíte verde.
- [ ] **Step 4: Commit** `feat(helpers): adicionar datas, dinheiro e leitura de entrada sem float`.

### Task 5: documentar no CLAUDE.md

**Files:**
- Modify: `CLAUDE.md` (linha de `App\Helpers` em Layers, e seção nova "Helpers: o que já existe"); `docs/superpowers/specs/2026-11-27-atualizacao-do-boilerplate-roteiro.md` (marcar a Fase 2)

Conteúdo da seção nova:
- uma tabela de 3 colunas (classe, para que serve, exemplo), uma linha por classe das Tasks 1 a 4, mais `LogPath` e `e()`;
- "Antes de escrever tratamento de texto, data, número ou entrada à mão, procure aqui.";
- "`App\Helpers` é puro: sem requisição, sem regra de negócio, sem estado. Helpers simples não têm teste unitário (decisão do dono); quem os usa é testado no seu nível.";
- "Dinheiro em centavos (`int`, `Input::money`/`MoneyFormat`); decimal e peso como string decimal (`Input::decimal`, coluna `DECIMAL`), nunca `float` no caminho de gravação.";
- "CNPJ é texto alfanumérico (IN RFB 2.229/2024), nunca número.";
- "Toda view nova escapa com `e()`; troque `htmlspecialchars(...)` por `e()` só ao mexer na view por outro motivo.".

Na lista do `App\Core` em Architecture, remover `Text::slugify()`/`Text::truncate()` e `Uuid::v4()`, que agora estão em Helpers. A explicação sobre o uuid vai para a linha do `Uuid` na tabela.

- [ ] **Step 1: Editar.**
- [ ] **Step 2: Suíte verde.**
- [ ] **Step 3: Commit** `docs(claude): documentar os helpers e as regras de dinheiro, decimal e CNPJ`.
