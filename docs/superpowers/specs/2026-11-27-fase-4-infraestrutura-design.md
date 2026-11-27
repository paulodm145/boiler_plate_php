# Fase 4: Infraestrutura, com e-mail, upload e exportação (design)

Data: 2026-11-27. Roteiro: `2026-11-27-atualizacao-do-boilerplate-roteiro.md`. Branch: `feature/fase-4-infraestrutura`.

## Intenção

Três coisas que todo projeto derivado acaba precisando passam a ter **um único jeito** de ser feitas:

- **Mandar e-mail**, com classes próprias no estilo dos Mailables do Laravel, escondendo o PHPMailer.
- **Receber arquivos com segurança.** O tipo é detectado pelo conteúdo, nunca pela extensão. O nome do arquivo é gerado pelo servidor e o arquivo fica fora do acesso web.
- **Exportar** CSV, PDF e Excel com o mesmo visual e as mesmas proteções.

As três vêm do que o mydaily e o lorencine já resolveram.

## Decisões já tomadas (conversa com o dono)

- E-mail com classes próprias que abstraem o PHPMailer (vendorizado em `lib/PHPMailer`), "semelhante ao Laravel".
- Upload com o formato checado pelo conteúdo do arquivo, além dos outros pontos de segurança.
- Exportação padronizada como no mydaily, com o Excel do lorencine.
- S3, Redis e fila ficam fora, como fases independentes depois.
- Helpers sem teste unitário, mas as classes de infraestrutura têm teste: segue o padrão dos Services e dos Repositories.

## Objetivos

1. `Mailable` mais um `Mailer` com transporte trocável: SMTP via PHPMailer, `LogMailer` para dev e `ArrayMailer` para os testes. O e-mail sai em HTML e em texto, com layout padrão e anexos.
2. A recuperação de senha passa a usar um `Mailable` (`PasswordResetMail`).
3. `UploadService`:
   - lista permitida de MIME real → extensão;
   - tamanho máximo;
   - nome `<uuid>.<ext>`;
   - pasta de passagem;
   - grava num disco (`LocalDisk`) em `storage/uploads/`, que não é servido pela web;
   - devolve `ServiceResult` com os metadados.
4. `FileResponse`, que é **o único** caminho para entregar um arquivo guardado. Usa os cabeçalhos seguros (`nosniff`, `Content-Disposition` RFC 6266, CSP `sandbox`), `inline` só para tipos seguros, e faz streaming.
5. `Export`, a base abstrata (Template Method), com `CsvExport`, `PdfExport` (FPDF vendorizado) e `XlsxExport` (o `XlsxWriter` do lorencine), mais o guia `docs/exportacao.md`.
6. A regra `mimes` do `Validator` e o `UploadService` usam o **mesmo** mapa de MIME, numa só fonte.

## Não-objetivos

- S3, URL assinada e fila de e-mail (envio depois da resposta). Isso fica para as fases independentes.
- Tirar metadados EXIF das imagens (`ImageMetadata` do mydaily) e redimensionar.
- Tela de anexos no admin. A fase 7 (design system) mostra um exemplo de upload.
- ZIP e TXT.

## Anti-objetivos

- Confiar na extensão do nome ou no `type` enviado pelo navegador.
- Gravar o arquivo com o nome escolhido pelo usuário, ou dentro de `public/`.
- Entregar HTML ou SVG enviado pelo usuário com `inline` sem `sandbox`, porque isso é XSS armazenado.
- Cabeçalho de e-mail montado com entrada sem limpeza (injeção de cabeçalho via `\r\n`).
- Planilha exportada que execute fórmula vinda do usuário (CSV/XLSX injection).

## Peças

### E-mail (`App\Mail`)

- `App\Mail\Message`, o envelope imutável:
  - campos `to` (lista), `cc`, `bcc`, `replyTo`, `subject`, `html`, `text` e `attachments` (`['path' => ..., 'name' => ...]` ou `['content' => ..., 'name' => ..., 'mime' => ...]`);
  - endereços validados com `filter_var`;
  - `\r` e `\n` removidos do assunto e dos nomes.
- `abstract class App\Mail\Mailable`:
  - `abstract subject(): string` e `abstract view(): string` (template em `app/Views/emails/<nome>.php`);
  - `data(): array`;
  - opcionais `attachments(): array` e `text(): ?string` (se for `null`, o texto é gerado do HTML: as tags saem, `<br>`/`</p>` viram quebra e os links viram "texto (url)");
  - `to(string|array $address): static`;
  - `build(): Message`, que renderiza a view dentro de `app/Views/emails/_layout.php` com `View::capture()`.
- `interface App\Mail\Transport { public function send(Message $message): bool; }`, com três implementações:
  - `SmtpTransport`, que concentra o PHPMailer. Reaproveita a configuração de `SmtpMailer::resolveConfig()`/`envOverrides()` da fase 1, com a regra do `SMTP_PASSWORD` mantida. Usa CharSet UTF-8, `isHTML(true)` com `AltBody` e os anexos;
  - `LogTransport`, que grava o e-mail no `Logger` (nível info, sem anexos binários). É o padrão quando o SMTP não está configurado;
  - `ArrayTransport`, para os testes, que guarda as mensagens.
- `App\Mail\Mailer`:
  - `Mailer::send(Mailable $mail): bool`;
  - `Mailer::fake(): ArrayTransport` (testes);
  - `Mailer::useTransport(Transport $t)`.
  - O transporte padrão vem de `MAIL_TRANSPORT=smtp|log` no `.env`, e sem valor fica `smtp` se houver host configurado, senão `log`.
  - Quando o envio falha, devolve `false` e registra no `Logger` (warning), sem lançar exceção. Isso troca o `error_log` que ficou pendente na fase 1.
- O `App\Core\Mail\MailerInterface`/`SmtpMailer` antigos: o `SmtpMailer::resolveConfig()` e o `envOverriddenKeys()` vão para `App\Mail\SmtpConfig`, que a tela de configurações já consome. A interface antiga sai, e o `PasswordResetService` passa a usar `Mailer`. *(Decisão do Claude: uma abstração só.)*
- `app/Views/emails/_layout.php` traz o cabeçalho com o `site_name`, o corpo e o rodapé. Usa CSS inline simples (clientes de e-mail ignoram `<style>` externo). `app/Views/emails/password-reset.php` é o primeiro template.

### Upload e arquivos (`App\Storage`, `App\Http\FileResponse`)

- `App\Storage\Disk` (interface): `name()`, `put(string $path, string $localFile)`, `exists`, `size`, `delete`, `readStream(string $path)` e `absolutePath(string $path): ?string`.
- `App\Storage\LocalDisk` grava sob uma raiz (`storage/uploads`), com pastas criadas em `0775`.
- `App\Storage\StoragePath::assertSafe(string $path)` recusa `..`, `/` inicial, `\`, byte nulo e segmentos vazios.
- `App\Storage\StoredFile` (readonly): `disk`, `path`, `mimeType`, `downloadName`, `inline` e `size`.
- `App\Storage\MimeTypes`: o **mapa único** extensão ↔ MIME real, usado pelo `Validator::ruleMimes` e pelo `UploadService`.
  - Grupos prontos: `IMAGES` (jpg/png/gif/webp), `DOCUMENTS` (pdf), `SPREADSHEETS` (csv/xlsx).
  - Os tipos seguros para `inline` são imagens raster e PDF. **Nunca SVG ou HTML.**
- `App\Services\Storage\UploadService`:
  - `store(?array $file, string $folder, int $maxBytes, array $allowedMimeToExt): ServiceResult`;
  - códigos de falha: `missing_file`, `upload_failed` (os `UPLOAD_ERR_*`), `file_too_large`, `invalid_type` e `could_not_save`;
  - fluxo: confere o erro do PHP, `is_uploaded_file`, o tamanho real (`filesize`), o MIME por `finfo` e a lista permitida. Depois move para a pasta de passagem com `move_uploaded_file` (um callable trocável nos testes), grava no disco como `<folder>/<AAAA>/<MM>/<uuid>.<ext>` e apaga a passagem;
  - `ok` devolve `['path', 'disk', 'mime', 'size', 'original_name' => FileName::clean(...)]`;
  - `removeStaleStaging(int $before)` limpa a passagem.
- `App\Helpers\FileName::clean()` (nome sem pastas, controles e U+202E) e `App\Helpers\ContentDisposition::header()` (RFC 6266), portados do mydaily. `App\Helpers\UploadedFiles::fromField()` normaliza `$_FILES` com vários arquivos.
- `App\Http\FileResponse::send(StoredFile $file, string $cacheControl = 'private, no-store')`:
  - cabeçalhos `Content-Type` gravado, `Content-Length`, `X-Content-Type-Options: nosniff`, `Content-Disposition` (inline ou attachment), `Content-Security-Policy: sandbox; default-src 'none'` e `Cache-Control`;
  - `inline` só se o tipo estiver na lista segura; se não estiver, `attachment`;
  - streaming em blocos;
  - 404 via `ErrorPage` se o arquivo não existir;
  - `headersFor()` é pura, para testar.
- `storage/uploads/.htaccess` com `Require all denied`, que já é coberto pelo `.htaccess` da raiz (fase 1), como defesa extra. Requisito do instalador: pasta gravável (já existe).

### Exportação (`App\Helpers\Export`)

Portada do mydaily, com o guia `docs/exportacao.md` adaptado:

- `abstract class Export`:
  - `toString()`, `contentType()` e `extension()`;
  - `download(string $name)` envia os cabeçalhos (tipo, `Content-Disposition` com nome acentuado, `nosniff`) e o conteúdo;
  - `fileName()` limpa o nome e acrescenta a extensão.
- `CsvExport`:
  - BOM, separador `;`, CRLF;
  - **proteção contra fórmula sempre ligada:** texto que começa com `= + - @`, tab ou CR ganha `'`;
  - linha desalinhada lança `InvalidArgumentException`.
- `PdfExport` (título, subtítulo, paisagem, colunas à direita, zebra, cabeçalho repetido e "página X de Y"), `Pdf\PdfDocument` (a única classe que fala com a FPDF) e `Pdf\PdfText` (UTF-8 → Windows-1252). A FPDF 1.86 fica vendorizada em `lib/fpdf/`, com README de origem e SHA-256, copiada do mydaily.
- `XlsxExport`:
  - o `XlsxWriter` do lorencine (OOXML à mão + `ZipArchive`) adaptado à base `Export`;
  - colunas tipadas (`text`, `integer`, `decimal`, `money`, `percent`, `date`), cabeçalho congelado e autofiltro;
  - a mesma proteção contra fórmula nas células de texto;
  - requisito: extensão `zip`. O `Requirements` passa a conferir `ZipArchive`.

## Testes (TDD)

Portar e adaptar do mydaily os testes `csv_export_test`, `pdf_export_test`, `upload_service_test`, `file_response_test`, `local_disk_test` e `uploaded_files_test`. Do lorencine, o `xlsx_writer_test`. Acrescentar:

- **`Mailable`:**
  - `build()` renderiza o HTML no layout com os dados escapados;
  - o texto gerado do HTML;
  - o assunto com `\r\n` sai numa linha só;
  - um endereço inválido lança `InvalidArgumentException`.
- **`Mailer`:**
  - com `fake()`, o `send` guarda a mensagem;
  - sem SMTP configurado, o transporte padrão é o `log`;
  - uma falha do transporte devolve `false` e gera uma linha de warning no log.
- **`SmtpTransport::configure()`:** UTF-8, HTML + AltBody, anexos e as regras de `SmtpConfig`. Os testes do `mailer_test` da fase 1 migram para cá.
- **`PasswordResetService`:** envia um `PasswordResetMail` com o link e o destinatário certos. Os testes atuais passam a usar `Mailer::fake()`.
- **`UploadService`:**
  - PNG real aceito;
  - `.png` que é texto recusado (`invalid_type`);
  - grande demais (`file_too_large`);
  - `UPLOAD_ERR_NO_FILE` (`missing_file`);
  - caminho que não veio de upload (`upload_failed`);
  - o nome gravado é `<uuid>.<ext>`, sem nada do nome original;
  - a passagem fica limpa depois.
- **`StoragePath::assertSafe`** recusa `../x`, `/x`, `a\b` e `a/../b`.
- **`FileResponse::headersFor`:**
  - SVG e HTML nunca são `inline`;
  - PDF `inline` tem `sandbox`;
  - nome com acento vira `filename*=UTF-8''...`.
- **`Validator::mimes`** continua passando, agora lendo o `MimeTypes`.
- **`CsvExport` e `XlsxExport`:** um texto `=1+1` sai protegido.

**Ponta a ponta:**
- "esqueci a senha" com `MAIL_TRANSPORT=log`: o e-mail completo aparece em `storage/logs/`;
- um script descartável envia um PNG e um PDF por um formulário de teste local (não commitado) e baixa os dois pelo `FileResponse`, conferindo os cabeçalhos;
- um CSV, um PDF e um XLSX gerados abrem: o CSV no Excel/LibreOffice, o PDF em leitor PDF e o XLSX por `unzip -t`/LibreOffice headless, se houver.

## Decisões do Claude (confira ao ler)

- O `App\Core\Mail\MailerInterface`/`SmtpMailer` são substituídos por `App\Mail\*`, e a configuração SMTP vira `App\Mail\SmtpConfig`. Fica uma abstração só.
- Sem SMTP configurado, o e-mail vai para o log, em vez de ser descartado em silêncio.
- Namespaces novos: `App\Mail` e `App\Storage` (o mydaily usava `App\Infrastructure`). Ficam mais curtos e diretos. *Se preferir `App\Infrastructure\Mail` e `App\Infrastructure\Storage`, troco.*
- Caminho gravado: `<pasta>/<AAAA>/<MM>/<uuid>.<ext>`, para não acumular milhares de arquivos numa pasta só.
- Sem remoção de EXIF agora (o `ImageMetadata` do mydaily depende do GD e de decisões por tipo de imagem). Fica para quando houver upload de foto de perfil.
