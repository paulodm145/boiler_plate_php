<?php

namespace App\Core\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body): bool;
}
