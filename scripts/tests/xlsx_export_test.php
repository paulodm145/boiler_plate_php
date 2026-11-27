<?php

declare(strict_types=1);

use App\Helpers\Export\XlsxExport;

echo "\nXlsxExport\n";

/** Partes do .xlsx gerado, por nome dentro do zip. */
function xlsxParts(XlsxExport $export): array
{
    $path = tempDir() . '/planilha.xlsx';
    file_put_contents($path, $export->toString());
    $zip = new ZipArchive();
    assertTrue($zip->open($path) === true, 'o .xlsx deveria ser um zip valido');
    $parts = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $parts[$name] = $zip->getFromName($name);
    }

    $zip->close();

    return $parts;
}

it('xlsx e um zip valido com as partes do OOXML', function (): void {
    $parts = xlsxParts(new XlsxExport(['Nome', 'Total'], [['Ana', 10]]));

    foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $part) {
        assertTrue(isset($parts[$part]), $part);
    }
});

it('colunas tipadas saem como numero e data do Excel', function (): void {
    $sheet = xlsxParts((new XlsxExport(['Qtd', 'Valor', 'Peso', 'Dia'], [[3, '1234.56', '1.5', '2026-11-24']]))
        ->types([0 => 'integer', 1 => 'money', 2 => 'decimal', 3 => 'date']))['xl/worksheets/sheet1.xml'];

    assertContains('<c r="A2" s="5"><v>3</v></c>', $sheet);
    assertContains('<c r="B2" s="3"><v>1234.56</v></c>', $sheet);
    assertContains('<c r="D2" s="2"><v>46350</v></c>', $sheet);
});

it('cabecalho congelado com autofiltro e texto escapado', function (): void {
    $sheet = xlsxParts(new XlsxExport(['Nome'], [['<b>Ana & Bia</b>']]))['xl/worksheets/sheet1.xml'];

    assertContains('state="frozen"', $sheet);
    assertContains('<autoFilter ref="A1:A2"/>', $sheet);
    assertContains('&lt;b&gt;Ana &amp; Bia&lt;/b&gt;', $sheet);
});

it('xlsx protege texto que comeca com =', function (): void {
    $sheet = xlsxParts(new XlsxExport(['A', 'B'], [['=1+1', -3]]))['xl/worksheets/sheet1.xml'];

    assertContains('&apos;=1+1', $sheet);
    assertFalse(str_contains($sheet, '<f>'));
});

it('xlsx com linha desalinhada lanca excecao', function (): void {
    assertThrows(fn () => (new XlsxExport(['A', 'B'], [['so uma']]))->toString());
});

it('tipo de coluna invalido lanca excecao', function (): void {
    assertThrows(fn () => (new XlsxExport(['A'], []))->types([0 => 'formula']));
});

it('httpHeaders do xlsx tem o tipo da planilha e a extensao', function (): void {
    $headers = (new XlsxExport(['A'], []))->httpHeaders('Relatório de vendas');

    assertContains('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', implode("\n", $headers));
    assertContains("filename*=UTF-8''Relat%C3%B3rio%20de%20vendas.xlsx", implode("\n", $headers));
});
