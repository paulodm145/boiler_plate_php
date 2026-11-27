<?php

namespace App\Helpers\Export;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/**
 * Planilha Excel (.xlsx, Office Open XML) sem dependencias: um ZIP com alguns
 * XMLs, montado a mao (base: o XlsxWriter do lorencine). Uma aba, cabecalho em
 * negrito congelado com autofiltro, e colunas tipadas (types()):
 *
 *   'text'     texto (padrao), com a protecao contra formula do CSV
 *   'integer'  numero inteiro
 *   'decimal'  numero com ate 3 casas (quantidades, peso)
 *   'money'    numero com 2 casas e separador de milhar (valor em reais, nao centavos)
 *   'percent'  numero com 1 casa (ex.: comissao 0,5)
 *   'date'     data Y-m-d (ou Y-m-d H:i:s) gravada como data do Excel
 *
 * Numeros e datas vao como valores, entao o Excel soma, filtra e ordena (e
 * exibe no formato regional de quem abre). Requer a extensao zip.
 *
 *     (new XlsxExport(['Cliente', 'Total'], $linhas))->types([1 => 'money'])->download('vendas');
 */
final class XlsxExport extends Export
{
    private const STYLES = ['text' => 0, 'header' => 1, 'date' => 2, 'money' => 3, 'decimal' => 4, 'integer' => 5, 'percent' => 6];
    private const DEFAULT_WIDTH = 15;

    /** @var array<int, string> */
    private array $types = [];

    /** @var array<int, int> */
    private array $widths = [];

    public function __construct(
        private readonly array $header,
        private readonly array $rows,
        private readonly string $sheetName = 'Planilha'
    ) {
    }

    /** @param array<int, string> $typesByIndex indice da coluna (a partir de 0) => tipo */
    public function types(array $typesByIndex): self
    {
        foreach ($typesByIndex as $index => $type) {
            if (!isset(self::STYLES[$type]) || $type === 'header') {
                throw new InvalidArgumentException('XlsxExport: tipo de coluna invalido: ' . $type);
            }

            $this->types[(int) $index] = $type;
        }

        return $this;
    }

    /** @param array<int, int> $widthsByIndex indice da coluna => largura (em caracteres) */
    public function widths(array $widthsByIndex): self
    {
        foreach ($widthsByIndex as $index => $width) {
            $this->widths[(int) $index] = max(1, (int) $width);
        }

        return $this;
    }

    public function toString(): string
    {
        $rows = self::tableRows('XlsxExport', $this->header, $this->rows);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($path === false) {
            throw new RuntimeException('Sem pasta temporaria para gerar a planilha.');
        }

        try {
            $zip = new ZipArchive();

            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Nao foi possivel criar a planilha.');
            }

            $zip->addFromString('[Content_Types].xml', $this->contentTypes());
            $zip->addFromString('_rels/.rels', $this->rootRels());
            $zip->addFromString('xl/workbook.xml', $this->workbook(count($rows)));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
            $zip->addFromString('xl/styles.xml', $this->styles());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->sheet($rows));
            $zip->close();

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    protected function contentType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    protected function extension(): string
    {
        return 'xlsx';
    }

    /** "A", "B", ..., "Z", "AA"... a partir do indice 0. */
    public static function columnLetter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + ($index - 1) % 26) . $letter;
        }

        return $letter;
    }

    /** Data Y-m-d (com ou sem hora) -> numero de serie do Excel; null se vazia/invalida. */
    public static function excelDate(?string $value): ?float
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/', $value, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        // Serie do Excel: dias desde 30/12/1899 (25569 = 01/01/1970), calculado em UTC.
        $timestamp = gmmktime((int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0), (int) $m[2], (int) $m[3], (int) $m[1]);

        return round($timestamp / 86400 + 25569, 6);
    }

    private function cell(int $index, int $rowNumber, mixed $value): string
    {
        $type = $this->types[$index] ?? 'text';
        $ref = self::columnLetter($index) . $rowNumber;

        if ($type === 'date') {
            $serial = self::excelDate($value === null ? null : (string) $value);

            return $serial === null ? '' : '<c r="' . $ref . '" s="' . self::STYLES['date'] . '"><v>' . $serial . '</v></c>';
        }

        if ($type !== 'text') {
            if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
                return '';
            }

            return '<c r="' . $ref . '" s="' . self::STYLES[$type] . '"><v>' . (0 + $value) . '</v></c>';
        }

        $text = self::clean(self::protectFormula($value, self::cellText($value)));

        return $text === '' ? '' : '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
    }

    /** Escapa para XML e remove caracteres de controle que tornariam o arquivo invalido. */
    private static function clean(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function sheet(array $rows): string
    {
        $lastColumn = self::columnLetter(max(0, count($this->header) - 1));
        $lastRow = count($rows) + 1;
        $cols = '';
        $headerCells = '';

        foreach (array_values($this->header) as $index => $label) {
            $n = $index + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . ($this->widths[$index] ?? self::DEFAULT_WIDTH) . '" customWidth="1"/>';
            $headerCells .= '<c r="' . self::columnLetter($index) . '1" s="' . self::STYLES['header'] . '" t="inlineStr"><is><t xml:space="preserve">'
                . self::clean(self::cellText($label)) . '</t></is></c>';
        }

        $body = '';

        foreach ($rows as $offset => $values) {
            $rowNumber = $offset + 2;
            $cells = '';

            foreach ($values as $index => $value) {
                $cells .= $this->cell($index, $rowNumber, $value);
            }

            $body .= '<row r="' . $rowNumber . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . ($cols === '' ? '' : '<cols>' . $cols . '</cols>')
            . '<sheetData><row r="1">' . $headerCells . '</row>' . $body . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
            . '</worksheet>';
    }

    private function workbook(int $rowCount): string
    {
        $name = self::clean(mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->sheetName), 0, 31));
        $lastColumn = self::columnLetter(max(0, count($this->header) - 1));
        $lastRow = $rowCount + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
            . '<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\'' . $name . '\'!$A$1:$' . $lastColumn . '$' . $lastRow . '</definedName></definedNames>'
            . '</workbook>';
    }

    private function styles(): string
    {
        // Indices de cellXfs = self::STYLES. numFmt 4 = "#,##0.00", 1 = "0" (embutidos).
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="#,##0.###"/><numFmt numFmtId="166" formatCode="0.0"/></numFmts>'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF2563EB"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="7">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }
}
