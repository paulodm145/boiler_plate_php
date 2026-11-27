# Fase 3: Camadas, validação e paginação (design)

Data: 2026-11-27. Roteiro: `2026-11-27-atualizacao-do-boilerplate-roteiro.md`. Branch: `feature/fase-3-camadas-validacao-paginacao`.

## Intenção

Fixar como uma requisição de escrita atravessa o código, para que todo projeto derivado faça igual:

- o **controller** recebe a requisição e a valida com um `FormRequest`, no estilo do Laravel;
- o **Service** aplica as regras e as transações e devolve um `ServiceResult`;
- o **Repository** faz o SQL;
- a **listagem** usa parâmetros uniformes de paginação, ordenação e busca.

O admin de usuários e o de configurações são reescritos nesse formato e viram o exemplo de referência.

## Decisões já tomadas (conversa com o dono)

- O controller só recebe e valida. A lógica fica nos Services.
- A validação segue o estilo do Laravel:
  - regras declarativas por pipe (`'email' => 'required|email|max:120'`);
  - `rules()`, `messages()` e `validated()`;
  - erros por campo e `old()`.
  
  Quando a validação falha num formulário, a resposta volta para ele. Numa requisição JSON, a resposta é 422 com os erros por campo.
- Regras BR: `cpf`, `cnpj` (alfanumérico), `cep`, `phone`, `date_br`, `money`, `decimal_br` e `weight`.
- **Exibição dos erros:** os formulários existentes mantêm o resumo no topo, como no mydaily. Os formulários novos marcam em vermelho os campos com erro e mostram embaixo de cada um o que falta.
- O QueryBuilder fica para consultas simples. Queries complexas usam SQL puro.
- Código e identificadores em inglês, sem acento. Mensagens para o usuário em português, com acento.

## Objetivos

1. Regra das camadas escrita no CLAUDE.md (tabela "pode / não pode", como a do mydaily) e seguida pelo código de exemplo.
2. `ServiceResult` e `JsonResponse`.
3. `Database::transaction()` reentrante.
4. `Validator` com regras por pipe, mensagens em pt-BR, regras BR e um jeito simples de acrescentar regras.
5. `FormRequest` com `rules()`, `messages()`, `attributes()`, `validated()` e um gancho de normalização. Na falha:
   - formulário: volta para a página anterior (só do mesmo host) com os erros e os valores digitados;
   - JSON: 422 com `{"message": ..., "errors": {campo: [mensagens]}}`.
6. Apoio nas views: `old()`, `errors()`, a classe `is-invalid` no campo, o `invalid-feedback` com a mensagem e uma partial de resumo. Também `Flash` para as mensagens de sucesso.
7. Paginação padrão:
   - parâmetros `page`, `per_page`, `sort`, `dir` e `q`, com listas permitidas;
   - funciona com SQL puro e com o QueryBuilder;
   - devolve itens + meta;
   - inclui partials de paginação, "exibir N" e cabeçalho ordenável, mais o `live-grid.js` do lorencine.
8. Admin de usuários (lista paginada no servidor + criar/editar/excluir) e de configurações em Controller → FormRequest → Service → Repository.
9. Recuperação de senha passando a usar `FormRequest` + Service.
10. `App\Core\Validator` removido. O instalador passa a usar o `Validator` novo nas suas checagens.

## Não-objetivos

- Reescrever o instalador em Services. Ele é um assistente do próprio core, com estados de sessão por passo, e mexer nele arrisca a instalação por FTP. Só troca o validador. *(Decisão do Claude.)*
- Validação no navegador e máscaras, que são da fase 6.
- Autorização dentro do `FormRequest` (`authorize()`): continua nos middlewares.
- API (fase 5) e upload de verdade (fase 4). As regras `file`, `mimes` e `max_size` entram aqui porque são de validação, mas o upload continua na fase 4.

## Anti-objetivos

- Um redirect de volta que aceite `Referer` de outro host (open redirect).
- Regra `unique`/`exists` que monte SQL a partir de entrada do usuário. Tabela e coluna vêm do código e são conferidas contra `^[a-z_][a-z0-9_]*$`.
- `ORDER BY` ou `LIMIT` montados com texto do request. A ordenação vem só do mapa de colunas permitidas, e os inteiros de `LIMIT`/`OFFSET` são `int`.
- Controller com regra de negócio, SQL ou método privado de validação.

## Peças

### Camadas (texto para o CLAUDE.md)

| Camada | Pode | Não pode |
|---|---|---|
| Controller | ler a requisição via `FormRequest`, chamar um Service, escolher a resposta (view, redirect, JSON), `Flash` | SQL, regra de negócio, `new Database()`, método privado de validação |
| FormRequest | regras de **formato** (`required`, `email`, `cpf`, `unique`...) e normalização da entrada | regra de negócio (quem pode, estado, limites) |
| Service | regras de negócio, transação, orquestrar repositórios e e-mails, devolver `ServiceResult` | superglobais, `header()`, `echo`, sessão |
| Repository | SQL (QueryBuilder no simples, SQL puro no complexo) | regra, sessão |
| View | exibir com `e()` | consulta, regra |

Services ficam em `app/Services/<Area>/<Nome>Service.php` (`App\Services\User\UserService`), recebem dependências opcionais no construtor (`?UserRepository $users = null`) para os testes e recebem o ator por parâmetro quando importa (`delete(string $uuid, array $actor)`).

### `App\Services\ServiceResult`

Portado do mydaily: `ok($data = null)`, `fail(string $code, string $message = '')`, `failWith(string $code, string $message, $data)`, `isOk()`, `data()`, `code()`, `message()`. Códigos em inglês e snake_case (`not_found`, `email_taken`, `cannot_delete_self`).

### `App\Http\JsonResponse`

Portado do mydaily: `send($data, int $status = 200)`, `error(string $code, int $status, string $message = '')` e `fromResult(ServiceResult $result)`. O `fromResult` mapeia `not_found` → 404, `forbidden` → 403 e os demais códigos → 422.

### `Database::transaction(callable $fn): mixed`

Reentrante, como no mydaily: só o nível mais externo faz commit ou rollback, e uma falha interna marca tudo para rollback. Mantém um estado por conexão (`WeakMap`).

### `App\Validation\Validator`

- `Validator::make(array $data, array $rules, array $messages = [], array $attributes = []): Validator`, com `passes()`, `fails()`, `errors(): array<string, string[]>`, `first(string $field): ?string` e `validated(): array`.
- O `validated()` devolve só os campos que têm regra, com o valor como veio.
- Regras em string com pipe ou em array (`['required', 'max:120']`). Parâmetros depois de `:`, separados por vírgula.
- **Semântica:**
  - um campo sem `required`, vazio (`null`, `''` ou `[]`), pula as outras regras;
  - `nullable` deixa isso explícito;
  - `required` falha para vazio;
  - `bail` não existe: cada campo junta todas as mensagens, e a view mostra a primeira.
- **Regras gerais:** `required`, `nullable`, `string`, `email`, `min:n`, `max:n` (texto conta caracteres `mb_strlen`; número compara valor; arquivo compara KB), `between:a,b`, `in:a,b,c`, `numeric`, `integer`, `regex:/.../`, `confirmed` (compara com `<campo>_confirmation`), `same:outro`, `date` (`Y-m-d` válida), `before:Y-m-d|campo` e `after:Y-m-d|campo`, `unique:tabela,coluna[,ignorarId[,colunaId]]`, `exists:tabela,coluna`, `file`, `mimes:jpg,png,pdf` (tipo pelo **conteúdo**, via `finfo`, com um mapa extensão → MIME) e `max_size:kb`.
- **Regras BR:** `cpf`, `cnpj` (alfanumérico), `cep`, `phone` e `date_br` (`Br\Date::parse`). Também `money` (`Input::money` não nulo), `decimal_br:escala` (`Input::decimal` não nulo) e `weight` (`decimal_br` com escala 3).
- **Regra nova:** um método `protected function ruleNome(mixed $value, array $params, string $field): bool` mais a mensagem em `MESSAGES`. Também existe `Validator::extend(string $name, callable $rule, string $message)` para regras do projeto sem editar a classe.
- **Mensagens:** pt-BR, com `:attribute`, `:min`, `:max`, `:values` e `:other`. Por exemplo, "O campo :attribute é obrigatório." Os nomes vêm de `attributes()`; sem eles, o nome do campo vira texto (`password_confirmation` → "password confirmation").
- `unique` e `exists` usam o `Database` injetado (`Validator::make(...)->setDatabase($db)`) ou o `Database::shared()`.

### `App\Http\FormRequest` (abstrata)

- `abstract rules(): array`; `messages(): array` e `attributes(): array` com padrão `[]`.
- `prepare(array $input): array`, um gancho antes de validar (aparar, juntar campos). O padrão é a identidade.
- `transform(array $validated): array`, um gancho depois de validar, para converter: `date_br` → `Y-m-d`, `money` → centavos. O padrão é a identidade.
- `public static function validate(?array $input = null): array`:
  - a entrada é `$_POST` + `$_FILES` por padrão, ou o JSON do corpo quando o `Content-Type` é JSON;
  - roda o `Validator`;
  - se passar, devolve `transform(validated())`;
  - se falhar, lança `App\Validation\ValidationException` com os erros e a entrada (sem os campos de senha).
- Regras que dependem da rota (o `unique` que ignora o próprio registro) usam `static::validate(...)` numa instância criada com contexto: `(new UpdateUserRequest($user['id']))->validated()`. Ou seja, `validate()` estático é um atalho para `(new static())->validated()`.
- `ValidationException` é tratada no `Router::dispatch()` por `App\Http\ValidationResponder::respond(ValidationException $e)`:
  - **JSON** (`ErrorPage::wantsJson`): status 422 e `{"message": "Os dados informados são inválidos.", "errors": {...}}`;
  - **formulário:** grava `errors` e `old` na sessão (lidos uma vez) e redireciona para o `Referer` **se for do mesmo host**; senão, para o `redirectTo()` do request, cujo padrão é `/`.
  
  O controller não escreve `if ($error)`.

### Apoio nas views

Em `app/functions.php`:
- `old(string $field, mixed $default = ''): mixed`;
- `errors(): App\Validation\ErrorBag`, com `has($field)`, `first($field)`, `all()` e `any()`.

Classe `App\Http\Form`:
- `Form::invalid(string $field): string` devolve `' is-invalid'` ou `''`;
- `Form::feedback(string $field): string` devolve `<div class="invalid-feedback">mensagem</div>` escapada, ou `''`.

Partials:
- `admin/partials/form-errors.php`, o resumo no topo: "Corrija os campos destacados" + a lista;
- `admin/partials/flash.php`.

Classe `App\Http\Flash` (lorencine): `success()`, `error()` e `pull()`.

Formulário novo de exemplo (usuários): resumo no topo **e** campo em vermelho com a mensagem embaixo. *(Decisão do Claude: como o formulário de usuário vira o exemplo de referência, ele já segue o padrão dos formulários novos.)*

### Paginação

`App\Http\ListQuery` lê e saneia os parâmetros da listagem.

- `ListQuery::fromRequest(array $sortable, string $defaultSort, string $defaultDir = 'asc', int $defaultPerPage = 25, ?array $query = null)`.
- `$sortable` é um mapa de chave pública para expressão SQL (`['name' => 'u.name', 'created' => 'u.created_at']`).
- Parâmetros:
  - `page`: inteiro ≥ 1;
  - `per_page`: só `[10, 25, 50, 100]`;
  - `sort`: só chaves do mapa;
  - `dir`: só `asc`/`desc`;
  - `q`: texto aparado com no máximo 100 caracteres.
- Valor fora da regra cai no padrão, sem erro.
- Métodos: `page()`, `perPage()`, `offset()`, `search()`, `sortKey()`, `direction()`, `orderBy(string $tieBreaker = ''): string` (a expressão do mapa + `ASC`/`DESC`), `url(array $changes): string` (URL atual com mudanças; `null` remove o parâmetro) e `sortLink(string $label, string $key): string` (cabeçalho clicável com ícone, como o `Grid::header` do lorencine).

`App\Database\Page` é o resultado imutável.
- Campos: `items`, `total`, `page`, `perPage`, `pages`, `from` e `to`.
- Métodos: `window(int $around = 2): array` (números de página, com `null` para "…") e `meta(): array`, que dá `['total', 'page', 'per_page', 'pages', 'from', 'to']` para a API.

`App\Database\Paginator`:
- `Paginator::sql(Database $db, string $selectSql, array $params, ListQuery $list, string $tieBreaker = ''): Page` conta com `SELECT COUNT(*) FROM (<sql>) AS counted` e busca com `<sql> ORDER BY <list->orderBy()> LIMIT <int> OFFSET <int>`, montados na string. A página além da última volta para a última.
- `QueryBuilder::paginate(ListQuery $list): Page` faz o mesmo com o builder. A ordenação vem do mapa, nunca do request.

A busca (`q`) é responsabilidade do repositório, que monta o `WHERE` com placeholders distintos por ocorrência (gotcha do PDO nativo).

Partials:
- `admin/partials/pagination.php` ("N registro(s) · página X de Y" + links);
- `admin/partials/page-size.php` ("Exibir 10/25/50/100").

`public/assets/js/live-grid.js` (lorencine) troca só o pedaço da tabela (`?partial=1`) na busca, na ordenação e na paginação. Sem JS, a página funciona do mesmo jeito.

Nomes dos parâmetros em inglês. A API da fase 5 usa os mesmos (`page`/`per_page`), e não o `skip`/`take` do mydaily. *(Decisão do Claude: um padrão só.)*

O `admin-table.js` atual (tudo no navegador) continua disponível para listas pequenas. O CLAUDE.md diz quando usar cada um.

### Exemplo de referência: usuários e configurações

- `StoreUserRequest` e `UpdateUserRequest(int $ignoreId)`:
  - `name`: `required|string|max:120`;
  - `email`: `required|email|max:180|unique:users,email[,id]`;
  - `password`: `required|min:8|confirmed` na criação e `nullable|min:8|confirmed` na edição.
  
  O formulário ganha o campo `password_confirmation`.
- `UserService`:
  - `list(ListQuery): Page`;
  - `create(array $data): ServiceResult`;
  - `update(string $uuid, array $data): ServiceResult`;
  - `delete(string $uuid, array $actor): ServiceResult`, que devolve `cannot_delete_self` para o próprio usuário e `not_found`.
  
  O e-mail duplicado continua conferido no Service também (`email_taken`), por causa de corrida e de chamadas fora do formulário.
- `UserRepository::paginate(ListQuery): Page`, com busca por nome ou e-mail e ordenação por `name`, `email` e `created`.
- `AdminUserController` fica só com a leitura via request, a chamada ao Service, o `Flash` e a resposta. Não tem métodos privados de validação.
- Lista `/admin/users` com busca, ordenação, "exibir N" e paginação no servidor, mais o `live-grid.js`.
- `UpdateSettingsRequest` + `SettingsService` para a tela de configurações, mantendo as regras do SMTP pelo `.env`.
- Recuperação de senha: `ForgotPasswordRequest` (`email`) e `ResetPasswordRequest` (`password` `min:8|confirmed`) mais um `PasswordResetService`. A lógica hoje está no controller, e o limite por IP continua como está.

## Testes (TDD)

São testes do runner próprio, em `scripts/tests/`:

- **`Validator`:**
  - cada regra geral, com um caso que passa e outro que falha;
  - as regras BR, incluindo CNPJ alfanumérico válido e inválido, `date_br` com `31/02`, `money` ambíguo e `decimal_br` com casas demais;
  - `unique` e `exists` contra o banco de teste, incluindo o ignorar id;
  - nome de tabela inválido no `unique`, que lança exceção;
  - mensagens com `:attribute` e `attributes()`;
  - `validated()` só com os campos que têm regra;
  - campo opcional vazio pula as regras;
  - `extend()`.
- **`FormRequest`:**
  - `validate()` devolve os dados transformados;
  - na falha, lança `ValidationException` com os erros e o `old` sem senha;
  - a entrada JSON é lida quando o `Content-Type` é JSON.
- **`ValidationResponder`:**
  - JSON dá 422 com o formato acima;
  - formulário grava `errors` e `old` na sessão e redireciona para o `Referer` do mesmo host;
  - um `Referer` de outro host vai para o `redirectTo()`.
- **`ListQuery`:** página negativa vira 1, `per_page` fora da lista vira o padrão, `sort` desconhecido vira o padrão, `dir` inválido vira `asc`, `q` é cortado em 100 caracteres e `orderBy` só usa o mapa.
- **`Paginator::sql` e `QueryBuilder::paginate`:** contra o banco de teste, cobrem total, páginas, `from`/`to`, a página além da última e a lista vazia.
- **`Database::transaction`:**
  - commit;
  - rollback na exceção;
  - transação aninhada com commit só no externo;
  - falha interna desfaz tudo.
- **`UserService`:**
  - criar;
  - e-mail em uso;
  - atualizar sem trocar a senha mantém o hash;
  - excluir a si mesmo dá `cannot_delete_self`;
  - uuid inexistente dá `not_found`.
- **Ponta a ponta com `curl`:**
  - criar usuário com erro: a página volta com o campo em vermelho e o nome digitado;
  - criar com sucesso: o `Flash` aparece;
  - buscar e paginar a lista com `?q=`, `?sort=`, `?per_page=` e `?partial=1`;
  - salvar configurações;
  - recuperar a senha;
  - POST com `Accept: application/json` e erro dá 422 JSON.

## Decisões do Claude (confira ao ler)

- O instalador só troca o validador e não vira Service.
- Os parâmetros de listagem são em inglês (`page`, `per_page`, `sort`, `dir`, `q`) e a API usa os mesmos.
- O formulário de usuário, que é o exemplo, já mostra o erro em cada campo, além do resumo.
- `FormRequest::transform()` é o lugar da conversão BR → banco (data, dinheiro, decimal). O `validated()` puro devolve o valor como veio.
- O `admin-table.js` (só no navegador) fica para listas pequenas, e o `ListQuery` para o resto.
- As regras moram em `App\Validation`, e não em `App\Http`, porque o Service ou um script podem reutilizar o `Validator`.
