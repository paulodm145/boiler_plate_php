<?php

namespace App\Mail;

/** Guarda as mensagens em memoria, para os testes (Mailer::fake()). */
final class ArrayTransport implements Transport
{
    /** @var Message[] */
    public array $messages = [];

    public function send(Message $message): bool
    {
        $this->messages[] = $message;

        return true;
    }

    public function last(): ?Message
    {
        return $this->messages === [] ? null : $this->messages[count($this->messages) - 1];
    }
}
