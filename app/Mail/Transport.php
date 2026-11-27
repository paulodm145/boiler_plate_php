<?php

namespace App\Mail;

/** Quem entrega a mensagem (SMTP, log, memoria nos testes). Nao lanca: falha e false. */
interface Transport
{
    public function send(Message $message): bool;
}
