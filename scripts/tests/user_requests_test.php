<?php

declare(strict_types=1);

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Validation\ValidationException;

echo "\nRequests de usuario\n";

function requestErrors(\App\Http\FormRequest $request, array $input): ?\App\Validation\ErrorBag
{
    try {
        $request->validated($input);

        return null;
    } catch (ValidationException $exception) {
        return $exception->errors();
    }
}

it('StoreUserRequest recusa e-mail repetido e senha sem confirmacao', function (): void {
    $db = usersTable();
    (new \App\Repositories\UserRepository($db))->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    $errors = requestErrors((new StoreUserRequest())->withDatabase($db), ['name' => 'Outra', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    assertSame('Este e-mail já está em uso.', $errors->first('email'));
    assertSame('A confirmação de senha não confere.', $errors->first('password'));
});

it('StoreUserRequest: nome vazio da a mensagem com o nome amigavel', function (): void {
    $errors = requestErrors((new StoreUserRequest())->withDatabase(usersTable()), ['email' => 'x@y.com']);

    assertSame('O campo nome é obrigatório.', $errors->first('name'));
});

it('UpdateUserRequest aceita o proprio e-mail e senha vazia', function (): void {
    $db = usersTable();
    $repository = new \App\Repositories\UserRepository($db);
    $repository->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $ana = $repository->findByEmail('ana@exemplo.com');

    $data = (new UpdateUserRequest($ana['uuid']))->withDatabase($db)
        ->validated(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => '', 'password_confirmation' => '']);

    assertSame('ana@exemplo.com', $data['email']);
});

it('UpdateUserRequest recusa o e-mail de outra pessoa', function (): void {
    $db = usersTable();
    $repository = new \App\Repositories\UserRepository($db);
    $repository->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $repository->create(['name' => 'Bia', 'email' => 'bia@exemplo.com', 'password' => 'segredo123']);
    $bia = $repository->findByEmail('bia@exemplo.com');

    $errors = requestErrors((new UpdateUserRequest($bia['uuid']))->withDatabase($db), ['name' => 'Bia', 'email' => 'ana@exemplo.com']);

    assertSame('Este e-mail já está em uso.', $errors->first('email'));
});
