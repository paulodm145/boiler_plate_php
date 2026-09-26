<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\View;
use App\Repositories\UserRepository;

class AdminAuthController
{
    private $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function loginForm(): void
    {
        if (Auth::check()) {
            header('Location: /admin');
            return;
        }

        View::render('admin/auth/login', [
            'title' => 'Login administrativo | Admin',
            'error' => null,
        ]);
    }

    public function login(): void
    {
        // REMOTE_ADDR, nao X-Forwarded-For: nao ha configuracao de proxy
        // confiavel neste boilerplate, e X-Forwarded-For e trivialmente
        // forjavel pelo proprio cliente. Se o deploy ficar atras de um
        // proxy/CDN reverso, REMOTE_ADDR passa a ser o IP do proxy pra
        // toda requisicao -- um admin errando a senha bloquearia todo
        // mundo atras do mesmo proxy por 15 min. Documentado aqui de
        // proposito, nao resolvido: exige saber em que proxy confiar, o
        // que este boilerplate nao assume.
        $key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // hit() roda incondicionalmente, antes de qualquer coisa, e a
        // contagem que ele devolve e que decide o bloqueio -- nao um
        // RateLimiter::tooManyAttempts() separado seguido de hit() so na
        // falha. Um check-then-act em duas chamadas distintas deixaria
        // requisicoes concorrentes lerem a mesma contagem "ainda dentro do
        // limite" antes de qualquer uma delas gravar sua propria tentativa,
        // permitindo passar do limite sob concorrencia. hit() e atomico
        // (INSERT...ON DUPLICATE KEY UPDATE com lock de linha do InnoDB) --
        // a leitura da contagem devolvida sempre enxerga, no minimo, o
        // proprio incremento desta requisicao.
        $attempts = RateLimiter::hit($key, 15);

        if ($attempts > 5) {
            http_response_code(429);
            View::render('admin/auth/login', [
                'title' => 'Login administrativo | Admin',
                'error' => $this->throttledMessage($key),
            ]);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $user = $this->users->findByEmail($email);

        if ($user === null || !$this->users->verifyPassword($password, $user)) {
            http_response_code(422);
            View::render('admin/auth/login', [
                'title' => 'Login administrativo | Admin',
                'error' => 'E-mail ou senha invalidos.',
            ]);
            return;
        }

        RateLimiter::clear($key);
        Auth::login($user);
        header('Location: /admin');
    }

    private function throttledMessage(string $key): string
    {
        $minutes = (int) ceil(RateLimiter::availableInSeconds($key) / 60);

        return 'Muitas tentativas. Tente novamente em ' . $minutes . ' minuto(s).';
    }

    public function logout(): void
    {
        Auth::logout();
        header('Location: /admin/login');
    }
}
