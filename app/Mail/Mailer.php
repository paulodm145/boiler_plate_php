<?php

namespace App\Mail;

use App\Core\Env;
use App\Core\Logger;

/**
 * Envia Mailables pelo transporte da aplicacao:
 *
 *     Mailer::send((new PasswordResetMail($link))->to($email));
 *
 * MAIL_TRANSPORT=smtp|log no .env; vazio = smtp se houver servidor configurado,
 * senao log (o e-mail vai para storage/logs em vez de sumir). Nunca lanca:
 * falha vira false + warning no log. Nos testes, Mailer::fake().
 */
final class Mailer
{
    private static ?Transport $transport = null;

    private function __construct()
    {
    }

    public static function send(Mailable $mail): bool
    {
        try {
            $message = $mail->build();
        } catch (\Throwable $exception) {
            Logger::warning('E-mail nao montado', ['mail' => $mail::class, 'error' => $exception->getMessage()]);

            return false;
        }

        try {
            $sent = self::transport()->send($message);
        } catch (\Throwable $exception) {
            $sent = false;
        }

        if (!$sent) {
            Logger::warning('E-mail nao enviado', ['to' => array_column($message->to, 'address'), 'subject' => $message->subject]);
        }

        return $sent;
    }

    public static function useTransport(Transport $transport): void
    {
        self::$transport = $transport;
    }

    /** Troca o transporte por um em memoria e o devolve (testes). */
    public static function fake(): ArrayTransport
    {
        $fake = new ArrayTransport();
        self::$transport = $fake;

        return $fake;
    }

    public static function reset(): void
    {
        self::$transport = null;
    }

    public static function defaultTransport(): Transport
    {
        $choice = strtolower((string) Env::get('MAIL_TRANSPORT', ''));

        if ($choice === 'log') {
            return new LogTransport();
        }

        if ($choice === 'smtp') {
            return new SmtpTransport();
        }

        try {
            $config = SmtpConfig::current();
        } catch (\Throwable $ignored) {
            return new LogTransport();
        }

        return trim((string) ($config['smtp_host'] ?? '')) !== '' ? new SmtpTransport($config) : new LogTransport();
    }

    private static function transport(): Transport
    {
        return self::$transport ??= self::defaultTransport();
    }
}
