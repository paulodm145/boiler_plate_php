<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Mail\MailerInterface;
use App\Core\Mail\SmtpMailer;
use App\Core\RateLimiter;
use App\Core\Validator;
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

    public function resetForm(string $token): void
    {
        $valid = $this->resets->findByToken($token) !== null;

        View::render('admin/auth/reset-password', [
            'title' => 'Redefinir senha | Admin',
            'token' => $token,
            'validToken' => $valid,
            'error' => $valid ? null : 'Este link e invalido ou ja expirou.',
        ]);
    }

    public function resetSubmit(string $token): void
    {
        $key = 'password-reset-submit:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (RateLimiter::hit($key, 15) > 3) {
            http_response_code(429);
            View::render('admin/auth/reset-password', [
                'title' => 'Redefinir senha | Admin',
                'token' => $token,
                'validToken' => true,
                'error' => 'Muitas tentativas. Tente novamente mais tarde.',
            ]);
            return;
        }

        $reset = $this->resets->findByToken($token);

        if ($reset === null) {
            View::render('admin/auth/reset-password', [
                'title' => 'Redefinir senha | Admin',
                'token' => $token,
                'validToken' => false,
                'error' => 'Este link e invalido ou ja expirou.',
            ]);
            return;
        }

        $password = (string) ($_POST['password'] ?? '');

        if (!Validator::minLength($password, 8)) {
            View::render('admin/auth/reset-password', [
                'title' => 'Redefinir senha | Admin',
                'token' => $token,
                'validToken' => true,
                'error' => 'A senha precisa de pelo menos 8 caracteres.',
            ]);
            return;
        }

        $user = $this->users->findByEmail($reset['email']);
        $this->users->updatePassword((int) $user['id'], $password);
        $this->resets->deleteByToken($token);

        Auth::login($user);
        header('Location: /admin');
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
