# Ferramentas de exportação

Toda exportação de arquivo passa por uma **ferramenta de exportação** em `app/Helpers/Export/`. Quem exporta pensa o mínimo possível: entrega o cabeçalho e os dados, e a ferramenta cuida do formato, da segurança e do download.

## Formatos

| Formato | Classe | Observação |
|---|---|---|
| CSV | `App\Helpers\Export\CsvExport` | abre certo no Excel em português |
| PDF (relatório tabular) | `App\Helpers\Export\PdfExport` | FPDF vendorizada em `lib/fpdf` |
| Excel (.xlsx) | `App\Helpers\Export\XlsxExport` | sem dependência; precisa da extensão `zip` |

## Onde fica cada parte

Quem decide **quais colunas e linhas** saem é o Service, porque isso é regra de negócio. O controller só entrega o arquivo:

```php
// app/Services/Client/ClientService.php
public function export(ListQuery $list): array
{
    $rows = [];

    foreach ($this->clients->all($list->search()) as $client) {
        $rows[] = [$client['name'], Br\Cnpj::format($client['cnpj']), Br\Date::format($client['created_at']), $client['credit_cents'] / 100];
    }

    return ['filename' => 'clientes', 'header' => ['Cliente', 'CNPJ', 'Cadastro', 'Crédito'], 'rows' => $rows];
}

// app/Controllers/AdminClientController.php
public function exportXlsx(): void
{
    $export = $this->clients->export(ListQuery::fromRequest(ClientRepository::SORTABLE, 'name'));

    (new XlsxExport($export['header'], $export['rows']))->types([3 => 'money'])->download($export['filename']);
}
```

`->toString()` no lugar de `->download()` serve para os testes e para anexar o arquivo num e-mail.

## Regras dos dados (todos os formatos)

- `$header` é uma lista de títulos. Cada item de `$rows` é uma linha com os valores **na mesma ordem do cabeçalho**. Uma linha associativa também serve: os valores são lidos na ordem em que estão.
- `null` vira célula vazia e `true`/`false` viram `1`/`0`.
- Uma linha que não é lista, ou que tem mais ou menos colunas que o cabeçalho, lança `InvalidArgumentException`. Isso é bug de quem montou as linhas: o erro aparece no teste, não numa planilha desalinhada.
- **Proteção contra fórmula, sempre ligada, no CSV e no XLSX:** texto que começa com `=`, `+`, `-`, `@`, tab ou CR ganha um `'` na frente, para que o que as pessoas digitam não vire fórmula. Números (`int`/`float`) saem como estão, então `-3` continua `-3`. Não há opção para desligar, de propósito.
- Nome do arquivo: a extensão entra sozinha; aspas, barras, `;` e quebras de linha são removidos; os acentos são preservados (`filename*` UTF-8).

## CSV

```php
(new CsvExport(['Pessoa', 'Horas'], [['Ana', '1h30']]))->download('horas');
(new CsvExport($header, $rows))->separator(',')->withoutBom()->download('dados');
```

O padrão é BOM UTF-8, separador `;`, CRLF e aspas só quando o valor tem separador, aspas ou quebra de linha. No CSV, datas, dinheiro e "Sim/Não" já devem chegar formatados como texto pelo Service.

## PDF (relatório tabular)

```php
(new PdfExport(['Pessoa', 'Horas', 'Valor'], $linhas))
    ->title('Horas do mês')
    ->subtitle('Outubro de 2026')   // opcional
    ->alignRight([1, 2])            // colunas numéricas (índice a partir de 0)
    ->landscape()                   // opcional
    ->download('horas');
```

Todo relatório sai com o mesmo visual:
- título e subtítulo;
- cabeçalho cinza repetido a cada página;
- linhas zebradas;
- rodapé com a data e "página X de Y".

As colunas ganham largura proporcional ao conteúdo, e o texto que não cabe termina em "…". Escreva sempre em UTF-8: a conversão para Windows-1252, que a FPDF exige, fica em `Pdf\PdfText`. O que não existe em Windows-1252 (emoji, ideogramas) vira `?`. Relatório fora do padrão usa `Pdf\PdfDocument` diretamente, pelos métodos `cellText()`/`textWidth()`/`fit()`.

## Excel (.xlsx)

```php
(new XlsxExport(['Cliente', 'Qtd', 'Peso (kg)', 'Total', 'Data'], $linhas, 'Vendas'))
    ->types([1 => 'integer', 2 => 'decimal', 3 => 'money', 4 => 'date'])
    ->widths([0 => 40])
    ->download('vendas');
```

- Os tipos de coluna são `text` (padrão), `integer`, `decimal` (até 3 casas), `money` (2 casas com milhar; passe **reais**, não centavos: `$cents / 100`), `percent` e `date` (`Y-m-d` ou `Y-m-d H:i:s`).
- Números e datas vão como valores, então o Excel soma, filtra e ordena.
- O cabeçalho fica congelado e com autofiltro. A aba tem no máximo 31 caracteres.
- Precisa da extensão `zip` do PHP, que o `/install` confere.

## Formato novo

As ferramentas seguem o padrão **Template Method**: `Export` (abstrata) faz o que é igual para todo formato, ou seja, o nome do arquivo, os cabeçalhos HTTP (`Content-Type`, `Content-Disposition` com nome acentuado, `nosniff`), o download, a validação das linhas (`tableRows()`) e a proteção contra fórmula (`protectFormula()`). Para criar um formato novo:

1. Crie `app/Helpers/Export/<Formato>Export.php` estendendo `Export`.
2. Implemente `toString()`, `contentType()` e `extension()`.
3. Receba pelo construtor só o essencial. O resto vira opção fluente, com padrão pronto.
4. Lance `InvalidArgumentException` para uso errado.
5. Escreva antes o teste `scripts/tests/<formato>_export_test.php` e registre em `scripts/test_install.php`.
6. Atualize a tabela de formatos deste guia no mesmo PR.

Sem dependência externa, porque o deploy é por FTP, sem Composer. Se um formato precisar de biblioteca, ela entra vendorizada em `lib/<nome>/`, como a FPDF: sem mudanças, com README de origem e checksum, e com uma única classe do projeto que a esconde.
