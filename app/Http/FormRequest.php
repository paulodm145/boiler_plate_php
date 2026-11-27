<?php

namespace App\Http;

use App\Validation\ValidationException;
use App\Validation\Validator;

/**
 * Validacao de uma requisicao, no estilo do FormRequest do Laravel. No controller:
 *
 *     $data = StoreUserRequest::validate();
 *
 * Se a entrada for invalida, lanca ValidationException e o Router devolve a
 * pessoa ao formulario com os erros e o que ela digitou (ou 422 JSON). Aqui
 * so ha regra de formato; regra de negocio fica no Service.
 */
abstract class FormRequest
{
    /** @return array<string, string|array> */
    abstract public function rules(): array;

    /** @return array<string, string> 'campo.regra' ou 'regra' => mensagem */
    public function messages(): array
    {
        return [];
    }

    /** @return array<string, string> 'campo' => nome usado em :attribute */
    public function attributes(): array
    {
        return [];
    }

    /** Para onde voltar quando o Referer nao serve (ausente ou de outro host). */
    public function redirectTo(): string
    {
        return '/';
    }

    /** Ajuste da entrada antes de validar (aparar, juntar campos). */
    protected function prepare(array $input): array
    {
        return $input;
    }

    /** Conversao depois de validar, para o formato do banco (data BR -> Y-m-d, dinheiro -> centavos). */
    protected function transform(array $validated): array
    {
        return $validated;
    }

    /** Valida a entrada (padrao: a da requisicao) e devolve os dados transformados. */
    public function validated(?array $input = null): array
    {
        $input = $this->prepare($input ?? static::input());
        $validator = Validator::make($input, $this->rules(), $this->messages(), $this->attributes());

        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), ValidationException::withoutSecrets($input), $this->redirectTo());
        }

        return $this->transform($validator->validated());
    }

    /** Atalho para requests sem contexto: StoreUserRequest::validate(). */
    public static function validate(?array $input = null): array
    {
        return (new static())->validated($input);
    }

    /**
     * Corpo JSON quando o Content-Type e JSON; senao $_FILES + $_POST (os arquivos vem so
     * de $_FILES: um campo de POST com o mesmo nome nao substitui o upload).
     */
    protected static function input(): array
    {
        if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== false) {
            $decoded = json_decode(static::rawBody(), true);

            return is_array($decoded) ? $decoded : [];
        }

        return $_FILES + $_POST;
    }

    protected static function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }
}
