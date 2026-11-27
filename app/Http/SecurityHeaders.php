<?php

namespace App\Http;

/**
 * Cabecalhos de seguranca de toda resposta do PHP. X-Frame-Options, nosniff
 * e Referrer-Policy ficam no .htaccess, porque valem tambem para os arquivos
 * estaticos. CSP de scripts (nonce) ainda nao: fica para a fase dos
 * componentes JS.
 */
final class SecurityHeaders
{
    private const CSP = "object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'";
    private const PERMISSIONS = 'camera=(), microphone=(), geolocation=(), payment=(), usb=()';

    private function __construct()
    {
    }

    public static function send(): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::headersFor(self::isHttps()) as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /** @return array<string, string> */
    public static function headersFor(bool $https): array
    {
        $headers = [
            'Content-Security-Policy' => self::CSP,
            'Permissions-Policy' => self::PERMISSIONS,
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($https) {
            // Sem includeSubDomains/preload: o dominio pode ter subdominios sem HTTPS, e preload e dificil de desfazer.
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        return $headers;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? null) == 443);
    }
}
