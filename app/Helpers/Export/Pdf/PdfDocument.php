<?php

namespace App\Helpers\Export\Pdf;

require_once dirname(__DIR__, 4) . '/lib/fpdf/fpdf.php';

/**
 * Classe de apoio sobre a FPDF (lib/fpdf, vendorizada). Quem usa escreve sempre
 * em UTF-8 por cellText()/textWidth(): a conversao para Windows-1252 acontece so
 * aqui (PdfText). Os metodos da FPDF nao sao sobrescritos de proposito -- MultiCell
 * e Write chamam Cell por dentro e converteriam o texto duas vezes. Traz o padrao
 * dos relatorios: A4, margens de 12 mm e rodape com a data e "pagina X de Y".
 */
class PdfDocument extends \FPDF
{
    public const MARGIN = 12;

    private $footerNote = '';

    public function __construct(bool $landscape = false)
    {
        parent::__construct($landscape ? 'L' : 'P', 'mm', 'A4');
        $this->AliasNbPages('{nb}');
        $this->SetMargins(self::MARGIN, self::MARGIN, self::MARGIN);
        $this->SetAutoPageBreak(true, 15);
        $this->SetCreator((string) (\App\Core\Env::get('APP_NAME') ?: 'App'), true);
    }

    /** Uma celula com texto em UTF-8 (mesmos parametros de FPDF::Cell). */
    public function cellText(float $width, float $height, string $text, $border = 0, int $ln = 0, string $align = '', bool $fill = false): void
    {
        $this->Cell($width, $height, PdfText::fromUtf8($text), $border, $ln, $align, $fill);
    }

    /** Largura, em mm, de um texto UTF-8 na fonte atual. */
    public function textWidth(string $text): float
    {
        return $this->GetStringWidth(PdfText::fromUtf8($text));
    }

    /** O texto cabe em $width (descontada a folga da celula)? Senao, corta e termina em "…". */
    public function fit(string $text, float $width): string
    {
        $room = $width - 2 * $this->cMargin;

        if ($this->textWidth($text) <= $room) {
            return $text;
        }

        // Corta primeiro pela proporcao medida (texto de 5 mil caracteres nao mede letra a letra), depois ajusta.
        $length = mb_strlen($text);
        $text = mb_substr($text, 0, max(1, (int) floor($length * $room / $this->textWidth($text))));

        while ($text !== '' && $this->textWidth($text . '…') > $room) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    /** Cabe mais $height mm nesta pagina antes da quebra automatica? */
    public function fits(float $height): bool
    {
        return $this->GetY() + $height <= $this->PageBreakTrigger;
    }

    public function contentWidth(): float
    {
        return $this->GetPageWidth() - 2 * self::MARGIN;
    }

    /** Texto do rodape, antes de "pagina X de Y" (ex.: "Gerado em 13/11/2026 10:00"). */
    public function setFooterNote(string $note): void
    {
        $this->footerNote = $note;
    }

    /** Gancho da FPDF: rodape de toda pagina. */
    public function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(120);
        $page = 'página ' . $this->PageNo() . ' de {nb}';
        $this->cellText(0, 5, $this->footerNote === '' ? $page : $this->footerNote . ' · ' . $page, 0, 0, 'R');
    }
}
