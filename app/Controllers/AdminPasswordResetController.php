<?php

namespace App\Controllers;

use App\Core\Env;
use App\Core\Mail\MailerInterface;
use App\Core\Mail\SmtpMailer;
use App\Core\RateLimiter;
use App\Core\View;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;

class AdminPasswordResetController
{
    private $users;
    private $resets;
    private $mailer;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->resets = new PasswordResetRepository();
        $this->mailer = new SmtpMailer();
    }

    public function forgotForm(): void
    {
        View::render('admin/auth/forgot-password', [
            'title' => 'Esqueci minha senha | Admin',
            'message' => null,
            'error' => null,
        ]);
    }

    public function forgotSubmit(): void
    {
        $key = 'password-reset-request:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (RateLimiter::hit($key, 15) > 3) {
            http_response_code(429);
            View::render('admin/auth/forgot-password', [
                'title' => 'Esqueci minha senha | Admin',
                'message' => null,
                'error' => 'Muitas tentativas. Tente novamente mais tarde.',
            ]);
            return;
        }

        $email = trim($_POST['email'] ?? '');
        $user = $email !== '' ? $this->users->findByEmail($email) : null;

        if ($user !== null) {
            $this->sendResetLink($email);
        }

        View::render('admin/auth/forgot-password', [
            'title' => 'Esqueci minha senha | Admin',
            'message' => 'Se esse e-mail existir, enviamos um link de redefinicao.',
            'error' => null,
        ]);
    }

    private function sendResetLink(string $email): void
    {
        $token = bin2hex(random_bytes(32));

        $this->resets->create($email, hash('sha256', $token), 60);

        $appUrl = rtrim((string) Env::get('APP_URL', ''), '/');
        $link = $appUrl . '/admin/redefinir-senha/' . $token;

        $this->mailer->send(
            $email,
            'Redefinicao de senha',
            "Recebemos um pedido de redefinicao de senha.\n\n"
                . "Clique no link abaixo pra escolher uma nova senha (valido por 60 minutos):\n"
                . $link . "\n\n"
                . "Se voce nao pediu isso, ignore este e-mail."
        );
    }
}
