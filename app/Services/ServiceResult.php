<?php

namespace App\Services;

/**
 * Resultado imutavel de uma operacao de service: sucesso com dados ou
 * falha com um codigo (e mensagem opcional) que o controller traduz em HTTP.
 */
final class ServiceResult
{
    private $ok;
    private $data;
    private $code;
    private $message;

    private function __construct(bool $ok, $data, ?string $code, ?string $message)
    {
        $this->ok = $ok;
        $this->data = $data;
        $this->code = $code;
        $this->message = $message;
    }

    public static function fail(string $code, string $message = ''): ServiceResult
    {
        return new self(false, null, $code, $message);
    }

    /** Falha esperada com detalhes para quem chamou (ex.: os escopos que faltam no consentimento). */
    public static function failWith(string $code, string $message, $data): ServiceResult
    {
        return new self(false, $data, $code, $message);
    }

    public static function ok($data = null): ServiceResult
    {
        return new self(true, $data, null, null);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function data()
    {
        return $this->data;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function message(): ?string
    {
        return $this->message;
    }
}
