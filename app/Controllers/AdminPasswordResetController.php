<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\View;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;

/** Recuperacao de senha: limite por IP aqui (protecao HTTP), regra no PasswordResetService. */
class AdminPasswordResetController
{
    private const INVALID_LINK = 'Este link é inválido ou já expirou.';
    private const TOO_MANY = 'Muitas tentativas. Tente novamente mais tarde.';

    private PasswordResetService $resets;

    public function __construct()
    {
        $this->resets = new PasswordResetService();
    }

    public function forgotForm(): void
    {
        View::render('admin/auth/forgot-password', ['title' => 'Esqueci minha senha | Admin', 'message' => null, 'error' => null]);
    }

    public function forgotSubmit(): void
    {
        if (RateLimiter::hit('password-reset-request:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 15) > 3) {
            http_response_code(429);
            View::render('admin/auth/forgot-password', ['title' => 'Esqueci minha senha | Admin', 'message' => null, 'error' => self::TOO_MANY]);

            return;
        }

        $data = ForgotPasswordRequest::validate();
        $this->resets->requestLink($data['email']);

        View::render('admin/auth/forgot-password', [
            'title' => 'Esqueci minha senha | Admin',
            'message' => 'Se esse e-mail existir, enviamos um link de redefinição.',
            'error' => null,
        ]);
    }

    public function resetForm(string $token): void
    {
        $valid = $this->resets->isValidToken($token);

        View::render('admin/auth/reset-password', [
            'title' => 'Redefinir senha | Admin',
            'token' => $token,
            'validToken' => $valid,
            'error' => $valid ? null : self::INVALID_LINK,
        ]);
    }

    public function resetSubmit(string $token): void
    {
        if (RateLimiter::hit('password-reset-submit:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 15) > 3) {
            http_response_code(429);
            View::render('admin/auth/reset-password', ['title' => 'Redefinir senha | Admin', 'token' => $token, 'validToken' => true, 'error' => self::TOO_MANY]);

            return;
        }

        if (!$this->resets->isValidToken($token)) {
            View::render('admin/auth/reset-password', ['title' => 'Redefinir senha | Admin', 'token' => $token, 'validToken' => false, 'error' => self::INVALID_LINK]);

            return;
        }

        $data = (new ResetPasswordRequest($token))->validated();
        $result = $this->resets->reset($token, $data['password']);

        if (!$result->isOk()) {
            View::render('admin/auth/reset-password', ['title' => 'Redefinir senha | Admin', 'token' => $token, 'validToken' => false, 'error' => self::INVALID_LINK]);

            return;
        }

        Auth::login($result->data());
        http_response_code(303);
        header('Location: /admin');
    }
}
