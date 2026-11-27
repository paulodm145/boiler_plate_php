<?php

declare(strict_types=1);

use App\Controllers\AdminPasswordResetController;
use App\Core\Auth;
use App\Core\Mail\MailerInterface;
use App\Core\RateLimiter;
use App\Database\Database;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;

echo "\nAdminPasswordResetController\n";

class FakeMailer implements MailerInterface
{
    public $sent = [];

    public function send(string $to, string $subject, string $body): bool
    {
        $this->sent[] = ['to' => $to, 'subject' => $subject, 'body' => $body];

        return true;
    }
}

function controllerWithTestRepositories(): array
{
    $database = testDatabase();
    $database->connection()->exec(
        'CREATE TABLE users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid CHAR(36) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(180) NOT NULL UNIQUE,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $database->connection()->exec(
        'CREATE TABLE rate_limits (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            rate_key VARCHAR(191) NOT NULL UNIQUE,
            attempts INT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    RateLimiter::setConnection($database);

    $users = new UserRepository($database);
    $resets = new PasswordResetRepository($database);
    $mailer = new FakeMailer();

    // O controller nao aceita injecao (nao tem construtor com argumentos): troca o
    // Service dele por um ligado aos repositorios de teste.
    $controller = new AdminPasswordResetController();
    $serviceProperty = new ReflectionProperty(AdminPasswordResetController::class, 'resets');
    $serviceProperty->setValue($controller, new \App\Services\Auth\PasswordResetService($users, $resets, $mailer));

    Auth::logout();

    return [$controller, $users, $resets, $mailer];
}

/** Roda a acao e devolve status, corpo e, se a validacao falhou, os erros (ValidationException). */
function callAction(callable $action): array
{
    $_SERVER['REMOTE_ADDR'] = '203.0.113.9';

    ob_start();

    try {
        $action();
    } catch (\App\Validation\ValidationException $exception) {
        ob_end_clean();

        return ['status' => 422, 'body' => '', 'errors' => $exception->errors()];
    }

    $body = ob_get_clean();

    return ['status' => http_response_code(), 'body' => $body, 'errors' => null];
}

it('pedir reset pra e-mail existente e inexistente devolvem a mesma resposta, so o existente dispara o envio', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    $_POST['email'] = 'ana@exemplo.com';
    $resultExisting = callAction(function () use ($controller): void {
        $controller->forgotSubmit();
    });

    $_POST['email'] = 'nao-existe@exemplo.com';
    $resultMissing = callAction(function () use ($controller): void {
        $controller->forgotSubmit();
    });

    assertSame($resultExisting['body'], $resultMissing['body'], 'a resposta deveria ser identica, exista ou nao o e-mail');
    assertSame(1, count($mailer->sent), 'so o e-mail existente deveria ter disparado o envio');
    assertSame('ana@exemplo.com', $mailer->sent[0]['to']);
});

it('bloqueia a 4a tentativa de pedido de reset no mesmo IP', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();

    for ($i = 0; $i < 3; $i++) {
        $_POST['email'] = 'ninguem@exemplo.com';
        callAction(function () use ($controller): void {
            $controller->forgotSubmit();
        });
    }

    $_POST['email'] = 'ninguem@exemplo.com';
    $result = callAction(function () use ($controller): void {
        $controller->forgotSubmit();
    });

    assertSame(429, $result['status']);
    assertContains('Muitas tentativas', $result['body']);
});

it('resetForm mostra o form pra um token valido e uma mensagem generica pra um invalido', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    ob_start();
    $controller->resetForm($token);
    $validBody = ob_get_clean();

    ob_start();
    $controller->resetForm('token-invalido-que-nao-existe-em-lugar-nenhum');
    $invalidBody = ob_get_clean();

    assertContains('Nova senha', $validBody);
    assertContains('inválido ou já expirou', $invalidBody);
});

it('resetSubmit troca a senha, apaga o token, loga automaticamente, e o token nao funciona de novo', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    $_POST['password'] = 'novaSenha123';
    $_POST['password_confirmation'] = 'novaSenha123';
    callAction(function () use ($controller, $token): void {
        $controller->resetSubmit($token);
    });

    assertTrue(Auth::check(), 'reset valido deveria autenticar a sessao automaticamente');

    $updated = $users->findByEmail('ana@exemplo.com');
    assertTrue($users->verifyPassword('novaSenha123', $updated));
    assertFalse($users->verifyPassword('segredo123', $updated));

    assertSame(null, $resets->findByToken($token), 'token deveria ter sido apagado apos o uso');

    // Reenvia o MESMO token, de verdade, em vez de so checar que a linha
    // sumiu do banco (isso ja e coberto por PasswordResetRepository::deleteByToken()
    // em outro teste) -- prova que o controller de fato rejeita um replay,
    // nao so que o dado foi apagado.
    $_POST['password'] = 'outraSenha456';
    $replay = callAction(function () use ($controller, $token): void {
        $controller->resetSubmit($token);
    });

    assertContains('inválido ou já expirou', $replay['body'], 'reenviar o mesmo token deveria ser rejeitado');

    $afterReplay = $users->findByEmail('ana@exemplo.com');
    assertTrue($users->verifyPassword('novaSenha123', $afterReplay), 'a senha do primeiro reset nao deveria ter sido trocada de novo pelo replay');
});

it('resetSubmit rejeita senha curta, reexibe o form, e nao troca a senha', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    $_POST['password'] = '123';
    $_POST['password_confirmation'] = '123';
    $result = callAction(function () use ($controller, $token): void {
        $controller->resetSubmit($token);
    });

    assertSame('O campo nova senha deve ter pelo menos 8 caracteres.', $result['errors']->first('password'));

    $unchanged = $users->findByEmail('ana@exemplo.com');
    assertTrue($users->verifyPassword('segredo123', $unchanged), 'senha nao deveria ter mudado');
});

it('resetSubmit com token invalido nao altera nenhuma senha', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    $_POST['password'] = 'novaSenha123';
    $_POST['password_confirmation'] = 'novaSenha123';
    $result = callAction(function () use ($controller): void {
        $controller->resetSubmit('token-que-nunca-existiu');
    });

    assertContains('inválido ou já expirou', $result['body']);

    $unchanged = $users->findByEmail('ana@exemplo.com');
    assertTrue($users->verifyPassword('segredo123', $unchanged));
});

it('resetSubmit com token valido mas usuario ja apagado mostra link invalido, sem lancar excecao', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    // Simula o dono do token sendo apagado depois do pedido de reset,
    // dentro da janela de 60 min -- nada cascade-deleta password_resets.
    $users->deleteByEmail('ana@exemplo.com');

    $_POST['password'] = 'novaSenha123';
    $_POST['password_confirmation'] = 'novaSenha123';
    $result = callAction(function () use ($controller, $token): void {
        $controller->resetSubmit($token);
    });

    assertContains('inválido ou já expirou', $result['body']);
    assertFalse(Auth::check(), 'nao deveria autenticar uma sessao pra um usuario que nao existe mais');
    assertSame(null, $resets->findByToken($token), 'a linha orfa deveria ter sido limpa');
});

it('bloqueia a 4a tentativa de submit de reset no mesmo IP', function (): void {
    [$controller, $users, $resets, $mailer] = controllerWithTestRepositories();
    $users->create(['name' => 'Ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $token = bin2hex(random_bytes(32));
    $resets->create('ana@exemplo.com', hash('sha256', $token), 60);

    for ($i = 0; $i < 3; $i++) {
        $_POST['password'] = '123';
        callAction(function () use ($controller, $token): void {
            $controller->resetSubmit($token);
        });
    }

    $_POST['password'] = 'novaSenha123';
    $_POST['password_confirmation'] = 'novaSenha123';
    $result = callAction(function () use ($controller, $token): void {
        $controller->resetSubmit($token);
    });

    assertSame(429, $result['status']);
});
