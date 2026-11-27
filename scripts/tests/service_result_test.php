<?php

declare(strict_types=1);

use App\Http\JsonResponse;
use App\Services\ServiceResult;

echo "\nServiceResult / JsonResponse\n";

it('ok guarda os dados', function (): void {
    $result = ServiceResult::ok(['id' => 1]);

    assertTrue($result->isOk());
    assertSame(['id' => 1], $result->data());
    assertSame(null, $result->code());
});

it('fail guarda codigo e mensagem', function (): void {
    $result = ServiceResult::fail('email_taken', 'Este e-mail já está em uso.');

    assertFalse($result->isOk());
    assertSame('email_taken', $result->code());
    assertSame('Este e-mail já está em uso.', $result->message());
});

it('statusFor mapeia o codigo para o status HTTP', function (): void {
    assertSame(200, JsonResponse::statusFor(ServiceResult::ok()));
    assertSame(404, JsonResponse::statusFor(ServiceResult::fail('not_found')));
    assertSame(403, JsonResponse::statusFor(ServiceResult::fail('forbidden')));
    assertSame(422, JsonResponse::statusFor(ServiceResult::fail('email_taken')));
});
