# Fase 4: Infraestrutura, plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** um único jeito de guardar e entregar arquivos (`App\Storage`, `UploadService`, `FileResponse`), de exportar (`App\Helpers\Export`: CSV, PDF, XLSX) e de mandar e-mail (`App\Mail`: `Mailable`, `Mailer`, transportes).

**Architecture:** portar do mydaily (`~/Projetos/mydaily`: `app/Infrastructure/Storage`, `app/Services/Storage/UploadService.php`, `app/Http/FileResponse.php`, `app/Helpers/{FileName,ContentDisposition,UploadedFiles}.php`, `app/Helpers/Export/**`, `lib/fpdf`, e os testes correspondentes em `scripts/tests/`) e do lorencine (`~/Projetos/lorencine/sistema/app/Core/XlsxWriter.php` + `xlsx_writer_test.php`), sem S3, sem `Disks` com vários discos, sem URL assinada e sem `ImageMetadata`. O e-mail é novo, por cima do PHPMailer já vendorizado.

**Tech Stack:** PHP 8.2, PHPMailer 7.1.1 (`lib/PHPMailer`), FPDF 1.86 (`lib/fpdf`), `ZipArchive`, `finfo`.

**Spec:** `docs/superpowers/specs/2026-11-27-fase-4-infraestrutura-design.md`

## Global Constraints

- Identificadores em inglês e ASCII. Códigos de falha em inglês snake_case. Mensagens ao usuário em pt-BR com acento.
- Ao portar, trocar `App\Infrastructure\Storage` por `App\Storage` e `App\Services\Storage\StoredFile` por `App\Storage\StoredFile`, traduzir para o inglês os códigos `ServiceResult` em português e remover as referências a S3, `Disks`, `SignsDownloads`, organização, equipe e specs do mydaily.
- O tipo de arquivo é sempre `finfo` sobre o conteúdo. A extensão gravada vem do mapa, nunca do nome enviado.
- SVG e HTML nunca são `inline`. Toda entrega de arquivo leva `nosniff` + `Content-Security-Policy: sandbox; default-src 'none'`.
- Texto de célula começando com `= + - @`, tab ou CR é protegido em CSV e XLSX.
- O PHPMailer só é incluído (`require_once`) por `App\Mail\SmtpTransport`. A FPDF só é incluída por `App\Helpers\Export\Pdf\PdfDocument`.
- Testes registrados em `scripts/test_install.php`. Suíte: `docker compose exec -T --user www-data app php scripts/test_install.php`, sozinha.
- Commits atômicos, semânticos, no infinitivo, com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Arquivo `.png` que é HTML/SVG** enviado como imagem: `invalid_type` no upload; se já estiver gravado com `text/html`, o `FileResponse` entrega como `attachment` com `sandbox`. Testes nas Tasks 1 e 2.
2. **Nome de arquivo com `../`, `\r\n`, aspas ou U+202E** no upload e no download: nunca vira caminho, e o cabeçalho sai em uma linha só. Testes na Task 1.
3. **Assunto ou nome do destinatário com `\r\n`** (injeção de cabeçalho): sai em uma linha só. Teste na Task 4.
4. **Linha de exportação com mais ou menos colunas que o cabeçalho**: `InvalidArgumentException`, nunca uma planilha desalinhada. Teste na Task 3.
5. **SMTP fora do ar**: `Mailer::send` devolve `false` e registra warning no log, e a página do "esqueci a senha" continua respondendo normalmente. Teste na Task 4.

---

### Task 1: armazenamento e upload

**Files:**
- Create: `app/Storage/{MimeTypes,Disk,LocalDisk,StoragePath,StoredFile}.php`, `app/Helpers/{FileName,ContentDisposition,UploadedFiles}.php`, `app/Services/Storage/UploadService.php`, `storage/uploads/.htaccess` (`Require all denied`)
- Modify: `app/Validation/Validator.php` (`ruleMimes` passa a usar `MimeTypes::matches()`, sem o `MIME_TYPES` local)
- Test: `scripts/tests/{local_disk,upload_service,uploaded_files,mime_types}_test.php`

**Interfaces:**
- Produces:
  - `MimeTypes`:
    - `const IMAGES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp']`;
    - `const DOCUMENTS = ['application/pdf' => 'pdf']`;
    - `const SPREADSHEETS = ['text/csv' => 'csv', 'text/plain' => 'csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx']`;
    - `const INLINE_SAFE = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']`;
    - `detect(string $path): string` (finfo);
    - `matches(string $path, array $extensions): bool` (extensão → MIMEs aceitos; mapa extensão → MIME com `jpg|jpeg`, `png`, `gif`, `webp`, `pdf`, `csv|txt` → `text/plain|text/csv`, `xlsx` → planilha|`application/zip`, `zip`);
    - `isInlineSafe(string $mime): bool`.
  - `Disk`: `name(): string`, `put(string $path, string $localFile): void`, `exists(string $path): bool`, `size(string $path): ?int`, `delete(string $path): void`, `readStream(string $path)` (resource|null), `absolutePath(string $path): string`.
  - `LocalDisk::__construct(string $root)`, com `name()` = `'local'`.
  - `StoragePath::assertSafe(string $path): void`, que lança `InvalidArgumentException`.
  - `StoredFile` (readonly): `Disk $disk`, `string $path`, `string $mimeType`, `string $downloadName`, `bool $inline`, `?int $size`; `exists(): bool`.
  - `FileName::clean(string $name): string`, `ContentDisposition::header(string $disposition, string $name): string` e `UploadedFiles::fromField(?array $field): array`, como no mydaily.
  - `UploadService::__construct(?Disk $disk = null, ?string $stagingDirectory = null, ?callable $mover = null)`:
    - os padrões são `LocalDisk(storage/uploads)`, `storage/uploads/.staging` e `move_uploaded_file`;
    - `store(?array $file, string $folder, int $maxBytes, array $allowedMimeToExt): ServiceResult`;
    - `ok(['path' => "<folder>/<AAAA>/<MM>/<uuid>.<ext>", 'disk' => 'local', 'mime', 'size', 'original_name'])`;
    - falhas: `missing_file` (nulo, `[]` ou `UPLOAD_ERR_NO_FILE`), `upload_failed` (outro erro do PHP, ou o mover devolve false), `file_too_large` (`filesize` > max, ou `UPLOAD_ERR_INI_SIZE`/`FORM_SIZE`), `invalid_type` e `could_not_save`;
    - `removeStaleStaging(int $beforeTimestamp): int`;
    - `StoragePath::assertSafe($folder)`.
  - `Validator::ruleMimes` = `isFile && MimeTypes::matches($tmp, $params)`.

- [ ] **Step 1: Portar e adaptar os testes** do mydaily: `local_disk_test` (só os casos de `LocalDisk`/`StoragePath`) e `upload_service_test`. Os casos de passagem, de nome `<uuid>.<ext>`, de tamanho real e de MIME pelo conteúdo ficam. Saem os de S3, EXIF e `Disks`. Os códigos passam para o inglês.
  - Acrescentar:
    - `it('png que na verdade e html e recusado')`;
    - `it('nome original com ../ e \\r\\n e limpo e nao entra no caminho gravado')`;
    - `it('StoragePath recusa ../x, /x, a\\b, a//b e byte nulo')`.
  - `uploaded_files_test` como está.
  - `mime_types_test`:
    - `detect` de um PNG real dá `image/png`;
    - `matches` com `['jpg']` de um PNG dá false;
    - `isInlineSafe('image/svg+xml')` dá false.
- [ ] **Step 2: Rodar.** Esperado: falhas por classes inexistentes.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde**, incluindo o `validator_test` (`mimes`).
- [ ] **Step 5: Commit** `feat(arquivos): adicionar armazenamento local e UploadService com tipo pelo conteudo`.

### Task 2: `FileResponse`

**Files:**
- Create: `app/Http/FileResponse.php`
- Test: `scripts/tests/file_response_test.php` (portado e adaptado)

**Interfaces:**
- Consumes: `StoredFile`, `MimeTypes::isInlineSafe`, `ContentDisposition::header`.
- Produces:
  - `FileResponse::send(StoredFile $file, string $cacheControl = 'private, no-store'): void`: se `!$file->exists()`, chama `ErrorPage::notFound()`; senão envia os cabeçalhos e faz streaming em blocos de 8 KB com `readStream`;
  - `FileResponse::headersFor(StoredFile $file, string $cacheControl): array<string,string>`: `Content-Type`, `Content-Length` (se o `size` for conhecido), `X-Content-Type-Options: nosniff`, `Content-Disposition` (`inline` só se `$file->inline && MimeTypes::isInlineSafe($file->mimeType)`, senão `attachment`), `Content-Security-Policy: sandbox; default-src 'none'` e `Cache-Control`.

- [ ] **Step 1: Testes:**
  - PDF inline tem `sandbox` e `inline`;
  - um `StoredFile` com `text/html` e `inline=true` sai como `attachment`;
  - `image/svg+xml` sai como `attachment`;
  - nome `relatório.pdf` gera `filename*=UTF-8''relat%C3%B3rio.pdf`;
  - nome com `\r\n` sai em uma linha.
- [ ] **Step 2: Rodar.** Esperado: falha.
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(arquivos): adicionar FileResponse para entregar arquivos com cabecalhos seguros`.

### Task 3: exportação (CSV, PDF e XLSX)

**Files:**
- Create: `app/Helpers/Export/{Export,CsvExport,PdfExport,XlsxExport}.php`, `app/Helpers/Export/Pdf/{PdfDocument,PdfText}.php`, `lib/fpdf/` (cópia de `~/Projetos/mydaily/lib/fpdf`, com `README.md` de origem/SHA-256), `lib/.htaccess` (`Require all denied`, se não existir), `docs/exportacao.md` (do mydaily, adaptado: tabela com os 3 formatos e a seção "Como exportar em Excel")
- Modify: `app/Core/Requirements.php` (requisito `ZipArchive`: `class_exists('ZipArchive')`, rótulo `Extensão zip`)
- Test: `scripts/tests/{csv_export,pdf_export,xlsx_export}_test.php`, e o `requirements_test` (rótulo do zip)

**Interfaces:**
- Produces:
  - `abstract class Export`: `abstract toString(): string`, `abstract protected contentType(): string`, `abstract protected extension(): string`, `download(string $name): void`, `httpHeaders(string $name): array` e `fileName(string $name): string`;
  - `CsvExport::__construct(array $header, array $rows)`, `separator(string): self`, `withoutBom(): self`;
  - `PdfExport::__construct(array $header, array $rows)`, `title()`, `subtitle()`, `landscape()`, `alignRight(array)`, e `static columnWidths(array $natural, array $rightAligned, float $available): array`;
  - `XlsxExport::__construct(array $header, array $rows, string $sheetName = 'Planilha')`:
    - `types(array $typesByIndex): self`, com os tipos `text|integer|decimal|money|percent|date`, padrão `text`;
    - `widths(array $widthsByIndex): self`;
    - `toString()` gera o arquivo num temporário com `ZipArchive` e devolve os bytes;
    - célula `text` com fórmula é protegida com `'`;
    - os valores de `date` aceitam `Y-m-d`;
    - `decimal`/`money` aceitam número ou string decimal;
    - linha desalinhada lança `InvalidArgumentException`, como o CSV.

- [ ] **Step 1: Portar os testes** `csv_export_test`, `pdf_export_test` e, do lorencine, `xlsx_writer_test`, reescrito como `xlsx_export_test` contra a API acima. Acrescentar:
  - `it('xlsx protege texto que comeca com =')`: abrir o zip e conferir `'=1+1` em `xl/sharedStrings.xml` ou no inline string;
  - `it('xlsx com linha desalinhada lanca excecao')`;
  - `it('xlsx e um zip valido com as partes do OOXML')`: `[Content_Types].xml`, `xl/workbook.xml` e `xl/worksheets/sheet1.xml`.
- [ ] **Step 2: Rodar.** Esperado: falhas.
- [ ] **Step 3: Implementar** e copiar a `lib/fpdf`.
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Commit** `feat(exportacao): adicionar exportacao padronizada em CSV, PDF e Excel`.

### Task 4: e-mail

**Files:**
- Create: `app/Mail/{Message,Mailable,Transport,SmtpTransport,LogTransport,ArrayTransport,Mailer,SmtpConfig,PasswordResetMail}.php`, `app/Views/emails/_layout.php`, `app/Views/emails/password-reset.php`
- Modify:
  - `app/Services/Auth/PasswordResetService.php` (sem `?MailerInterface`; usa `Mailer::send(new PasswordResetMail($link))`);
  - `app/Services/Settings/SettingsService.php` (`SmtpConfig::envOverriddenKeys()`);
  - `scripts/tests/admin_password_reset_test.php` e `password_reset_service_test.php` (usam `Mailer::fake()`);
  - `scripts/tests/mailer_test.php` (vira o teste de `SmtpConfig` + `SmtpTransport::configure`);
  - `.env.example` (`MAIL_TRANSPORT=` comentado: `smtp|log`).
- Delete: `app/Core/Mail/MailerInterface.php`, `app/Core/Mail/SmtpMailer.php`
- Test: `scripts/tests/mail_test.php`

**Interfaces:**
- Produces:
  - `Message`:
    - `__construct(array $to, string $subject, string $html, string $text, array $cc = [], array $bcc = [], ?string $replyTo = null, array $attachments = [])`;
    - os endereços são `string` ou `['address' => ..., 'name' => ...]`, normalizados para `[['address' => ..., 'name' => ...]]`;
    - um endereço inválido (`filter_var`) lança `InvalidArgumentException`;
    - o assunto e os nomes têm `\r`/`\n` trocados por espaço;
    - getters públicos readonly.
  - `abstract class Mailable`:
    - `abstract public function subject(): string` e `abstract protected function view(): string` (nome do template em `app/Views/emails/`);
    - `protected function data(): array` (padrão `[]`), `protected function attachments(): array` (padrão `[]`) e `protected function text(): ?string` (padrão `null`, gerado do HTML);
    - `public function to(string|array $address, ?string $name = null): static` e `cc()`/`bcc()`/`replyTo()` análogos;
    - `public function build(): Message`: renderiza a view com `View::capture()` dentro de `emails/_layout.php`, com as variáveis `$content`, `$subject` e `$siteName`;
    - `public static function textFromHtml(string $html): string`: `<br>`/`</p>`/`</div>`/`</li>`/`</h*>` viram `\n`, `<a href="u">t</a>` vira `t (u)`, `strip_tags`, entidades decodificadas e espaços aparados.
  - `interface Transport { public function send(Message $message): bool; }`.
  - `ArrayTransport` (`$messages` público, `last(): ?Message`) e `LogTransport` (`Logger::info('E-mail (log)', [...to, subject, text])`).
  - `SmtpTransport::__construct(?array $config = null)`, com padrão `SmtpConfig::current()`. `configure(PHPMailer $mail, Message $m): void` é público para teste. O `send()` lança nada: captura `PHPMailer\Exception`, faz `Logger::warning` e devolve `false`.
  - `SmtpConfig::ENV_KEYS`, `resolve(array $settings): array` (o antigo `resolveConfig`), `envOverriddenKeys(): array` e `current(): array` (settings + env). As regras de `SMTP_PASSWORD` + `SMTP_HOST` e de 587/tls ficam idênticas à fase 1.
  - `Mailer`:
    - `static send(Mailable $mail): bool`;
    - `static useTransport(Transport $t): void`;
    - `static fake(): ArrayTransport`;
    - `static reset(): void`;
    - `static defaultTransport(): Transport`: `MAIL_TRANSPORT=log` → Log; `smtp` → Smtp; vazio → Smtp se `SmtpConfig::current()['smtp_host']` não for vazio, senão Log;
    - se o transporte devolver `false` ou lançar, `Logger::warning('E-mail nao enviado', ['to' => ..., 'subject' => ...])` e devolve `false`.
  - `PasswordResetMail::__construct(string $link)`: assunto "Redefinição de senha" e view `password-reset`, com o link e a validade de 60 minutos.

- [ ] **Step 1: Testes** (`mail_test.php`):
  - `build` renderiza o layout com o `site_name` e os dados escapados (`<script>` vira `&lt;script&gt;`);
  - `textFromHtml` com parágrafos e link (`'<p>Oi</p><p><a href="https://x.test/a">clique</a></p>'` → `"Oi\nclique (https://x.test/a)"`);
  - assunto com `"A\r\nBcc: x@y.com"` vira `'A  Bcc: x@y.com'`, numa linha;
  - destinatário inválido lança;
  - `Mailer::fake()` + `send` guarda uma mensagem com o destinatário;
  - transporte que devolve `false` faz o `send` devolver `false` e o log ganhar uma linha `WARNING: E-mail nao enviado` (com `Logger::configure` num tempDir);
  - `defaultTransport` com `MAIL_TRANSPORT=log` é `LogTransport`;
  - `SmtpTransport::configure` define `CharSet` UTF-8, `isHTML`, `AltBody` igual ao texto e um anexo por `content`.
  
  Mais os testes de `SmtpConfig` migrados do `mailer_test.php`.
- [ ] **Step 2: Rodar.** Esperado: falhas.
- [ ] **Step 3: Implementar**, migrar o `PasswordResetService` e os testes dele, e apagar `app/Core/Mail`. `grep -rn 'Core\\\\Mail' app scripts` precisa voltar vazio.
- [ ] **Step 4: Suíte verde.**
- [ ] **Step 5: Ponta a ponta:** instalação no `app_e2e` (recriar o container antes, porque o git troca o inode do `.env.docker`), `MAIL_TRANSPORT=log` e "esqueci a senha" de um e-mail existente: o log mostra o e-mail com o link. Restaurar o `.env.docker` no fim.
- [ ] **Step 6: Commit** `feat(email): adicionar Mailable e Mailer com transportes SMTP, log e fake`.

### Task 5: documentação

**Files:**
- Modify: `CLAUDE.md` (Architecture: as camadas `App\Mail` e `App\Storage`; a exceção do PHPMailer passa a citar `App\Mail\SmtpTransport`, e a FPDF entra como segunda exceção vendorizada; seções novas "E-mail", "Files (upload & download)" e "Export", cada uma com exemplo curto e as regras), `docs/superpowers/specs/2026-11-27-atualizacao-do-boilerplate-roteiro.md` (marcar a Fase 4)

- [ ] **Step 1: Editar.**
- [ ] **Step 2: Suíte verde.**
- [ ] **Step 3: Commit** `docs(claude): documentar e-mail, arquivos e exportacao`.
