<?php

namespace App\Helpers\Export;

use InvalidArgumentException;

/**
 * Exporta uma tabela em CSV pronta para abrir no Excel em portugues.
 *
 * Uso minimo (o Service monta cabecalho e linhas; o controller so baixa):
 *
 *   (new CsvExport(['Pessoa', 'Horas'], [['Ana', '1h30'], ['Bruno', 2]]))
 *       ->download('relatorio');
 *
 * Personalizado: ->separator(',')->withoutBom()->download('dados');
 * Sem download (teste, anexo de e-mail): ->toString().
 *
 * Padroes: BOM UTF-8 (acentos certos no Excel), ";" como separador, CRLF,
 * aspas so quando o valor tem separador, aspas ou quebra de linha.
 * Protecao contra formula SEMPRE ligada (nao e opcao): texto que comeca com
 * = + - @ tab ou CR ganha um apostrofo, porque o que as pessoas digitam nao
 * pode virar formula na planilha de quem exporta. Numeros saem como estao.
 *
 * Cada linha e uma lista de valores na ordem do cabecalho (uma linha
 * associativa e lida pelos valores, na ordem em que estao). null vira vazio,
 * bool vira 1/0. Linha com numero de colunas diferente do cabecalho e erro.
 */
class CsvExport extends Export
{
    private const BOM = "\xEF\xBB\xBF";
    private const LINE_END = "\r\n";

    /** @var array */
    private $header;

    /** @var array */
    private $rows;

    /** @var string */
    private $separator = ';';

    /** @var bool */
    private $withBom = true;

    public function __construct(array $header, array $rows)
    {
        $this->header = $header;
        $this->rows = $rows;
    }

    /** Troca o separador (padrao ";"). Exatamente um caractere, nunca aspas nem quebra de linha. */
    public function separator(string $separator): self
    {
        if (strlen($separator) !== 1 || strpbrk($separator, "\"\r\n") !== false) {
            throw new InvalidArgumentException(
                'CsvExport: o separador deve ter exatamente um caractere, que nao seja aspas nem quebra de linha'
            );
        }

        $this->separator = $separator;

        return $this;
    }

    /** Tira o BOM UTF-8 do inicio (para sistemas que nao o esperam). */
    public function withoutBom(): self
    {
        $this->withBom = false;

        return $this;
    }

    public function toString(): string
    {
        $lines = [$this->line($this->header)];

        foreach (self::tableRows('CsvExport', $this->header, $this->rows) as $values) {
            $lines[] = $this->line($values);
        }

        return ($this->withBom ? self::BOM : '') . implode(self::LINE_END, $lines) . self::LINE_END;
    }

    protected function contentType(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    protected function extension(): string
    {
        return 'csv';
    }

    private function line(array $values): string
    {
        return implode($this->separator, array_map([$this, 'cell'], array_values($values)));
    }

    private function cell($value): string
    {
        $text = self::cellText($value);

        $text = self::protectFormula($value, $text);

        if (strpbrk($text, $this->separator . "\"\r\n") === false) {
            return $text;
        }

        return '"' . str_replace('"', '""', $text) . '"';
    }
}
