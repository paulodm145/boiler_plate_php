<?php

namespace App\Validation;

use App\Database\Database;
use App\Helpers\Br\Cep;
use App\Helpers\Br\Cnpj;
use App\Helpers\Br\Cpf;
use App\Helpers\Br\Date;
use App\Helpers\Br\Phone;
use App\Helpers\Input;
use InvalidArgumentException;

/**
 * Validacao no estilo do Laravel: regras por pipe ('required|email|max:120')
 * ou em array (['required', 'regex:/a|b/'], use array quando a regex tem "|").
 *
 *     $validator = Validator::make($_POST, ['email' => 'required|email'], [], ['email' => 'e-mail']);
 *     if ($validator->fails()) { $validator->errors()->first('email'); }
 *
 * Campo vazio (null, '' so de espacos, []) sem "required" pula as demais
 * regras. Cada campo junta todas as mensagens que falharem; "required" que
 * falha encerra o campo. Regra nova: um metodo ruleNome() + a mensagem em
 * MESSAGES, ou Validator::extend() no projeto.
 */
class Validator
{
    protected const MESSAGES = [
        'required' => 'O campo :attribute é obrigatório.',
        'string' => 'O campo :attribute deve ser um texto.',
        'email' => 'Informe um e-mail válido em :attribute.',
        'min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        'min.numeric' => 'O campo :attribute deve ser no mínimo :min.',
        'min.file' => 'O arquivo de :attribute deve ter pelo menos :min KB.',
        'max' => 'O campo :attribute deve ter no máximo :max caracteres.',
        'max.numeric' => 'O campo :attribute deve ser no máximo :max.',
        'max.file' => 'O arquivo de :attribute deve ter no máximo :max KB.',
        'between' => 'O campo :attribute deve estar entre :min e :max.',
        'in' => 'O valor de :attribute não é permitido.',
        'numeric' => 'O campo :attribute deve ser um número.',
        'integer' => 'O campo :attribute deve ser um número inteiro.',
        'regex' => 'O formato de :attribute é inválido.',
        'confirmed' => 'A confirmação de :attribute não confere.',
        'same' => 'Os campos :attribute e :other devem ser iguais.',
        'date' => 'Informe uma data válida em :attribute.',
        'before' => 'A data de :attribute deve ser anterior a :other.',
        'after' => 'A data de :attribute deve ser posterior a :other.',
        'unique' => 'Este :attribute já está em uso.',
        'exists' => 'O :attribute selecionado não existe.',
        'file' => 'Envie um arquivo em :attribute.',
        'mimes' => 'O arquivo de :attribute deve ser do tipo: :values.',
        'max_size' => 'O arquivo de :attribute deve ter no máximo :max KB.',
        'cpf' => 'Informe um CPF válido.',
        'cnpj' => 'Informe um CNPJ válido.',
        'cep' => 'Informe um CEP válido.',
        'phone' => 'Informe um telefone válido.',
        'date_br' => 'Informe uma data válida (dd/mm/aaaa) em :attribute.',
        'money' => 'Informe um valor válido em :attribute (ex.: 1.234,56).',
        'decimal_br' => 'Informe um número válido em :attribute.',
        'weight' => 'Informe um peso válido em :attribute (ex.: 1.234,567).',
    ];

    /** Extensao -> tipos MIME aceitos, detectados pelo conteudo (finfo), nunca pela extensao. */
    protected const MIME_TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'csv' => ['text/plain', 'text/csv'],
        'txt' => ['text/plain'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip'],
    ];

    /** @var array<string, array{0: callable, 1: string}> */
    private static array $extensions = [];

    private ?Database $database = null;
    private ?ErrorBag $errorBag = null;

    /** @var array<string, string[]> */
    private array $errorMessages = [];

    /**
     * @param array<string, string|array> $rules
     * @param array<string, string> $messages 'campo.regra' ou 'regra' => mensagem
     * @param array<string, string> $attributes 'campo' => nome amigavel
     */
    final public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $attributes = []
    ) {
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): static
    {
        return new static($data, $rules, $messages, $attributes);
    }

    /** Regra do projeto sem editar a classe: $rule(mixed $value, array $params, array $data): bool. */
    public static function extend(string $name, callable $rule, string $message): void
    {
        self::$extensions[$name] = [$rule, $message];
    }

    /** Conexao usada por unique/exists (padrao: Database::shared()). */
    public function setDatabase(Database $database): static
    {
        $this->database = $database;

        return $this;
    }

    public function passes(): bool
    {
        return !$this->errors()->any();
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): ErrorBag
    {
        if ($this->errorBag === null) {
            $this->run();
            $this->errorBag = new ErrorBag($this->errorMessages);
        }

        return $this->errorBag;
    }

    /** So os campos que tem regra (e que vieram na entrada), com o valor como veio. */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $spec) {
            $rules = $this->parse($spec);
            $names = array_column($rules, 0);
            $value = $this->data[$field] ?? null;

            if ($this->isEmpty($value)) {
                if (in_array('required', $names, true)) {
                    $this->addError($field, 'required', []);
                }

                continue;
            }

            foreach ($rules as [$rule, $params]) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }

                if (!$this->check($rule, $value, $params, $field, $names)) {
                    $this->addError($field, $this->messageKey($rule, $value, $names), $params);
                }
            }
        }
    }

    /** @return array<int, array{0: string, 1: string[]}> */
    private function parse(string|array $spec): array
    {
        $parsed = [];

        foreach (is_array($spec) ? $spec : explode('|', $spec) as $rule) {
            [$name, $raw] = array_pad(explode(':', (string) $rule, 2), 2, null);
            $name = trim($name);

            if ($name === '') {
                continue;
            }

            // A regex e um parametro so: virgulas fazem parte dela.
            $params = $raw === null ? [] : ($name === 'regex' ? [$raw] : array_map('trim', explode(',', $raw)));
            $parsed[] = [$name, $params];
        }

        return $parsed;
    }

    private function check(string $rule, mixed $value, array $params, string $field, array $names): bool
    {
        if (isset(self::$extensions[$rule])) {
            return (bool) (self::$extensions[$rule][0])($value, $params, $this->data);
        }

        $method = 'rule' . str_replace(' ', '', ucwords(str_replace('_', ' ', $rule)));

        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException('Regra de validacao desconhecida: ' . $rule);
        }

        return (bool) $this->{$method}($value, $params, $field, $names);
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === [] || (is_string($value) && trim($value) === '');
    }

    /** Texto do valor para regras de texto; array/objeto -> null (a regra falha sem warning). */
    private function text(mixed $value): ?string
    {
        return is_string($value) || is_int($value) || is_float($value) ? (string) $value : null;
    }

    private function isFile(mixed $value): bool
    {
        return is_array($value)
            && isset($value['tmp_name'], $value['error'])
            && $value['error'] === UPLOAD_ERR_OK
            && is_string($value['tmp_name'])
            && is_file($value['tmp_name']);
    }

    /** Tamanho para min/max/between: KB do arquivo, valor numerico ou numero de caracteres. */
    private function size(mixed $value, array $names): ?float
    {
        if ($this->isFile($value)) {
            return ((int) ($value['size'] ?? filesize($value['tmp_name']))) / 1024;
        }

        $text = $this->text($value);

        if ($text === null) {
            return is_array($value) ? (float) count($value) : null;
        }

        if ((in_array('numeric', $names, true) || in_array('integer', $names, true)) && is_numeric($text)) {
            return (float) $text;
        }

        return (float) mb_strlen($text);
    }

    private function messageKey(string $rule, mixed $value, array $names): string
    {
        if (!in_array($rule, ['min', 'max'], true)) {
            return $rule;
        }

        if ($this->isFile($value)) {
            return $rule . '.file';
        }

        return in_array('numeric', $names, true) || in_array('integer', $names, true) ? $rule . '.numeric' : $rule;
    }

    private function addError(string $field, string $key, array $params): void
    {
        $rule = explode('.', $key)[0];
        $message = $this->messages[$field . '.' . $rule]
            ?? $this->messages[$rule]
            ?? (isset(self::$extensions[$rule]) ? self::$extensions[$rule][1] : null)
            ?? static::MESSAGES[$key]
            ?? static::MESSAGES[$rule]
            ?? 'O campo :attribute é inválido.';

        $this->errorMessages[$field][] = strtr($message, $this->placeholders($field, $rule, $params));
    }

    private function placeholders(string $field, string $rule, array $params): array
    {
        $replace = [':attribute' => $this->attributeName($field), ':values' => implode(', ', $params)];

        if (in_array($rule, ['min', 'max', 'max_size'], true)) {
            $replace[':min'] = $params[0] ?? '';
            $replace[':max'] = $params[0] ?? '';
        }

        if ($rule === 'between') {
            $replace[':min'] = $params[0] ?? '';
            $replace[':max'] = $params[1] ?? '';
        }

        if (in_array($rule, ['same', 'before', 'after'], true)) {
            $other = $params[0] ?? '';
            $replace[':other'] = array_key_exists($other, $this->data) || isset($this->attributes[$other])
                ? $this->attributeName($other)
                : (Date::format($other) ?: $other);
        }

        return $replace;
    }

    private function attributeName(string $field): string
    {
        return $this->attributes[$field] ?? str_replace('_', ' ', $field);
    }

    /** Valor de comparacao de before/after: outro campo da entrada ou uma data literal Y-m-d. */
    private function dateParam(string $param): ?string
    {
        $candidate = array_key_exists($param, $this->data) ? $this->text($this->data[$param]) : $param;

        return $candidate !== null && Date::parse($candidate) === $candidate && str_contains($candidate, '-') ? $candidate : null;
    }

    private function identifier(string $name): string
    {
        if (preg_match('/^[a-z_][a-z0-9_]*\z/', $name) !== 1) {
            throw new InvalidArgumentException('Nome de tabela ou coluna invalido na regra de validacao: ' . $name);
        }

        return $name;
    }

    private function db(): Database
    {
        return $this->database ?? Database::shared();
    }

    protected function ruleString(mixed $value): bool
    {
        return is_string($value);
    }

    protected function ruleEmail(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && filter_var($text, FILTER_VALIDATE_EMAIL) !== false;
    }

    protected function ruleMin(mixed $value, array $params, string $field, array $names): bool
    {
        $size = $this->size($value, $names);

        return $size !== null && $size >= (float) ($params[0] ?? 0);
    }

    protected function ruleMax(mixed $value, array $params, string $field, array $names): bool
    {
        $size = $this->size($value, $names);

        return $size !== null && $size <= (float) ($params[0] ?? 0);
    }

    protected function ruleBetween(mixed $value, array $params, string $field, array $names): bool
    {
        $size = $this->size($value, $names);

        return $size !== null && $size >= (float) ($params[0] ?? 0) && $size <= (float) ($params[1] ?? 0);
    }

    protected function ruleIn(mixed $value, array $params): bool
    {
        $text = $this->text($value);

        return $text !== null && in_array($text, $params, true);
    }

    protected function ruleNumeric(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && is_numeric($text);
    }

    protected function ruleInteger(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && preg_match('/^-?\d+\z/', $text) === 1;
    }

    protected function ruleRegex(mixed $value, array $params): bool
    {
        $text = $this->text($value);

        return $text !== null && preg_match($params[0] ?? '//', $text) === 1;
    }

    protected function ruleConfirmed(mixed $value, array $params, string $field): bool
    {
        return ($this->data[$field . '_confirmation'] ?? null) === $value;
    }

    protected function ruleSame(mixed $value, array $params): bool
    {
        return ($this->data[$params[0] ?? ''] ?? null) === $value;
    }

    protected function ruleDate(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && str_contains($text, '-') && Date::parse($text) === $text;
    }

    protected function ruleBefore(mixed $value, array $params): bool
    {
        $other = $this->dateParam($params[0] ?? '');

        return $this->ruleDate($value) && $other !== null && (string) $value < $other;
    }

    protected function ruleAfter(mixed $value, array $params): bool
    {
        $other = $this->dateParam($params[0] ?? '');

        return $this->ruleDate($value) && $other !== null && (string) $value > $other;
    }

    /** unique:tabela,coluna[,ignorarId[,colunaId]] */
    protected function ruleUnique(mixed $value, array $params): bool
    {
        $table = $this->identifier($params[0] ?? '');
        $column = $this->identifier($params[1] ?? '');
        $text = $this->text($value);

        if ($text === null) {
            return false;
        }

        $sql = 'SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = :value';
        $bindings = ['value' => $text];

        if (($params[2] ?? '') !== '') {
            $sql .= ' AND ' . $this->identifier($params[3] ?? 'id') . ' <> :ignore';
            $bindings['ignore'] = $params[2];
        }

        return $this->db()->fetch($sql . ' LIMIT 1', $bindings) === null;
    }

    /** exists:tabela,coluna */
    protected function ruleExists(mixed $value, array $params): bool
    {
        $table = $this->identifier($params[0] ?? '');
        $column = $this->identifier($params[1] ?? '');
        $text = $this->text($value);

        return $text !== null
            && $this->db()->fetch('SELECT 1 FROM ' . $table . ' WHERE ' . $column . ' = :value LIMIT 1', ['value' => $text]) !== null;
    }

    protected function ruleFile(mixed $value): bool
    {
        return $this->isFile($value);
    }

    /** Tipo detectado pelo conteudo do arquivo (finfo), nao pela extensao do nome. */
    protected function ruleMimes(mixed $value, array $params): bool
    {
        if (!$this->isFile($value)) {
            return false;
        }

        $detected = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($value['tmp_name']);

        foreach ($params as $extension) {
            if (in_array($detected, static::MIME_TYPES[strtolower($extension)] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    protected function ruleMaxSize(mixed $value, array $params): bool
    {
        return $this->isFile($value) && filesize($value['tmp_name']) <= (int) ($params[0] ?? 0) * 1024;
    }

    protected function ruleCpf(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && Cpf::isValid($text);
    }

    protected function ruleCnpj(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && Cnpj::isValid($text);
    }

    protected function ruleCep(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && Cep::isValid($text);
    }

    protected function rulePhone(mixed $value): bool
    {
        $text = $this->text($value);

        return $text !== null && Phone::isValid($text);
    }

    protected function ruleDateBr(mixed $value): bool
    {
        return is_string($value) && Date::parse($value) !== null;
    }

    protected function ruleMoney(mixed $value): bool
    {
        return is_string($value) && Input::money($value) !== null;
    }

    /** decimal_br:escala (padrao 2), formato brasileiro ("1.234,56"). */
    protected function ruleDecimalBr(mixed $value, array $params): bool
    {
        return is_string($value) && Input::decimal($value, (int) ($params[0] ?? 2)) !== null;
    }

    protected function ruleWeight(mixed $value): bool
    {
        return is_string($value) && Input::decimal($value, 3) !== null;
    }
}
