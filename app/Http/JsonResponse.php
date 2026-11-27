<?php

namespace App\Http;

use App\Services\ServiceResult;

/**
 * Resposta JSON sem envelope: o corpo e exatamente o que foi passado.
 * fromResult() traduz um ServiceResult em status HTTP (statusFor()).
 */
final class JsonResponse
{
    private const STATUS_BY_CODE = ['not_found' => 404, 'forbidden' => 403];

    private function __construct()
    {
    }

    public static function send(mixed $data, int $status = 200): void
    {
        http_response_code($status);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($data);
    }

    /** {"error": "email_taken", "message": "..."} */
    public static function error(string $code, int $status, string $message = ''): void
    {
        self::send(['error' => $code, 'message' => $message], $status);
    }

    /** Sucesso: 200 com os dados do Service; falha: error() com o status de statusFor(). */
    public static function fromResult(ServiceResult $result): void
    {
        if ($result->isOk()) {
            self::send($result->data());

            return;
        }

        self::error((string) $result->code(), self::statusFor($result), (string) $result->message());
    }

    /** ok -> 200; not_found -> 404; forbidden -> 403; qualquer outra falha esperada -> 422. */
    public static function statusFor(ServiceResult $result): int
    {
        if ($result->isOk()) {
            return 200;
        }

        return self::STATUS_BY_CODE[(string) $result->code()] ?? 422;
    }
}
