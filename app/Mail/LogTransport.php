<?php

namespace App\Mail;

use App\Core\Env;
use App\Core\Logger;

/**
 * Escreve o e-mail no log em vez de enviar (dev, ou SMTP ainda nao configurado):
 * destinatarios, assunto e o texto, sem anexos. Fora do modo debug, sequencias
 * com cara de token (40+ letras/digitos, como o de redefinicao de senha) viram
 * ":token": quem le o log de producao nao pode usar o link de outra pessoa.
 */
final class LogTransport implements Transport
{
    private bool $revealSecrets;

    /** $revealSecrets: padrao APP_DEBUG=true (em dev o link do e-mail precisa ser clicavel). */
    public function __construct(?bool $revealSecrets = null)
    {
        $this->revealSecrets = $revealSecrets ?? Env::get('APP_DEBUG', 'false') === 'true';
    }

    public function send(Message $message): bool
    {
        $body = $this->revealSecrets ? $message->text : (string) preg_replace('/[A-Za-z0-9_-]{40,}/', ':token', $message->text);

        Logger::info('E-mail (log): ' . $message->subject, [
            'to' => array_column($message->to, 'address'),
            'cc' => array_column($message->cc, 'address'),
            'attachments' => array_column($message->attachments, 'name'),
            'body' => $body,
        ]);

        return true;
    }
}
