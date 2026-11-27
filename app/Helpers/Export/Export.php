<?php

namespace App\Helpers\Export;

use App\Helpers\Text;

/**
 * Base das ferramentas de exportacao (padrao Template Method): o que todo
 * formato repete — nome do arquivo, cabecalhos HTTP, entregar o download —
 * fica aqui, uma vez so. Um formato novo (PDF, Excel, TXT, ZIP...) estende
 * esta classe e implementa apenas tres metodos:
 *
 *   toString()    o conteudo do arquivo
 *   contentType() o tipo MIME, ex.: 'text/csv; charset=UTF-8'
 *   extension()   a extensao sem ponto, ex.: 'csv'
 *
 * Opcoes do formato sao metodos que devolvem $this, com um padrao pronto
 * para quem nao chamar nada. Guia: docs/exportacao.md.
 */
abstract class Export
{
    private const FALLBACK_NAME = 'exportacao';

    /** Texto que comeca assim vira formula na planilha (CSV/XLSX injection). */
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    /** O conteudo completo do arquivo. */
    abstract public function toString(): string;

    abstract protected function contentType(): string;

    abstract protected function extension(): string;

    /**
     * Envia o arquivo para o navegador como download. Depois disso a action
     * so retorna: nada mais pode ser impresso.
     */
    public function download(string $name): void
    {
        foreach ($this->httpHeaders($name) as $header) {
            header($header);
        }

        echo $this->toString();
    }

    /**
     * Os cabecalhos que download() envia. O filename= leva uma versao ASCII
     * (navegadores antigos) e o filename* o nome com acentos.
     */
    public function httpHeaders(string $name): array
    {
        $fileName = $this->fileName($name);
        $asciiBase = Text::slugify($this->withoutExtension($fileName));
        $asciiName = ($asciiBase !== '' ? $asciiBase : self::FALLBACK_NAME) . '.' . $this->extension();

        return [
            'Content-Type: ' . $this->contentType(),
            'Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($fileName),
            'X-Content-Type-Options: nosniff',
        ];
    }

    /**
     * Nome seguro para o Content-Disposition: sem caracteres de controle,
     * aspas, barras nem ";" (injecao de cabecalho/caminho), sempre com a
     * extensao do formato.
     */
    public function fileName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F"\\\\\/;]/', '', $name));
        $base = $this->withoutExtension($clean);

        return ($base !== '' ? $base : self::FALLBACK_NAME) . '.' . $this->extension();
    }

    /**
     * Linhas de uma tabela prontas para o formato: cada linha vira a lista dos seus
     * valores (uma linha associativa e lida pelos valores, na ordem); linha que nao
     * e lista ou com contagem diferente do cabecalho e InvalidArgumentException
     * (bug de quem montou). Compartilhado por CsvExport e PdfExport.
     */
    protected static function tableRows(string $tool, array $header, array $rows): array
    {
        $columns = count($header);
        $table = [];

        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException(sprintf('%s: a linha %d nao e uma lista de valores (recebeu %s)', $tool, $index + 1, gettype($row)));
            }

            $values = array_values($row);

            if (count($values) !== $columns) {
                throw new \InvalidArgumentException(sprintf('%s: a linha %d tem %d colunas, o cabecalho tem %d', $tool, $index + 1, count($values), $columns));
            }

            $table[] = $values;
        }

        return $table;
    }

    /** Valor de celula como texto: null vira vazio, bool vira 1/0, o resto e convertido. */
    protected static function cellText($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Protecao contra formula, sempre ligada: TEXTO que comeca com = + - @ tab ou CR ganha
     * um apostrofo, para o que as pessoas digitam nao virar formula. Numeros (int/float)
     * passam como estao: -3 continua -3, ja o texto '-3' ganha o apostrofo.
     */
    protected static function protectFormula(mixed $value, string $text): string
    {
        return is_string($value) && $text !== '' && in_array($text[0], self::FORMULA_STARTS, true) ? "'" . $text : $text;
    }

    private function withoutExtension(string $name): string
    {
        $suffix = '.' . $this->extension();

        // (string): no PHP 7.2 substr() de uma string curta demais devolve false
        if (strcasecmp((string) substr($name, -strlen($suffix)), $suffix) === 0) {
            return trim((string) substr($name, 0, -strlen($suffix)));
        }

        return $name;
    }
}
