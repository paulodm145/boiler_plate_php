<?php

declare(strict_types=1);

use App\Helpers\Export\CsvExport;

echo "\nCsvExport\n";

it('o csv sai com BOM, ponto e virgula e CRLF', function (): void {
    $csv = (new CsvExport(['Pessoa', 'Horas'], [['Ana', '1h30']]))->toString();

    assertSame("\xEF\xBB\xBFPessoa;Horas\r\nAna;1h30\r\n", $csv);
});

it('valor com separador, aspas ou quebra de linha vai entre aspas', function (): void {
    $csv = (new CsvExport(['A', 'B'], [['Bruno; o "Bru"', "a\nb"]]))->withoutBom()->toString();

    assertSame("A;B\r\n\"Bruno; o \"\"Bru\"\"\";\"a\nb\"\r\n", $csv);
});

it('texto que comeca como formula ganha apostrofo', function (): void {
    $csv = (new CsvExport(['A', 'B', 'C', 'D', 'E'], [['=SOMA(A1)', '+x', '@y', '-Ana', "\tQA"]]))
        ->withoutBom()
        ->toString();

    assertSame("A;B;C;D;E\r\n'=SOMA(A1);'+x;'@y;'-Ana;'\tQA\r\n", $csv);
});

it('numero negativo nao ganha apostrofo mas texto negativo ganha', function (): void {
    $csv = (new CsvExport(['A', 'B', 'C'], [[-3, -1.5, '-3']]))->withoutBom()->toString();

    assertSame("A;B;C\r\n-3;-1.5;'-3\r\n", $csv);
});

it('nulo vira vazio e booleano vira 1 ou 0', function (): void {
    $csv = (new CsvExport(['A', 'B', 'C', 'D'], [['a', null, true, false]]))->withoutBom()->toString();

    assertSame("A;B;C;D\r\na;;1;0\r\n", $csv);
});

it('linha associativa sai pelos valores na ordem em que estao', function (): void {
    $csv = (new CsvExport(['Nome', 'Horas'], [['nome' => 'Ana', 'horas' => 2]]))->withoutBom()->toString();

    assertSame("Nome;Horas\r\nAna;2\r\n", $csv);
});

it('sem linhas sai so o cabecalho', function (): void {
    assertSame("\xEF\xBB\xBFA;B\r\n", (new CsvExport(['A', 'B'], []))->toString());
});

it('separador personalizado muda o separador e as aspas', function (): void {
    $csv = (new CsvExport(['A', 'B'], [['a,b', 'c;d']]))->separator(',')->withoutBom()->toString();

    assertSame("A,B\r\n\"a,b\",c;d\r\n", $csv);
});

it('sem BOM o arquivo comeca direto no cabecalho', function (): void {
    $csv = (new CsvExport(['Pessoa'], []))->withoutBom()->toString();

    assertSame("Pessoa\r\n", $csv);
});

it('separador vazio ou com mais de um caractere e erro', function (): void {
    foreach (['', ';;', '"', "\r", "\n"] as $separator) {
        try {
            (new CsvExport(['A'], []))->separator($separator);
            throw new RuntimeException('separador invalido aceito: "' . $separator . '"');
        } catch (InvalidArgumentException $expected) {
            assertContains('separador', $expected->getMessage());
        }
    }
});

it('linha com colunas a menos que o cabecalho e erro', function (): void {
    try {
        (new CsvExport(['A', 'B'], [['ok', 'ok'], ['x']]))->toString();
        throw new RuntimeException('linha desalinhada aceita');
    } catch (InvalidArgumentException $expected) {
        assertContains('linha 2', $expected->getMessage());
    }
});

it('o erro conta a posicao da linha mesmo com chaves nao sequenciais', function (): void {
    try {
        (new CsvExport(['A', 'B'], ['uuid-1' => ['ok', 'ok'], 'uuid-2' => ['x']]))->toString();
        throw new RuntimeException('linha desalinhada aceita');
    } catch (InvalidArgumentException $expected) {
        assertContains('linha 2', $expected->getMessage());
    }
});

it('linha que nao e lista e erro claro, sem aviso', function (): void {
    foreach (['texto solto', null, 42] as $row) {
        try {
            (new CsvExport(['A'], [['ok'], $row]))->toString();
            throw new RuntimeException('linha que nao e lista aceita');
        } catch (InvalidArgumentException $expected) {
            assertContains('a linha 2 nao e uma lista de valores', $expected->getMessage());
        }
    }
});

it('nome sem extensao ganha .csv', function (): void {
    $export = new CsvExport(['A'], []);

    assertSame('impedimentos.csv', $export->fileName('impedimentos'));
    assertSame('impedimentos.csv', $export->fileName('impedimentos.csv'));
});

it('aspas, barras e quebras de linha saem do nome do arquivo', function (): void {
    $export = new CsvExport(['A'], []);

    assertSame('abcdef.csv', $export->fileName("a\"b/c\\d\r\ne;f"));
    assertSame('exportacao.csv', $export->fileName('"'));
});

it('os cabecalhos do download dizem csv, anexo e nosniff', function (): void {
    $headers = (new CsvExport(['A'], []))->httpHeaders('Histórico 2026');

    assertSame([
        'Content-Type: text/csv; charset=UTF-8',
        'Content-Disposition: attachment; filename="historico-2026.csv"; filename*=UTF-8\'\'Hist%C3%B3rico%202026.csv',
        'X-Content-Type-Options: nosniff',
    ], $headers);
});
