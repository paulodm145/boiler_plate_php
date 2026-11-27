<?php

declare(strict_types=1);

use App\Helpers\Br\Date;
use App\Http\Form;
use App\Http\FormRequest;
use App\Http\ValidationResponder;
use App\Validation\ErrorBag;
use App\Validation\ValidationException;

echo "\nFormRequest\n";

class TestSignupRequest extends FormRequest
{
    public static string $body = '';

    public function rules(): array
    {
        return [
            'name' => 'required|max:5',
            'email' => 'required|email',
            'password' => 'required|confirmed',
            'born' => 'nullable|date_br',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome'];
    }

    public function redirectTo(): string
    {
        return '/signup';
    }

    protected function transform(array $validated): array
    {
        if (isset($validated['born'])) {
            $validated['born'] = Date::parse($validated['born']);
        }

        return $validated;
    }

    public static function exposedInput(): array
    {
        return static::input();
    }

    protected static function rawBody(): string
    {
        return self::$body;
    }
}

function validSignup(): array
{
    return ['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo12', 'password_confirmation' => 'segredo12', 'born' => '24/11/2026'];
}

function resetValidationSession(): void
{
    unset($_SESSION['_errors'], $_SESSION['_old'], $_SESSION['_errors_current'], $_SESSION['_old_current']);
}

it('validate devolve os dados transformados', function (): void {
    $data = TestSignupRequest::validate(validSignup());

    assertSame('2026-11-24', $data['born']);
    assertSame('Ana', $data['name']);
    assertFalse(isset($data['password_confirmation']));
});

it('falha lanca ValidationException com erros por campo e o destino do request', function (): void {
    try {
        TestSignupRequest::validate(['name' => 'Anastacia', 'email' => 'x']);
        throw new RuntimeException('devia ter lancado');
    } catch (ValidationException $exception) {
        assertSame('O campo nome deve ter no máximo 5 caracteres.', $exception->errors()->first('name'));
        assertTrue($exception->errors()->has('email'));
        assertTrue($exception->errors()->has('password'));
        assertSame('/signup', $exception->redirectTo());
    }
});

it('old da excecao nao leva campos de senha', function (): void {
    try {
        TestSignupRequest::validate(['name' => 'Anastacia'] + validSignup());
        throw new RuntimeException('devia ter lancado');
    } catch (ValidationException $exception) {
        assertSame('Anastacia', $exception->old()['name']);
        assertFalse(array_key_exists('password', $exception->old()));
        assertFalse(array_key_exists('password_confirmation', $exception->old()));
    }
});

it('le o corpo JSON quando o Content-Type e JSON', function (): void {
    $_SERVER['CONTENT_TYPE'] = 'application/json; charset=utf-8';
    TestSignupRequest::$body = json_encode(['name' => 'Bia']);

    try {
        assertSame(['name' => 'Bia'], TestSignupRequest::exposedInput());
    } finally {
        unset($_SERVER['CONTENT_TYPE']);
    }
});

it('redirectTarget segue Referer do mesmo host', function (): void {
    assertSame('/admin/users/create?x=1', ValidationResponder::redirectTarget('http://localhost:8002/admin/users/create?x=1', 'localhost:8002', '/'));
});

it('redirectTarget ignora Referer de outro host ou ausente', function (): void {
    assertSame('/admin', ValidationResponder::redirectTarget('https://evil.test/x', 'localhost:8002', '/admin'));
    assertSame('/admin', ValidationResponder::redirectTarget(null, 'localhost:8002', '/admin'));
    assertSame('/admin', ValidationResponder::redirectTarget('//evil.test/x', 'localhost:8002', '/admin'));
});

it('jsonBody tem message e errors por campo', function (): void {
    $body = ValidationResponder::jsonBody(new ErrorBag(['email' => ['Informe um e-mail válido em email.']]));

    assertSame('Os dados informados são inválidos.', $body['message']);
    assertSame(['email' => ['Informe um e-mail válido em email.']], $body['errors']);
});

it('primeira visita: old devolve o padrao e errors vazio', function (): void {
    resetValidationSession();
    flash_validation_state();

    assertSame('x', old('name', 'x'));
    assertFalse(errors()->any());

    ob_start();
    require dirname(__DIR__, 2) . '/app/Views/admin/partials/form-errors.php';
    assertSame('', trim((string) ob_get_clean()));
});

it('erros e old valem por uma requisicao', function (): void {
    resetValidationSession();
    $_SESSION['_errors'] = ['name' => ['O campo nome é obrigatório.']];
    $_SESSION['_old'] = ['name' => 'Ana'];

    flash_validation_state();
    assertSame('Ana', old('name'));
    assertSame('O campo nome é obrigatório.', errors()->first('name'));

    flash_validation_state();
    assertSame('', old('name'));
    assertFalse(errors()->any());
});

it('Form::invalid e feedback escapam e so aparecem com erro', function (): void {
    resetValidationSession();
    $_SESSION['_errors'] = ['name' => ['<b>ruim</b>']];
    flash_validation_state();

    assertSame(' is-invalid', Form::invalid('name'));
    assertSame('<div class="invalid-feedback">&lt;b&gt;ruim&lt;/b&gt;</div>', Form::feedback('name'));
    assertSame('', Form::invalid('email'));
    assertSame('', Form::feedback('email'));

    resetValidationSession();
    flash_validation_state();
});

it('old com valor que nao e texto devolve o padrao, sem warning', function (): void {
    resetValidationSession();
    $_SESSION['_old'] = ['name' => ['x']];
    flash_validation_state();
    error_clear_last();

    assertSame('padrao', old('name', 'padrao'));
    assertSame(null, error_get_last());

    resetValidationSession();
    flash_validation_state();
});

it('withoutSecrets tira qualquer chave com password, inclusive aninhada', function (): void {
    $kept = ValidationException::withoutSecrets([
        'name' => 'Ana',
        'smtp_password' => 'segredo',
        'Current_Password' => 'x',
        'user' => ['email' => 'a@b.com', 'password' => 'y'],
    ]);

    assertSame(['name' => 'Ana', 'user' => ['email' => 'a@b.com']], $kept);
});

it('UpdateSettingsRequest apara os campos antes de validar', function (): void {
    $data = (new \App\Http\Requests\UpdateSettingsRequest())->validated([
        'site_name' => '  Meu App ', 'smtp_port' => ' 587 ', 'smtp_encryption' => 'tls', 'smtp_from_address' => 'a@b.com ',
    ]);

    assertSame('587', $data['smtp_port']);
    assertSame('Meu App', $data['site_name']);
    assertSame('a@b.com', $data['smtp_from_address']);
});
