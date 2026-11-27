<?php

declare(strict_types=1);

use App\Validation\Validator;

echo "\nValidator\n";

function v(array $data, array $rules, array $messages = [], array $attributes = []): Validator
{
    return Validator::make($data, $rules, $messages, $attributes);
}

function validatorDb(): \App\Database\Database
{
    $db = testDatabase();
    $db->execute('DROP TABLE IF EXISTS test_users');
    $db->execute('CREATE TABLE test_users (id INT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(180))');
    $db->execute("INSERT INTO test_users (email) VALUES ('ana@exemplo.com'), ('bia@exemplo.com')");

    return $db;
}

function uploadedFile(string $contents, string $name): array
{
    $path = tempDir() . '/' . $name;
    file_put_contents($path, $contents);

    return ['name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($contents)];
}

it('required falha para vazio, espacos, null e lista vazia, e aceita "0"', function (): void {
    foreach (['', '   ', null, []] as $empty) {
        assertTrue(v(['f' => $empty], ['f' => 'required'])->fails());
    }
    assertTrue(v(['f' => '0'], ['f' => 'required'])->passes());
    assertTrue(v([], ['f' => 'required'])->fails());
});

it('campo opcional vazio pula as demais regras', function (): void {
    assertTrue(v(['nick' => ''], ['nick' => 'email|min:10'])->passes());
    assertTrue(v(['nick' => ''], ['nick' => 'nullable|email'])->passes());
});

it('string falha para array sem warning', function (): void {
    error_clear_last();
    assertTrue(v(['name' => ['x']], ['name' => 'required|string|max:5'])->fails());
    assertSame(null, error_get_last());
});

it('email', function (): void {
    assertTrue(v(['e' => 'ana@exemplo.com'], ['e' => 'email'])->passes());
    assertTrue(v(['e' => 'ana@'], ['e' => 'email'])->fails());
});

it('min e max contam caracteres multibyte', function (): void {
    assertTrue(v(['t' => 'ção'], ['t' => 'min:3|max:3'])->passes());
    assertTrue(v(['t' => 'çã'], ['t' => 'min:3'])->fails());
    assertTrue(v(['t' => 'abcdef'], ['t' => 'max:5'])->fails());
});

it('max com numeric compara o valor', function (): void {
    assertTrue(v(['n' => '9'], ['n' => 'numeric|max:10'])->passes());
    assertTrue(v(['n' => '11'], ['n' => 'numeric|max:10'])->fails());
});

it('between, in, numeric, integer e regex', function (): void {
    assertTrue(v(['n' => '5'], ['n' => 'integer|between:1,10'])->passes());
    assertTrue(v(['n' => '50'], ['n' => 'integer|between:1,10'])->fails());
    assertTrue(v(['c' => 'b'], ['c' => 'in:a,b'])->passes());
    assertTrue(v(['c' => 'z'], ['c' => 'in:a,b'])->fails());
    assertTrue(v(['n' => 'abc'], ['n' => 'numeric'])->fails());
    assertTrue(v(['n' => '1.5'], ['n' => 'integer'])->fails());
    assertTrue(v(['n' => '123'], ['n' => 'regex:/^\d+$/'])->passes());
    assertTrue(v(['n' => '12a'], ['n' => 'regex:/^\d+$/'])->fails());
});

it('confirmed e same', function (): void {
    assertTrue(v(['password' => 'segredo12', 'password_confirmation' => 'segredo12'], ['password' => 'confirmed'])->passes());
    assertTrue(v(['password' => 'segredo12', 'password_confirmation' => 'outra'], ['password' => 'confirmed'])->fails());
    assertTrue(v(['a' => 'x', 'b' => 'y'], ['a' => 'same:b'])->fails());
});

it('date, before e after (literal ou outro campo)', function (): void {
    assertTrue(v(['d' => '2026-02-31'], ['d' => 'date'])->fails());
    assertTrue(v(['d' => '2026-11-24'], ['d' => 'date|before:2026-12-31'])->passes());
    assertTrue(v(['start' => '2026-11-24', 'end' => '2026-11-20'], ['end' => 'date|after:start'])->fails());
    assertTrue(v(['start' => '2026-11-24', 'end' => '2026-11-25'], ['end' => 'date|after:start'])->passes());
});

it('unique com e sem ignorar o proprio registro, e exists', function (): void {
    $db = validatorDb();
    $id = (int) $db->fetch("SELECT id FROM test_users WHERE email = 'ana@exemplo.com'")['id'];

    assertTrue(v(['email' => 'ana@exemplo.com'], ['email' => 'unique:test_users,email'])->setDatabase($db)->fails());
    assertTrue(v(['email' => 'nova@exemplo.com'], ['email' => 'unique:test_users,email'])->setDatabase($db)->passes());
    assertTrue(v(['email' => 'ana@exemplo.com'], ['email' => 'unique:test_users,email,' . $id])->setDatabase($db)->passes());
    assertTrue(v(['email' => 'bia@exemplo.com'], ['email' => 'exists:test_users,email'])->setDatabase($db)->passes());
    assertTrue(v(['email' => 'zz@exemplo.com'], ['email' => 'exists:test_users,email'])->setDatabase($db)->fails());
});

it('unique com nome de tabela invalido lanca excecao', function (): void {
    $db = validatorDb();

    assertThrows(function () use ($db): void {
        v(['email' => 'x@y.com'], ['email' => 'unique:users;drop,email'])->setDatabase($db)->passes();
    });
});

it('file, mimes pelo conteudo e max_size', function (): void {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

    assertTrue(v(['f' => uploadedFile($png, 'a.png')], ['f' => 'file|mimes:png'])->passes());
    assertTrue(v(['f' => uploadedFile('nao sou imagem', 'b.png')], ['f' => 'file|mimes:png'])->fails());
    assertTrue(v(['f' => uploadedFile(str_repeat('x', 2048), 'c.txt')], ['f' => 'file|max_size:1'])->fails());
    assertTrue(v(['f' => 'texto'], ['f' => 'file'])->fails());
});

it('cpf, cnpj alfanumerico, cep e phone', function (): void {
    assertTrue(v(['c' => '529.982.247-25'], ['c' => 'cpf'])->passes());
    assertTrue(v(['c' => '111.111.111-11'], ['c' => 'cpf'])->fails());
    assertTrue(v(['c' => '12.ABC.345/01DE-35'], ['c' => 'cnpj'])->passes());
    assertTrue(v(['c' => '12.abc.345/01de-35'], ['c' => 'cnpj'])->passes());
    assertTrue(v(['c' => '12.ABC.345/01DE-36'], ['c' => 'cnpj'])->fails());
    assertTrue(v(['c' => '29100-000'], ['c' => 'cep'])->passes());
    assertTrue(v(['c' => '2910'], ['c' => 'cep'])->fails());
    assertTrue(v(['p' => '(27) 99999-1234'], ['p' => 'phone'])->passes());
    assertTrue(v(['p' => '123'], ['p' => 'phone'])->fails());
});

it('date_br, money, decimal_br e weight', function (): void {
    assertTrue(v(['d' => '24/11/2026'], ['d' => 'date_br'])->passes());
    assertTrue(v(['d' => '31/02/2026'], ['d' => 'date_br'])->fails());
    assertTrue(v(['m' => '1.234,56'], ['m' => 'money'])->passes());
    assertTrue(v(['m' => '1.234.56'], ['m' => 'money'])->fails());
    assertTrue(v(['n' => '1,234'], ['n' => 'decimal_br:2'])->fails());
    assertTrue(v(['n' => '1,23'], ['n' => 'decimal_br:2'])->passes());
    assertTrue(v(['w' => '1.234,567'], ['w' => 'weight'])->passes());
    assertTrue(v(['w' => '1,2345'], ['w' => 'weight'])->fails());
});

it('mensagens usam attributes, mensagem por campo.regra e fallback com espacos', function (): void {
    assertSame('O campo nome é obrigatório.', v([], ['name' => 'required'], [], ['name' => 'nome'])->errors()->first('name'));
    assertSame('Diga seu nome.', v([], ['name' => 'required'], ['name.required' => 'Diga seu nome.'])->errors()->first('name'));
    assertSame('O campo full name é obrigatório.', v([], ['full_name' => 'required'])->errors()->first('full_name'));
    assertSame('O campo nome deve ter no máximo 5 caracteres.', v(['name' => 'abcdefg'], ['name' => 'max:5'], [], ['name' => 'nome'])->errors()->first('name'));
});

it('errors junta todas as mensagens do campo e first devolve a primeira', function (): void {
    $errors = v(['e' => 'x'], ['e' => 'email|min:3'])->errors();

    assertSame(2, count($errors->get('e')));
    assertSame($errors->get('e')[0], $errors->first('e'));
    assertTrue($errors->has('e'));
    assertFalse($errors->has('outro'));
});

it('validated so devolve campos com regra', function (): void {
    $validator = v(['name' => 'Ana', 'is_admin' => '1'], ['name' => 'required']);

    assertSame(['name' => 'Ana'], $validator->validated());
});

it('regras em array funcionam como no pipe', function (): void {
    assertTrue(v(['n' => 'abcdef'], ['n' => ['required', 'max:5']])->fails());
});

it('extend registra regra com mensagem propria', function (): void {
    Validator::extend('even', fn ($value): bool => (int) $value % 2 === 0, 'O campo :attribute deve ser par.');

    assertTrue(v(['n' => '4'], ['n' => 'even'])->passes());
    assertSame('O campo n deve ser par.', v(['n' => '3'], ['n' => 'even'])->errors()->first('n'));
});
