<?php

namespace App\Mail;

require_once dirname(__DIR__, 2) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__, 2) . '/lib/PHPMailer/SMTP.php';

use App\Core\Logger;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** O unico lugar que fala com o PHPMailer (vendorizado em lib/PHPMailer). */
final class SmtpTransport implements Transport
{
    private array $config;

    /** @param array|null $config formato de SmtpConfig::resolve() (padrao: SmtpConfig::current()) */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? SmtpConfig::current();
    }

    public function send(Message $message): bool
    {
        if (trim((string) ($this->config['smtp_host'] ?? '')) === '') {
            Logger::warning('SMTP nao configurado: e-mail nao enviado.', ['to' => array_column($message->to, 'address')]);

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $this->configure($mail, $message);
            $mail->send();

            return true;
        } catch (PHPMailerException $exception) {
            Logger::warning('Falha ao enviar e-mail por SMTP.', ['to' => array_column($message->to, 'address'), 'error' => $exception->getMessage()]);

            return false;
        }
    }

    /**
     * So configura o objeto, sem rede (testavel). CharSet explicito: o padrao do
     * PHPMailer e ISO-8859-1, e um nome de remetente com acento sairia mal codificado.
     */
    public function configure(PHPMailer $mail, Message $message): void
    {
        $config = $this->config;

        $mail->CharSet = PHPMailer::CHARSET_UTF8;
        $mail->isSMTP();
        $mail->Host = (string) $config['smtp_host'];
        $mail->Port = (int) $config['smtp_port'];
        $mail->SMTPAuth = trim((string) $config['smtp_username']) !== '';
        $mail->Username = (string) $config['smtp_username'];
        $mail->Password = (string) $config['smtp_password'];

        if ($config['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($config['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPAutoTLS = false;
        }

        $mail->setFrom((string) $config['smtp_from_address'], (string) $config['smtp_from_name']);

        foreach ($message->to as $address) {
            $mail->addAddress($address['address'], $address['name']);
        }

        foreach ($message->cc as $address) {
            $mail->addCC($address['address'], $address['name']);
        }

        foreach ($message->bcc as $address) {
            $mail->addBCC($address['address'], $address['name']);
        }

        if ($message->replyTo !== null) {
            $mail->addReplyTo($message->replyTo['address'], $message->replyTo['name']);
        }

        $mail->Subject = $message->subject;
        $mail->isHTML(true);
        $mail->Body = $message->html;
        $mail->AltBody = $message->text;

        foreach ($message->attachments as $attachment) {
            if (isset($attachment['content'])) {
                $mail->addStringAttachment((string) $attachment['content'], (string) $attachment['name'], PHPMailer::ENCODING_BASE64, (string) ($attachment['mime'] ?? ''));
            } elseif (isset($attachment['path'])) {
                $mail->addAttachment((string) $attachment['path'], (string) ($attachment['name'] ?? ''));
            }
        }
    }
}
