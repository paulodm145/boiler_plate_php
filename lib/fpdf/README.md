# FPDF 1.86 (vendorizada)

Biblioteca de terceiros, sem mudanças, usada só por `App\Helpers\Export\Pdf\PdfDocument`
(regra "Exports" do CLAUDE.md; guia em `docs/exportacao.md`).

- Origem: https://github.com/Setasign/FPDF, tag `1.8.6` (FPDF 1.86, 2023-06-25),
  arquivo `1.8.6.tar.gz` com SHA-256 `7f09cdfc9ef75d53d79bb4d56fc7baefc80a9d87b38284f058fefe845dfe5414`.
- Trazidos: `fpdf.php`, `font/` (métricas das 14 fontes padrão do PDF) e `license.txt` (licença permissiva).
- Não edite estes arquivos. Para atualizar, troque-os pela versão nova, confira com `php -l` no PHP 8.2
  e rode `scripts/tests/pdf_export_test.php`.
- A FPDF não fala UTF-8: a conversão para Windows-1252 é feita só em `PdfDocument`.
