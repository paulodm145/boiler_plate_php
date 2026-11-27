# Atualização do boilerplate — roteiro

Data: 2026-11-27. Status: **roteiro aprovado em conversa; cada fase ainda passa por design próprio antes de ser construída.**

## Por que

O mydaily e o lorencine (`~/Projetos/mydaily`, `~/Projetos/lorencine/sistema`) nasceram deste boilerplate e precisaram desenvolver peças que faltavam aqui: helpers, exportação padronizada, API, Services, e-mail com classes próprias, upload seguro, paginação e correções estruturais. O objetivo é trazer essas peças de volta, para que o próximo projeto comece com elas.

## Decisões já tomadas

- O boilerplate passa para **PHP 8.2**. A restrição a PHP 7.2 sai do CLAUDE.md.
- O **QueryBuilder/Model/Relations** daqui continuam, mas **queries complexas usam SQL puro**.
- **Controllers só recebem e validam a request. A lógica fica nos Services**, que devolvem `ServiceResult`. Repositories só fazem SQL.
- A validação segue o estilo do **FormRequest do Laravel**: regras declarativas por pipe, erros por campo, `old()` e `validated()`. Na API, a falha responde 422 em JSON.
- **Erros nos formulários:** os que já existem mantêm o resumo no topo, como no mydaily. Os formulários novos marcam em vermelho os campos com erro e mostram embaixo de cada um o que falta.
- O CNPJ é sempre **alfanumérico** (IN RFB 2.229/2024): 12 posições `A-Z0-9` mais 2 dígitos verificadores, guardado como texto. Vale para o PHP e para o JS.
- Os componentes JS não têm dependência e usam só o Bootstrap 5.3 que o admin já carrega (CSS e variáveis). Começam simples e evoluem com o tempo. *(Decisão do Claude.)*
- **Todo formulário aplica máscaras, calendário e erros por campo.** Isso vira regra no CLAUDE.md e item de checklist, porque a tendência é esquecer.

## Fases (nesta ordem)

Cada fase segue o mesmo caminho: design, plano, branch própria, TDD, suíte verde no PHP 8.2 e CLAUDE.md atualizado no mesmo merge.

### Fase 1 — Base e correções
- [ ] Docker em PHP 8.2 (tomando como base o `Dockerfile`/compose do lorencine), mantendo as portas 8002/3308.
- [ ] **`Csrf` + `VerifyCsrfToken`**, que estão faltando hoje: o `routes.php` usa a classe e o login quebra com erro fatal. Origem: mydaily, via lorencine `892f129`. O `migrate-progress.js` passa a enviar `X-CSRF-Token`.
- [ ] `.htaccess` da raiz bloqueando `lib/`, `docs/`, `docker/`, `routes.php`, arquivos ocultos e `*.md` (lorencine `faebe28`).
- [ ] SMTP pelo `.env` (`SMTP_*`), com precedência campo a campo sobre a tela de Configurações (lorencine `8665116`).
- [ ] `Logger` + `ErrorHandler` (mydaily): log diário com chaves sensíveis ocultas e página 500 com `request_id`.
- [ ] `SecurityHeaders` básicos (mydaily). Se entra CSP com nonce é decidido no design da fase 6.
- [ ] Instalador: administrador opcional quando já há usuários (lorencine `19bbfb8`).
- [ ] CLAUDE.md: PHP 8.2, a política do QueryBuilder e a documentação de `Model`/`Relations`, que existem mas não estão documentados.

### Fase 2 — Helpers PHP (`App\Helpers`, puros)
- [ ] Texto, arrays e números: `Str`, `Arr`, `Number`, `Text` (unificando com `Core\Text`), `Mask`, `Lgpd`.
- [ ] Brasil: `Br\Cpf`, **`Br\Cnpj` alfanumérico**, `Br\Cep`, `Br\Phone`, unindo os formatos do `Core\Br` do lorencine (moeda, decimal e data).
- [ ] Datas e dinheiro: `DateOnly`, `DateFormat`, `LocalTime`, `Duration`, `MoneyFormat`.
- [ ] Entrada e saída: `Input` (`money("1.234,56")` → centavos sem passar por float, decimal, peso e data BR → `Y-m-d`), `JsonResponse`, `Url`, `Asset`.
- [ ] Segurança: `HtmlSanitizer`, `FileName`, `ContentDisposition`, `Color`, `Uuid`.
- [ ] Função global `e()`.
- [ ] *A decidir no design:* quais helpers ganham teste. O mydaily dispensou testes nos helpers "estilo Laravel" (D-25), mas os validadores BR provavelmente devem ter.

### Fase 3 — Camadas, validação e paginação
- [ ] Regra das camadas no CLAUDE.md, com tabela do que pode e do que não pode, mais `ServiceResult`.
- [ ] `Database::transaction()` reentrante (mydaily).
- [ ] `FormRequest` com `rules()`, `messages()`, `validated()`, erros por campo, resumo e `old()`. Falha no form volta para o formulário; falha na API responde 422 em JSON.
- [ ] `Validator` com regras por pipe, substituindo o `Core\Validator` atual:
  - gerais: `required`, `nullable`, `string`, `email`, `min`, `max`, `between`, `in`, `numeric`, `integer`, `regex`, `confirmed`, `same`, `date`, `before`, `after`, `unique`, `exists`, `file`, `mimes` (pelo conteúdo), `max_size`;
  - **BR:** `cpf`, `cnpj` (alfanumérico), `cep`, `phone`, `date_br` (`dd/mm/aaaa`), `money` (`1.234,56`), `decimal_br`, `weight`.
- [ ] `Flash` (lorencine) e partials de erro por campo (`is-invalid` + `invalid-feedback`) e do resumo.
- [ ] **Classe padrão de paginação e listagem:** parâmetros uniformes (`page`, `per_page` com lista permitida, `sort` com colunas permitidas, `dir`, `q`). Funciona com o QueryBuilder e com SQL puro e devolve itens + meta. Une o `Paginator`/`Grid` do lorencine e o `skip/take` do mydaily. Inclui as partials de paginação e de "exibir N" e o `live-grid.js` (lorencine).
- [ ] Reescrever como exemplo: usuários, configurações, recuperação de senha e instalador, todos em Controller → FormRequest → Service.

### Fase 4 — Infraestrutura
- [ ] **E-mail:** classes `Mailable` (destinatário, assunto, view e dados), `Mailer` com `SmtpMailer` (PHPMailer) e `NullMailer`/log para dev e testes, templates em `Views/emails/` com `_layout`. A recuperação de senha passa a usar isso.
- [ ] **Upload:** `UploadService` com o tipo pelo conteúdo (`finfo`), lista permitida, tamanho, nome `<uuid>.<ext>`, `FileName::clean` e `StoragePath::assertSafe`, mais `storage/` sem acesso web. `FileResponse` entrega com `nosniff`, `Content-Disposition` RFC 6266 e `sandbox`. Disco abstrato com `LocalDisk`. *S3 fica fora por enquanto.*
- [ ] **Exportação:** base `Export` (Template Method), `CsvExport` (BOM, `;` e proteção contra fórmula), `PdfExport` (FPDF vendorizado em `lib/fpdf`) e `XlsxExport` (o `XlsxWriter` do lorencine adaptado à base). Guia em `docs/exportacao.md`.

### Fase 5 — API
- [ ] Rotas `/api/v1/...` sem sessão, autenticadas por token guardado como SHA-256.
- [ ] `ApiRequest`, `ApiResponse` (formato padrão e erros com código), `FormRequest` respondendo 422 e a mesma classe de paginação da fase 3.
- [ ] Endpoint de exemplo, `docs/api/doc-api.md` + `openapi.yaml`, e um teste que compara a documentação com as rotas.
- [ ] *A decidir no design:* como o token é emitido (tela no admin?) e se há CORS.

### Fase 6 — Componentes JavaScript (sem dependências)
- [ ] **Máscaras** aplicadas por atributo (`data-mask="cpf"`): CPF, **CNPJ alfanumérico**, CEP, telefone, data, moeda, peso, decimal, inteiro, e **máscara customizada** (`data-mask="##.###-AA"`).
- [ ] **Calendário próprio:** mostra sempre `dd/mm/aaaa`, envia `aaaa-mm-dd` em campo oculto e não depende do idioma do navegador. Navegável pelo teclado.
- [ ] **Helper de AJAX** sobre `fetch`: JSON, CSRF automático e tratamento de erro. Uma resposta 422 marca os campos.
- [ ] **Formulário AJAX opcional:** envia por fetch e pinta os erros por campo vindos do servidor.
- [ ] **Toasts** e **alert/confirm** sóbrios, com Promise, substituindo o `confirm()` nativo.
- [ ] *Não é objetivo:* validação completa no cliente. O servidor é a fonte da verdade.

### Fase 7 — Design system
- [ ] Página no admin com os componentes, os tokens e **formulários de exemplo** usando FormRequest, máscaras, calendário, erros por campo, envio AJAX e toasts.
- [ ] Guia de uso e checklist "formulário novo" no CLAUDE.md.

## Fora do escopo (por enquanto)
- S3, Redis e fila (existem no mydaily, mas são específicos de lá).
- Multi-tenancy (o padrão continua só documentado no CLAUDE.md).
- Regras de negócio dos projetos de origem.
