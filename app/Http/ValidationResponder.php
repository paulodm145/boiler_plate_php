<?php

namespace App\Http;

use App\Core\Auth;
use App\Core\ErrorPage;
use App\Validation\ErrorBag;
use App\Validation\ValidationException;

/**
 * Resposta a uma ValidationException (chamado pelo Router::dispatch()):
 * JSON -> 422 com os erros por campo; formulario -> erros e entrada na
 * sessao (lidos uma vez, ver flash_validation_state()) e volta para a pagina
 * anterior, so se ela for deste host.
 */
final class ValidationResponder
{
    private function __construct()
    {
    }

    public static function respond(ValidationException $exception): void
    {
        if (ErrorPage::wantsJson($_SERVER, headers_list())) {
            JsonResponse::send(self::jsonBody($exception->errors()), 422);

            return;
        }

        Auth::start();
        $_SESSION['_errors'] = $exception->errors()->all();
        $_SESSION['_old'] = $exception->old();

        http_response_code(303);
        header('Location: ' . self::redirectTarget(
            $_SERVER['HTTP_REFERER'] ?? null,
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            $exception->redirectTo()
        ));
    }

    /** Caminho + query do Referer quando o host dele e $host; senao $fallback (nunca outro host). */
    public static function redirectTarget(?string $referer, string $host, string $fallback): string
    {
        $parts = $referer === null ? false : parse_url($referer);

        if (!is_array($parts) || !isset($parts['host']) || $host === '') {
            return $fallback;
        }

        $refererHost = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        if (strcasecmp($refererHost, $host) !== 0) {
            return $fallback;
        }

        $path = '/' . ltrim($parts['path'] ?? '/', '/');

        return isset($parts['query']) ? $path . '?' . $parts['query'] : $path;
    }

    /** {"message": "...", "errors": {"campo": ["..."]}} */
    public static function jsonBody(ErrorBag $errors): array
    {
        return ['message' => 'Os dados informados são inválidos.', 'errors' => $errors->all()];
    }
}
