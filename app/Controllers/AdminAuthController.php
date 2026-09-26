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
        $key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 5)) {
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
            RateLimiter::hit($key, 15);
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
