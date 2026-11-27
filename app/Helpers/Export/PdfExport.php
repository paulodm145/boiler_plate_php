<?php

namespace App\Helpers\Export;

use App\Helpers\Export\Pdf\PdfDocument;

/**
 * Relatorio tabular simples em PDF, sempre no mesmo visual: titulo, subtitulo,
 * tabela com cabecalho cinza em negrito repetido a cada pagina, linhas zebradas
 * em cinza claro e rodape com a data de geracao e "pagina X de Y".
 *
 * Uso minimo (o Service monta cabecalho, linhas e titulo; o controller so baixa):
 *
 *   (new PdfExport(['Pessoa', 'Horas'], [['Ana', '1,50'], ['Bruno', '2,00']]))
 *       ->title('Relatório')
 *       ->download('relatorio');
 *
 * Opcoes: ->subtitle('Outubro de 2026'), ->landscape() (tabelas largas),
 * ->alignRight([1, 2]) (colunas numericas, pelo indice). Mesmas regras de dados do
 * CsvExport (lista na ordem do cabecalho; null vira vazio). Texto em UTF-8: a
 * conversao para a FPDF e da classe de apoio (Pdf\PdfDocument). Texto que nao cabe
 * na coluna termina em "…".
 */
class PdfExport extends Export
{
    private const FONT = 'Helvetica';
    private const FONT_SIZE = 9;
    private const ROW_HEIGHT = 6;
    private const MIN_COLUMN = 14;
    private const HEADER_FILL = 220;
    private const ZEBRA_FILL = 245;
    /** Quantas linhas medir para dar a largura das colunas (o resto segue a mesma proporcao). */
    private const SAMPLE_ROWS = 300;

    /** @var array */
    private $header;

    /** @var array */
    private $rows;

    /** @var string */
    private $title = '';

    /** @var string */
    private $subtitle = '';

    /** @var bool */
    private $landscape = false;

    /** @var array */
    private $rightAligned = [];

    public function __construct(array $header, array $rows)
    {
        $this->header = array_map('strval', array_values($header));
        $this->rows = $rows;
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /** Pagina deitada, para tabelas com muitas colunas. */
    public function landscape(): self
    {
        $this->landscape = true;

        return $this;
    }

    /** Colunas (indices, a partir de 0) alinhadas a direita: valores e quantidades. */
    public function alignRight(array $columns): self
    {
        $this->rightAligned = array_map('intval', $columns);

        return $this;
    }

    public function toString(): string
    {
        if ($this->header === []) {
            throw new \InvalidArgumentException('PdfExport: o cabecalho precisa de pelo menos uma coluna');
        }

        $rows = array_map(function (array $values): array {
            return array_map([self::class, 'cellText'], $values);
        }, self::tableRows('PdfExport', $this->header, $this->rows));

        $pdf = new PdfDocument($this->landscape);
        $pdf->setFooterNote('Gerado em ' . date('d/m/Y H:i'));
        $pdf->AddPage();
        $this->heading($pdf);
        $widths = $this->widths($pdf, $rows);
        $this->headerRow($pdf, $widths);

        foreach ($rows as $index => $row) {
            if (!$pdf->fits(self::ROW_HEIGHT)) {
                $pdf->AddPage();
                $this->headerRow($pdf, $widths);
            }

            $this->bodyRow($pdf, $widths, $row, $index % 2 === 1);
        }

        if ($rows === []) {
            $pdf->SetFont(self::FONT, 'I', self::FONT_SIZE);
            $pdf->cellText(0, self::ROW_HEIGHT, 'Nenhum registro.', 0, 1);
        }

        return $pdf->Output('S');
    }

    protected function contentType(): string
    {
        return 'application/pdf';
    }

    protected function extension(): string
    {
        return 'pdf';
    }

    private function heading(PdfDocument $pdf): void
    {
        if ($this->title !== '') {
            $pdf->SetFont(self::FONT, 'B', 14);
            $pdf->SetTextColor(33);
            $pdf->cellText(0, 8, $this->title, 0, 1);
        }

        if ($this->subtitle !== '') {
            $pdf->SetFont(self::FONT, '', 9);
            $pdf->SetTextColor(100);
            $pdf->cellText(0, 5, $this->subtitle, 0, 1);
        }

        $pdf->Ln(3);
    }

    private function headerRow(PdfDocument $pdf, array $widths): void
    {
        $pdf->SetFont(self::FONT, 'B', self::FONT_SIZE);
        $pdf->SetFillColor(self::HEADER_FILL);
        $pdf->SetTextColor(33);

        foreach ($this->header as $column => $title) {
            $pdf->cellText($widths[$column], self::ROW_HEIGHT + 1, $pdf->fit($title, $widths[$column]), 0, 0, $this->align($column), true);
        }

        $pdf->Ln();
    }

    private function bodyRow(PdfDocument $pdf, array $widths, array $row, bool $zebra): void
    {
        $pdf->SetFont(self::FONT, '', self::FONT_SIZE);
        $pdf->SetFillColor($zebra ? self::ZEBRA_FILL : 255);
        $pdf->SetTextColor(33);

        foreach ($row as $column => $text) {
            $pdf->cellText($widths[$column], self::ROW_HEIGHT, $pdf->fit($text, $widths[$column]), 0, 0, $this->align($column), true);
        }

        $pdf->Ln();
    }

    /** Larguras proporcionais ao texto mais largo de cada coluna, ocupando a largura util. */
    private function widths(PdfDocument $pdf, array $rows): array
    {
        $natural = [];
        $pdf->SetFont(self::FONT, 'B', self::FONT_SIZE);

        foreach ($this->header as $column => $title) {
            $natural[$column] = $pdf->textWidth($title);
        }

        $pdf->SetFont(self::FONT, '', self::FONT_SIZE);

        foreach (array_slice($rows, 0, self::SAMPLE_ROWS) as $row) {
            foreach ($row as $column => $text) {
                $natural[$column] = max($natural[$column], $pdf->textWidth($text));
            }
        }

        $natural = array_map(function (float $width): float {
            return max(self::MIN_COLUMN, $width + 4);
        }, $natural);

        return self::columnWidths($natural, $this->rightAligned, $pdf->contentWidth());
    }

    /**
     * Larguras finais: as colunas a direita (valores) ficam com a largura natural --
     * um numero cortado num relatorio financeiro e inaceitavel; as de texto dividem o
     * resto (fill()). So se nem o minimo de cada coluna de texto couber, tudo encolhe
     * na mesma proporcao.
     */
    public static function columnWidths(array $natural, array $rightAligned, float $available): array
    {
        $fixed = array_intersect_key($natural, array_flip($rightAligned));
        $text = array_diff_key($natural, $fixed);
        $room = $available - array_sum($fixed);

        if ($text === [] || $room < self::MIN_COLUMN * count($text)) {
            $scale = $available / array_sum($natural);

            return array_map(function (float $width) use ($scale): float {
                return $width * $scale;
            }, $natural);
        }

        $widths = $fixed + self::fill($text, $room);

        ksort($widths);

        return array_map('floatval', $widths);
    }

    /**
     * Divide $room entre as colunas de texto. Se tudo cabe, cada uma cresce na proporcao
     * do conteudo. Senao, "enchimento": a coluna curta ("Tipo", "Projeto") recebe a
     * largura natural inteira e so as compridas dividem o que sobra, em partes iguais
     * (o excesso termina em "…").
     */
    private static function fill(array $text, float $room): array
    {
        if (array_sum($text) <= $room) {
            $scale = $room / array_sum($text);

            return array_map(function (float $width) use ($scale): float {
                return $width * $scale;
            }, $text);
        }

        asort($text);
        $widths = [];
        $left = count($text);

        foreach ($text as $column => $width) {
            $share = $room / $left;
            $widths[$column] = min($width, $share);
            $room -= $widths[$column];
            $left--;
        }

        return $widths;
    }

    private function align(int $column): string
    {
        return in_array($column, $this->rightAligned, true) ? 'R' : 'L';
    }
}
