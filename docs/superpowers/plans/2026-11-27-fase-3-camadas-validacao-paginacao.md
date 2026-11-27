# Fase 3: Camadas, validação e paginação, plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Controller → FormRequest → Service → Repository como padrão, com `Validator` no estilo Laravel (incluindo as regras BR), respostas de erro por campo, paginação uniforme, e usuários, configurações e recuperação de senha reescritos como exemplo.

**Architecture:** As classes novas ficam em `App\Services` (`ServiceResult`, Services por área), `App\Validation` (`Validator`, `ErrorBag`, `ValidationException`), `App\Http` (`FormRequest`, `ValidationResponder`, `Form`, `Flash`, `JsonResponse`, `ListQuery`) e `App\Database` (`Page`, `Paginator`). O `Router::dispatch()` captura a `ValidationException`. Origem portável: mydaily (`ServiceResult`, `JsonResponse`, `Database::transaction`) e lorencine (`Paginator`/`Grid` → `ListQuery`/`Page`, `Flash`, `live-grid.js`, partials).

**Tech Stack:** PHP 8.2 (typed properties, `readonly` e `match` liberados em código novo), MySQL 8, runner próprio.

**Spec:** `docs/superpowers/specs/2026-11-27-fase-3-camadas-validacao-paginacao-design.md`

## Global Constraints

- Identificadores em inglês, ASCII. Mensagens ao usuário em pt-BR com acento.
- Controller sem SQL, sem regra de negócio e sem método privado de validação. Service sem superglobais, sem `header()`, sem `echo` e sem sessão.
- `unique`/`exists`: tabela e coluna conferidas contra `^[a-z_][a-z0-9_]*$`, senão `InvalidArgumentException`.
- `ORDER BY` só a partir do mapa `$sortable`. `LIMIT`/`OFFSET` são inteiros montados na string (gotcha do PDO nativo). Placeholder repetido ganha um nome por ocorrência.
- O redirect de volta só segue um `Referer` cujo host é igual ao `HTTP_HOST`. Os campos `password*` nunca vão para o `old`.
- Parâmetros de listagem: `page`, `per_page` (`[10, 25, 50, 100]`, padrão 25), `sort`, `dir` (`asc|desc`), `q` (máximo 100 caracteres), `partial=1` (só o pedaço da tabela).
- Testes novos registrados em `scripts/test_install.php`. Fixtures declaram o próprio `CREATE TABLE` (ver o gotcha no CLAUDE.md).
- Suíte: `docker compose exec -T --user www-data app php scripts/test_install.php`, sozinha.
- Commits atômicos, semânticos, no infinitivo, com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **`Referer` ausente ou de outro host** na falha de validação: vai para o `redirectTo()` (padrão `/`), nunca para o host externo. Teste na Task 3.
2. **Campo array onde se espera texto** (`name[]=x`): a regra `string` falha com mensagem, sem warning e sem gravar "Array". Teste na Task 2.
3. **`page` além da última** (`?page=999` com 3 páginas): mostra a última página, sem lista vazia nem erro. Teste na Task 4.
4. **Sessão sem erros anteriores** (primeira visita ao form): `old()` devolve o padrão, `errors()->any()` é falso e as partials não renderizam nada. Teste na Task 3.
5. **Excluir o próprio usuário, ou um uuid inexistente**: `cannot_delete_self` / `not_found`, sem exclusão. Teste na Task 5.

---

### Task 1: `ServiceResult`, `JsonResponse` e `Database::transaction`

**Files:**
- Create: `app/Services/ServiceResult.php` (mydaily), `app/Http/JsonResponse.php` (mydaily, adaptado)
- Modify: `app/Database/Database.php` (acrescentar `transaction()` + `transactionState()` do mydaily, com `WeakMap` estático)
- Test: `scripts/tests/service_result_test.php`, `scripts/tests/database_transaction_test.php`

**Interfaces:**
- Produces:
  - `ServiceResult::ok(mixed $data = null)`, `fail(string $code, string $message = '')`, `failWith(string $code, string $message, mixed $data)`, `isOk(): bool`, `data(): mixed`, `code(): ?string`, `message(): ?string`;
  - `JsonResponse::send(mixed $data, int $status = 200): void`, `error(string $code, int $status, string $message = ''): void` (corpo `{"error": code, "message": msg}`), `fromResult(ServiceResult $r): void` (ok → 200 com `data`; `not_found` → 404; `forbidden` → 403; outros → 422), `statusFor(ServiceResult $r): int` (pura, para teste);
  - `Database::transaction(callable $fn): mixed`.

- [ ] **Step 1: Testes.**
  - `service_result_test.php`: `ok` guarda os dados; `fail` guarda código e mensagem; `statusFor`: ok → 200, `not_found` → 404, `forbidden` → 403, `email_taken` → 422.
  - `database_transaction_test.php` (tabela `tx_probe (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20))`):
    - `it('commit grava')`;
    - `it('excecao desfaz e relanca')`;
    - `it('aninhada so faz commit no nivel externo')`: dentro de uma transação, outra insere; uma exceção depois, no externo, desfaz as duas;
    - `it('falha interna capturada ainda desfaz tudo')`: o interno lança e o externo captura; ao sair, `RuntimeException` e zero linhas.
- [ ] **Step 2: Rodar.** Esperado: falha por classe ou método inexistente.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(core): adicionar ServiceResult, JsonResponse e transacao reentrante`.

### Task 2: `Validator`

**Files:**
- Create: `app/Validation/Validator.php`, `app/Validation/ErrorBag.php`
- Test: `scripts/tests/validator_test.php`

**Interfaces:**
- Consumes: `Br\Cpf`, `Br\Cnpj`, `Br\Cep`, `Br\Phone`, `Br\Date`, `Input::money`, `Input::decimal` (fase 2); `Database::shared()`.
- Produces:
  - `Validator::make(array $data, array $rules, array $messages = [], array $attributes = []): self`, `setDatabase(Database $db): self`, `passes(): bool`, `fails(): bool`, `errors(): ErrorBag`, `validated(): array`;
  - `Validator::extend(string $name, callable $rule, string $message): void`, com `$rule(mixed $value, array $params, array $data): bool`;
  - `ErrorBag::__construct(array $messages = [])`, `has(string $field): bool`, `first(string $field): ?string`, `get(string $field): array`, `all(): array<string,string[]>`, `any(): bool`, `count(): int`.

Regras, cada uma como método `protected function ruleX(mixed $value, array $params, string $field): bool`, e mensagens padrão (constante `MESSAGES`):

| Regra | Mensagem |
|---|---|
| `required` | `O campo :attribute é obrigatório.` |
| `string` | `O campo :attribute deve ser um texto.` |
| `email` | `Informe um e-mail válido em :attribute.` |
| `min` | `O campo :attribute deve ter pelo menos :min caracteres.` (numérico: `deve ser no mínimo :min.`) |
| `max` | `O campo :attribute deve ter no máximo :max caracteres.` (numérico: `deve ser no máximo :max.`; arquivo: `deve ter no máximo :max KB.`) |
| `between` | `O campo :attribute deve estar entre :min e :max.` |
| `in` | `O valor de :attribute não é permitido.` |
| `numeric` / `integer` | `O campo :attribute deve ser um número.` / `deve ser um número inteiro.` |
| `regex` | `O formato de :attribute é inválido.` |
| `confirmed` | `A confirmação de :attribute não confere.` |
| `same` | `Os campos :attribute e :other devem ser iguais.` |
| `date` | `Informe uma data válida em :attribute.` |
| `before` / `after` | `A data de :attribute deve ser anterior a :other.` / `posterior a :other.` |
| `unique` | `Este :attribute já está em uso.` |
| `exists` | `O :attribute selecionado não existe.` |
| `file` / `mimes` / `max_size` | `Envie um arquivo em :attribute.` / `O arquivo de :attribute deve ser do tipo: :values.` / `O arquivo de :attribute deve ter no máximo :max KB.` |
| `cpf` / `cnpj` / `cep` / `phone` | `Informe um CPF válido.` / `Informe um CNPJ válido.` / `Informe um CEP válido.` / `Informe um telefone válido.` |
| `date_br` | `Informe uma data válida (dd/mm/aaaa) em :attribute.` |
| `money` / `decimal_br` / `weight` | `Informe um valor válido em :attribute (ex.: 1.234,56).` / `Informe um número válido em :attribute.` / `Informe um peso válido em :attribute (ex.: 1.234,567).` |

Detalhes:
- **Campo vazio:** `null`, `''` só de espaços ou `[]` sem `required` pula as demais regras. `nullable` não faz nada além de documentar isso.
- **Valores não-string:** um campo array em regra de texto falha na própria regra, sem warning.
- **`:attribute`:** `attributes()[$field]` ou `str_replace('_', ' ', $field)`. `messages()` aceita `'campo.regra'` e `'regra'`.
- **Comparação com outro campo:** `before`/`after` aceitam um `Y-m-d` literal ou o nome de outro campo. `:other` usa o nome amigável desse campo.
- **`unique:tabela,coluna,ignorarId,colunaId=id`:** `SELECT 1 FROM tabela WHERE coluna = :value [AND colunaId <> :ignore] LIMIT 1`. **`exists:tabela,coluna`** é análogo.
- **Arquivos:**
  - `file` exige um item de `$_FILES` com `error === UPLOAD_ERR_OK` e `is_uploaded_file($tmp) || is_file($tmp)` (o teste usa um arquivo temporário);
  - `mimes:jpg,png,pdf` detecta com `finfo_file`, usando o mapa `jpg|jpeg` → `image/jpeg`, `png` → `image/png`, `gif` → `image/gif`, `webp` → `image/webp`, `pdf` → `application/pdf`, `csv|txt` → `text/plain`/`text/csv`, `xlsx` → `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` ou `application/zip`, `zip` → `application/zip`;
  - `max_size:kb` usa `size`.
- `weight` = `decimal_br` com escala 3. `decimal_br:2` = `Input::decimal($v, 2) !== null`. `money` = `Input::money($v) !== null`.

- [ ] **Step 1: Testes** (`validator_test.php`), um `it` por linha:
  - `required` falha para `''`, `'   '`, `null` e `[]`, e passa para `'0'`;
  - opcional vazio pula as regras: `['nick' => '']` com `'email'` passa;
  - `string` falha para `['x']`, sem warning (`error_get_last()` continua `null` depois de `error_clear_last()`);
  - `email`;
  - `min:3`/`max:5` com texto multibyte (`'ção'` tem 3);
  - `max:10` com `numeric` compara valor;
  - `between`, `in:a,b`, `numeric`, `integer` (`'1.5'` falha), `regex:/^\d+$/`;
  - `confirmed` (`password` + `password_confirmation`), `same:other`;
  - `date` (`'2026-02-31'` falha);
  - `before:2026-12-31` e `after:start` usando outro campo;
  - `unique:test_users,email` contra a tabela de teste (`test_users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(180))`), com o ignorar id passando para o próprio registro;
  - `exists:test_users,email`;
  - `unique:users;drop,email` lança `InvalidArgumentException`;
  - `file` + `mimes:png` com um PNG real de 1x1 gravado em `tempDir()` e um `.png` que na verdade é texto (falha em `mimes`), mais `max_size:1` com arquivo de 2 KB;
  - `cpf` (`529.982.247-25` passa, `111.111.111-11` falha);
  - `cnpj` (`12.ABC.345/01DE-35` e `12.abc.345/01de-35` passam, `12.ABC.345/01DE-36` falha);
  - `cep`, `phone`;
  - `date_br` (`24/11/2026` passa, `31/02/2026` falha);
  - `money` (`1.234,56` passa, `1.234.56` falha);
  - `decimal_br:2` (`1,234` falha), `weight` (`1.234,567` passa);
  - mensagens: `:attribute` usa `attributes()`, `'name.required'` sobrescreve, o fallback troca `_` por espaço;
  - `errors()->all()` junta todas as mensagens do campo, e `first()` devolve a primeira;
  - `validated()` só tem os campos com regra (um campo extra não aparece);
  - `extend('even', ...)` funciona com a mensagem própria.
- [ ] **Step 2: Rodar.** Esperado: falhas por classe inexistente.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(validacao): adicionar Validator com regras por pipe e regras brasileiras`.

### Task 3: `FormRequest`, resposta de erro e apoio nas views

**Files:**
- Create: `app/Http/FormRequest.php`, `app/Validation/ValidationException.php`, `app/Http/ValidationResponder.php`, `app/Http/Form.php`, `app/Http/Flash.php` (lorencine, chaves em inglês), `app/Views/admin/partials/form-errors.php`, `app/Views/admin/partials/flash.php`
- Modify: `app/Core/Router.php` (`dispatch()` envolve a chamada ao controller em `try { ... } catch (ValidationException $e) { ValidationResponder::respond($e); }`), `app/functions.php` (`old()` e `errors()`), `app/Views/admin/partials/shell-top.php` (inclui `flash.php`)
- Test: `scripts/tests/form_request_test.php`

**Interfaces:**
- Consumes: `Validator`/`ErrorBag` (Task 2), `ErrorPage::wantsJson` (fase 1).
- Produces:
  - `abstract class FormRequest`:
    - `abstract public function rules(): array`;
    - `public function messages(): array` e `public function attributes(): array`, com padrão `[]`;
    - `protected function prepare(array $input): array` e `protected function transform(array $validated): array`, com padrão identidade;
    - `public function redirectTo(): string`, padrão `'/'`;
    - `public function validated(?array $input = null): array`, que lança `ValidationException`;
    - `public static function validate(?array $input = null): array` = `(new static())->validated($input)`;
    - `protected static function input(): array`: o JSON do corpo se `CONTENT_TYPE` contém `application/json`, senão `$_POST + $_FILES`.
  - `ValidationException`: `__construct(ErrorBag $errors, array $old, string $redirectTo)`, `errors(): ErrorBag`, `old(): array`, `redirectTo(): string`.
  - `ValidationResponder`:
    - `respond(ValidationException $e): void`;
    - `redirectTarget(?string $referer, string $host, string $fallback): string` (pura): a URL do `Referer` só se `parse_url(host) === $host`, devolvida como caminho + query; senão o `$fallback`;
    - na resposta JSON, `jsonBody(ErrorBag): array` = `['message' => 'Os dados informados são inválidos.', 'errors' => [...]]` com status 422;
    - no formulário, grava `$_SESSION['_errors']` e `$_SESSION['_old']` e redireciona com 303.
  - `functions.php`:
    - `old(string $field, mixed $default = ''): mixed` lê `$_SESSION['_old_current']`;
    - `errors(): ErrorBag` lê `$_SESSION['_errors_current']`;
    - uma função interna `flash_validation_state()` move `_errors`/`_old` para `_errors_current`/`_old_current` **uma vez por requisição** e é chamada logo depois do `Auth::start()` em `public/index.php`. Assim o par é lido em uma requisição e some na seguinte.
  - `Form::invalid(string $field): string` devolve `' is-invalid'`/`''`; `Form::feedback(string $field): string` devolve `'<div class="invalid-feedback">' . e(first) . '</div>'`/`''`.
  - `Flash::success(string)`, `Flash::error(string)` e `Flash::pull(): ?array{type,message}` (tipos `success`/`danger`).
  - `form-errors.php`: se `errors()->any()`, mostra o alerta `alert-danger` "Corrija os campos destacados:" com a lista `<ul>` de todas as mensagens (escapadas); senão, nada.

- [ ] **Step 1: Testes** (`form_request_test.php`; uma classe de teste `TestSignupRequest` com `name` `required|max:5`, `email` `required|email`, `password` `required|confirmed`, `born` `nullable|date_br` e `transform()` convertendo `born` com `Br\Date::parse`):
  - `it('validate devolve os dados transformados')`: `born` `'24/11/2026'` vira `'2026-11-24'`;
  - `it('falha lanca ValidationException com erros por campo')`;
  - `it('old da excecao nao leva campos de senha')`: as chaves `password` e `password_confirmation` não estão em `old()`;
  - `it('le o corpo JSON quando o Content-Type e JSON')`: usar `TestSignupRequest::input()` via subclasse que expõe o método, com um stream simulado: extrair `protected static function rawBody(): string` (padrão `file_get_contents('php://input')`) que a subclasse de teste sobrescreve;
  - `it('redirectTarget segue Referer do mesmo host')`: `('http://localhost:8002/admin/users/create?x=1', 'localhost:8002', '/')` → `'/admin/users/create?x=1'`;
  - `it('redirectTarget ignora Referer de outro host e vazio')`: `('https://evil.test/x', 'localhost:8002', '/admin')` → `'/admin'`, e `(null, ..., '/admin')` → `'/admin'`;
  - `it('jsonBody tem message e errors por campo')`;
  - `it('primeira visita: old devolve o padrao e errors vazio')`: sessão limpa, `flash_validation_state()`, `old('name', 'x') === 'x'`, `errors()->any() === false`, e o render da partial `form-errors.php` dá `''`;
  - `it('erros e old valem por uma requisicao')`: grava `_errors`/`_old`, chama `flash_validation_state()` → disponíveis; chama de novo → vazios;
  - `it('Form::invalid e feedback escapam e so aparecem com erro')`.
- [ ] **Step 2: Rodar.** Esperado: falhas por classe inexistente.
- [ ] **Step 3: Implementar**, incluindo o `try/catch` no `Router::dispatch()` e a chamada no `index.php`.
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(validacao): adicionar FormRequest, resposta de erro por campo e apoio nas views`.

### Task 4: Paginação padrão

**Files:**
- Create: `app/Http/ListQuery.php`, `app/Database/Page.php`, `app/Database/Paginator.php`, `app/Views/admin/partials/pagination.php` e `page-size.php` (adaptados do lorencine), `public/assets/js/live-grid.js` (lorencine, trocando `parcial` por `partial`, `pagina` por `page`, `ordem` por `sort` e `por_pagina` por `per_page`)
- Modify: `app/Core/QueryBuilder.php` (`paginate(ListQuery $list): Page`), `public/assets/css/app.css` (estilos de `grid-*` do lorencine `86e2b73`)
- Test: `scripts/tests/pagination_test.php`

**Interfaces:**
- Produces:
  - `ListQuery::fromRequest(array $sortable, string $defaultSort, string $defaultDir = 'asc', int $defaultPerPage = 25, ?array $query = null): self`;
  - `ListQuery`: `page(): int`, `perPage(): int`, `offset(): int`, `search(): string`, `sortKey(): string`, `direction(): string`, `orderBy(string $tieBreaker = ''): string`, `isPartial(): bool`, `url(array $changes = [], ?string $path = null): string` (sem `partial`; `null` remove), `sortLink(string $label, string $key, string $class = ''): string` (um `<th>` com `<a data-grid-link>` e ícone `Icon::svg('sort'|'sort-up'|'sort-down')`), `withPage(int $page): self`;
  - `const PER_PAGE_OPTIONS = [10, 25, 50, 100]`;
  - `final class Page`: `readonly array $items`, `readonly int $total`, `$page`, `$perPage`, `$pages`, `$from`, `$to`; `window(int $around = 2): array`; `meta(): array`;
  - `Paginator::sql(Database $db, string $selectSql, array $params, ListQuery $list, string $tieBreaker = ''): Page`;
  - `QueryBuilder::paginate(ListQuery $list): Page` (os itens são models), que ordena por `$list->orderBy()` e ignora o `orderBy()` do builder.
- Partials: recebem `$page` (`Page`) e `$list` (`ListQuery`).

- [ ] **Step 1: Testes** (`pagination_test.php`, tabela `page_probe (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(40), created_at DATETIME)` com 23 linhas):
  - `fromRequest` saneia: `page=-3` → 1, `page=abc` → 1, `per_page=7` → 25, `per_page=50` → 50, `sort=drop` → padrão, `dir=up` → `asc`, `q` de 150 caracteres → 100;
  - `orderBy` usa a expressão do mapa (`['name' => 'p.name']`, `sort=name`, `dir=desc` → `'p.name DESC'`) e o desempate (`'p.name DESC, p.id ASC'`);
  - `url` mantém os parâmetros e remove `partial` e os `null`;
  - `Paginator::sql`: `per_page=10`, `page=2` → 10 itens, `total` 23, `pages` 3, `from` 11, `to` 20;
  - **`page=999` → página 3, com 3 itens, `from` 21 e `to` 23**;
  - lista vazia (`WHERE 1=0`) → `total` 0, `pages` 1, `from` 0, `to` 0, sem itens;
  - `window()` com 10 páginas na página 5 → `[1, null, 3, 4, 5, 6, 7, null, 10]`;
  - `meta()` com as chaves `total, page, per_page, pages, from, to`;
  - `QueryBuilder::paginate` com um model de teste sobre `page_probe` devolve models e o mesmo `total`.
- [ ] **Step 2: Rodar.** Esperado: falhas.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(listagem): adicionar paginacao padrao com ListQuery, Page e Paginator`.

### Task 5: exemplo de usuários

**Files:**
- Create: `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`, `app/Services/User/UserService.php`, `app/Views/admin/partials/users-table.php` (o pedaço que o `partial=1` devolve)
- Modify: `app/Repositories/UserRepository.php` (`paginate(ListQuery $list): Page`), `app/Controllers/AdminUserController.php`, `app/Views/admin/users.php`, `app/Views/admin/user-form.php`
- Delete: `scripts/tests/admin_user_validation_test.php` (testava o `validate()` privado que deixa de existir)
- Test: `scripts/tests/user_service_test.php`, `scripts/tests/user_requests_test.php`

**Interfaces:**
- Consumes: Tasks 1 a 4.
- Produces:
  - `StoreUserRequest`:
    - `name` `required|string|max:120`;
    - `email` `required|email|max:180|unique:users,email`;
    - `password` `required|string|min:8|confirmed`;
    - atributos `name` → `nome`, `email` → `e-mail`, `password` → `senha`;
    - `redirectTo()` `/admin/users/create`.
  - `UpdateUserRequest::__construct(private int $userId, private string $userUuid)`:
    - `email` `...|unique:users,email,{userId}`;
    - `password` `nullable|string|min:8|confirmed`;
    - `redirectTo()` `/admin/users/{uuid}/edit`.
  - `UserService::__construct(?UserRepository $users = null, ?Database $database = null)`:
    - `list(ListQuery $list): Page`;
    - `find(string $uuid): ?array`;
    - `create(array $data): ServiceResult` (`email_taken`);
    - `update(string $uuid, array $data): ServiceResult` (`not_found`, `email_taken`; senha vazia mantém o hash);
    - `delete(string $uuid, array $actor): ServiceResult` (`not_found`, `cannot_delete_self`).
  - `UserRepository::paginate(ListQuery $list): Page`:
    - sortable `['name' => 'name', 'email' => 'email', 'created' => 'created_at']`, padrão `name asc`, desempate `id ASC`;
    - busca `q` com `WHERE name LIKE :q_name OR email LIKE :q_email`, os dois com `%q%`;
    - seleciona `uuid, name, email, created_at`, sem o `id` nem o `password`.
- **Controller** (sem métodos privados):
  - `index` usa `ListQuery::fromRequest([...], 'name')`, `UserService::list` e renderiza `admin/users`, ou só a partial se `isPartial()`;
  - `store` faz `StoreUserRequest::validate()` → `create` → `Flash::success('Usuário criado.')` → redirect 303 para `/admin/users`. No `email_taken`, lança a `ValidationException` com o erro no campo `email`, montada por um helper estático `ValidationException::withMessages(array $messages, array $old, string $redirectTo)`, criado aqui;
  - `update`/`delete` seguem o mesmo padrão; `not_found` → `ErrorPage::notFound()`, `cannot_delete_self` → `Flash::error('Você não pode excluir o próprio usuário.')` + redirect para a edição.
- **Views:**
  - `users.php`: um `data-live-grid` com form GET (`q`, `per_page`) e um `data-grid-results` com a partial; a tabela usa `$list->sortLink()`;
  - `user-form.php`:
    - `form-errors.php` no topo;
    - cada input com `Form::invalid()`, `value="<?= e(old('campo', $padrao)) ?>"` e `Form::feedback()`;
    - campo novo `password_confirmation`;
    - labels obrigatórios com `<span class="text-danger">*</span>`.

- [ ] **Step 1: Testes.**
  - `user_service_test.php` (fixture `users` com as colunas da migration 001 + 003):
    - criar grava com hash;
    - `create` com e-mail existente → `email_taken`;
    - `update` sem senha mantém o hash, e com senha troca;
    - `update` de uuid inexistente → `not_found`;
    - `delete` do próprio → `cannot_delete_self` e o usuário continua;
    - `delete` inexistente → `not_found`;
    - `delete` de outro remove;
    - `list` com `q` filtra por nome ou e-mail e a ordenação `email desc` funciona.
  - `user_requests_test.php`:
    - `StoreUserRequest` recusa e-mail repetido (`unique`) e senha sem confirmação;
    - `UpdateUserRequest` aceita o próprio e-mail e a senha vazia;
    - a mensagem de nome vazio é `O campo nome é obrigatório.`.
- [ ] **Step 2: Rodar.** Esperado: falhas.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Ponta a ponta** (instalação no `app_e2e`, como na fase 1; restaurar o `.env.docker` no fim), com `curl` e cookie jar:
  - POST de criar sem nome e com `Referer: http://localhost:8002/admin/users/create` → 303 para `/admin/users/create`; o GET seguinte mostra `is-invalid` no nome, `O campo nome é obrigatório.` e o e-mail digitado no `value`;
  - criar válido → 303 `/admin/users` e o alerta "Usuário criado." aparece uma vez;
  - `GET /admin/users?q=<parte do nome>&sort=email&dir=desc&per_page=10` lista filtrado;
  - `&partial=1` devolve só a tabela (sem `<html`);
  - POST com `Accept: application/json` e erro → 422 `{"message":...,"errors":{"name":[...]}}`;
  - excluir a si mesmo → alerta de erro.
- [ ] **Step 6: Commit** `refactor(usuarios): reescrever o admin de usuarios com FormRequest, Service e lista paginada`.

### Task 6: configurações, recuperação de senha, instalador e remoção do `Core\Validator`

**Files:**
- Create: `app/Http/Requests/UpdateSettingsRequest.php`, `ForgotPasswordRequest.php`, `ResetPasswordRequest.php`; `app/Services/Settings/SettingsService.php`; `app/Services/Auth/PasswordResetService.php`
- Modify: `app/Controllers/AdminSettingController.php`, `AdminPasswordResetController.php`, `InstallController.php` (só as checagens de `Validator::` passam a usar `App\Validation\Validator::make`), `app/Views/admin/settings.php`, `app/Views/admin/auth/forgot-password.php`, `app/Views/admin/auth/reset-password.php` (`form-errors.php` + `old()`; o reset ganha `password_confirmation`), `scripts/tests/admin_password_reset_test.php` (o caso "senha curta" passa a esperar `ValidationException`; os de limite por IP continuam)
- Delete: `app/Core/Validator.php`
- Test: `scripts/tests/password_reset_service_test.php`, `scripts/tests/settings_service_test.php`

**Interfaces:**
- Produces:
  - `UpdateSettingsRequest`:
    - `site_name` `required|string|max:120`, `site_description` `nullable|string|max:255`;
    - `smtp_host` `nullable|string|max:190`, `smtp_port` `nullable|integer|between:1,65535`, `smtp_encryption` `required|in:tls,ssl,none`;
    - `smtp_username` `nullable|string|max:190`, `smtp_password` `nullable|string|max:190`;
    - `smtp_from_address` `nullable|email|max:190`, `smtp_from_name` `nullable|string|max:120`;
    - `redirectTo()` `/admin/settings`. Confira os nomes dos campos em `app/Views/admin/settings.php` e no `SettingRepository::update`.
  - `SettingsService::__construct(?SettingRepository $settings = null)`: `all(): array`, `update(array $data): ServiceResult`, `smtpFromEnv(): array` (`SmtpMailer::envOverriddenKeys()`).
  - `ForgotPasswordRequest`: `email` `required|email|max:180`. `ResetPasswordRequest`: `password` `required|string|min:8|confirmed`.
  - `PasswordResetService::__construct(?UserRepository $users = null, ?PasswordResetRepository $resets = null, ?MailerInterface $mailer = null)`:
    - `requestLink(string $email): void` (silencioso se não existir; a mesma mensagem e o mesmo e-mail de hoje);
    - `isValidToken(string $token): bool`;
    - `reset(string $token, string $password): ServiceResult`, que devolve `invalid_token` (incluindo o token órfão, que é apagado) ou `ok($user)`.
  - O limite por IP (`RateLimiter::hit`) continua no controller, **antes** do `validate()`, porque é proteção HTTP.
  - O `Auth::login()` continua no controller. O Service devolve o usuário.

- [ ] **Step 1: Testes.**
  - `password_reset_service_test.php`:
    - `requestLink` de e-mail existente grava o token e chama o mailer (um fake que registra), e o de e-mail inexistente não chama;
    - `reset` com token válido troca a senha e apaga o token;
    - token inválido → `invalid_token`;
    - token órfão → `invalid_token` e o token é apagado.
  - `settings_service_test.php`: `update` grava e preserva a senha SMTP quando o campo vem vazio.
  - Ajustar `admin_password_reset_test.php`.
- [ ] **Step 2: Rodar.** Esperado: falhas.
- [ ] **Step 3: Implementar.** Apagar `app/Core/Validator.php`; `grep -rn 'Core\\\\Validator' app scripts` precisa voltar vazio.
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Ponta a ponta:**
  - salvar configurações com `smtp_port=abc` → o campo fica em vermelho com a mensagem;
  - salvar válido → `Flash` "Configurações salvas.";
  - esqueci a senha com e-mail inválido → erro por campo, e com e-mail válido → a mensagem genérica;
  - fluxo do instalador até o passo 4 com senha curta → a mensagem aparece como antes.
- [ ] **Step 6: Commit** `refactor(admin): levar configuracoes e recuperacao de senha para FormRequest e Service`.

### Task 7: CLAUDE.md e roteiro

**Files:**
- Modify: `CLAUDE.md`, `docs/superpowers/specs/2026-11-27-atualizacao-do-boilerplate-roteiro.md`

Mudanças no CLAUDE.md:
- Seção "Layers": acrescentar `App\Services`, `App\Validation` e `App\Http\Requests`, e incluir a tabela "pode / não pode" do design.
- Seção nova "Validation (FormRequest)": como criar um request; exemplo de controller; lista de regras; regras BR; `transform()` para converter BR → banco; `old()`, `errors()`, `Form::invalid()`/`feedback()` e `form-errors.php`; `ValidationException::withMessages()` para erro de regra de negócio num campo; 422 JSON; e o checklist do formulário novo:
  - campo em vermelho;
  - mensagem embaixo;
  - asterisco nos obrigatórios;
  - `old()`;
  - `Csrf::field()`;
  - máscaras e calendário quando a fase 6 existir.
- Seção nova "Listing (pagination)": `ListQuery`, `Paginator::sql`, `QueryBuilder::paginate`, as partials e o `live-grid.js`; quando usar o `admin-table.js`; e os parâmetros `page`/`per_page`/`sort`/`dir`/`q`/`partial`.
- Gotcha: placeholders distintos na busca.

- [ ] **Step 1: Editar** e marcar a Fase 3 no roteiro.
- [ ] **Step 2: Suíte verde.**
- [ ] **Step 3: Commit** `docs(claude): documentar camadas, FormRequest e paginacao padrao`.
