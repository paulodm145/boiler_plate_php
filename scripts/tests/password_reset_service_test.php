<?php

declare(strict_types=1);

use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\PasswordResetService;

echo "\nPasswordResetService\n";

function resetServiceFixture(): array
{
    [, $users, $resets, $mailer] = controllerWithTestRepositories();

    return [new PasswordResetService($users, $resets), $users, $resets, $mailer];
}

it('requestLink de e-mail existente grava o token e envia; inexistente nao envia', function (): void {
    [$service, $users, $resets, $mailer] = resetServiceFixture();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    $service->requestLink('ana@exemplo.com');
    $service->requestLink('ninguem@exemplo.com');

    assertSame(1, count($mailer->messages));
    assertSame('ana@exemplo.com', $mailer->messages[0]->to[0]['address']);
    assertSame('Redefinição de senha', $mailer->messages[0]->subject);
    preg_match('#/admin/redefinir-senha/([0-9a-f]{64})#', $mailer->messages[0]->text, $match);
    assertTrue($service->isValidToken($match[1]));
});

it('reset com token valido troca a senha, apaga o token e devolve o usuario', function (): void {
    [$service, $users, $resets] = resetServiceFixture();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    $result = $service->reset($token, 'novaSenha123');

    assertTrue($result->isOk());
    assertSame('ana@exemplo.com', $result->data()['email']);
    assertTrue($users->verifyPassword('novaSenha123', $users->findByEmail('ana@exemplo.com')));
    assertFalse($service->isValidToken($token));
});

it('reset com token invalido falha com invalid_token', function (): void {
    [$service] = resetServiceFixture();

    assertSame('invalid_token', $service->reset('nao-existe', 'novaSenha123')->code());
});

it('reset com token orfao falha e apaga o token', function (): void {
    [$service, $users, $resets] = resetServiceFixture();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);
    $users->deleteByEmail('ana@exemplo.com');

    assertSame('invalid_token', $service->reset($token, 'novaSenha123')->code());
    assertSame(null, $resets->findByToken($token));
});
