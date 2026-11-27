<?php

declare(strict_types=1);

use App\Http\SecurityHeaders;

echo "\nSecurityHeaders\n";

it('toda resposta trava plugin, base, moldura e formulario para fora', function (): void {
    $headers = SecurityHeaders::headersFor(false);

    assertSame("object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'", $headers['Content-Security-Policy']);
    assertSame('camera=(), microphone=(), geolocation=(), payment=(), usb=()', $headers['Permissions-Policy']);
    assertSame('same-origin', $headers['Cross-Origin-Opener-Policy']);
});

it('hsts so sob https, sem subdominios nem preload', function (): void {
    assertFalse(isset(SecurityHeaders::headersFor(false)['Strict-Transport-Security']));
    assertSame('max-age=31536000', SecurityHeaders::headersFor(true)['Strict-Transport-Security']);
});

it('sem CSP de scripts nesta fase', function (): void {
    assertFalse(isset(SecurityHeaders::headersFor(true)['Content-Security-Policy-Report-Only']));
});
