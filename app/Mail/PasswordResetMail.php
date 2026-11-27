<?php

namespace App\Mail;

final class PasswordResetMail extends Mailable
{
    public function __construct(private readonly string $link, private readonly int $minutes = 60)
    {
    }

    public function subject(): string
    {
        return 'Redefinição de senha';
    }

    protected function view(): string
    {
        return 'password-reset';
    }

    protected function data(): array
    {
        return ['link' => $this->link, 'minutes' => $this->minutes];
    }
}
