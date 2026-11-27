<?php

declare(strict_types=1);

use App\Helpers\Export\Pdf\PdfText;
use App\Helpers\Export\PdfExport;

echo "\nPdfExport\n";

it('texto em UTF-8 vira Windows-1252 com acentos, travessao, reticencias e cifrao', function (): void {
    assertSame("Fun\xE7\xE3o \x96 R$ 1.234,56\x85 \x80", PdfText::fromUtf8('Função – R$ 1.234,56… €'));
    assertSame('Conceição', iconv('Windows-1252', 'UTF-8', PdfText::fromUtf8('Conceição')));
});

it('caractere sem equivalente vira interrogacao, sem quebrar nem cortar o texto', function (): void {
    assertSame('Equipe ? fim', PdfText::fromUtf8('Equipe 日 fim'));
    assertSame('ok ?', PdfText::fromUtf8('ok 🚀'));
    assertSame('', PdfText::fromUtf8(''));
});

it('o relatorio sai em PDF com nome .pdf e cabecalhos de download', function (): void {
    $export = (new PdfExport(['Pessoa', 'Horas'], [['Ana', '1,50'], ['João', '2,00']]))->title('Horas da equipe')->subtitle('Outubro de 2026');

    assertSame('%PDF-', substr($export->toString(), 0, 5));
    assertSame('horas.pdf', $export->fileName('horas'));
    assertSame('Content-Type: application/pdf', $export->httpHeaders('horas')[0]);
});

it('muitas linhas quebram em mais de uma pagina', function (): void {
    $rows = [];
    for ($i = 1; $i <= 120; $i++) {
        $rows[] = ['Pessoa ' . $i, (string) $i];
    }

    $pdf = (new PdfExport(['Pessoa', 'Número'], $rows))->toString();

    assertTrue(preg_match('/\/Type \/Pages\s*\/Kids \[[^\]]*\]\s*\/Count (\d+)/', $pdf, $match) === 1, 'arvore de paginas nao encontrada');
    assertTrue((int) $match[1] >= 3, 'esperava 3 ou mais paginas, veio ' . $match[1]);
});

it('paisagem, colunas a direita e texto longo nao quebram a geracao', function (): void {
    $pdf = (new PdfExport(['Descrição', 'Valor'], [[str_repeat('Texto bem comprido ', 30), '1.234,56']]))
        ->landscape()
        ->alignRight([1])
        ->toString();

    assertSame('%PDF-', substr($pdf, 0, 5));
});

it('linha que nao e lista ou com colunas a menos e erro, como no CSV', function (): void {
    foreach ([[['so uma']], ['texto']] as $rows) {
        try {
            (new PdfExport(['A', 'B'], $rows))->toString();
            throw new RuntimeException('linha invalida aceita');
        } catch (InvalidArgumentException $expected) {
            assertContains('linha 1', $expected->getMessage());
        }
    }
});

it('coluna de valor fica com a largura natural mesmo com uma descricao enorme ao lado', function (): void {
    // naturais: Tipo 20, Descricao 600, Projeto 25, Horas 12, Valor por hora 24, Valor 22; numericas 3, 4, 5; pagina util 186.
    $widths = PdfExport::columnWidths([20, 600, 25, 12, 24, 22], [3, 4, 5], 186);

    assertSame([12.0, 24.0, 22.0], [$widths[3], $widths[4], $widths[5]]);
    assertTrue(abs(array_sum($widths) - 186) < 0.01, 'deve ocupar a largura util');
    assertTrue(min($widths[0], $widths[2]) >= 14, 'coluna de texto nunca abaixo do minimo');
});

it('sem coluna longa as larguras crescem na proporcao do conteudo', function (): void {
    $widths = PdfExport::columnWidths([20, 40], [], 120);

    assertSame([40.0, 80.0], $widths);
});

it('cabecalho vazio e erro de uso', function (): void {
    try {
        (new PdfExport([], []))->toString();
        throw new RuntimeException('aceitou tabela sem colunas');
    } catch (InvalidArgumentException $expected) {
        assertContains('cabecalho', $expected->getMessage());
    }
});
