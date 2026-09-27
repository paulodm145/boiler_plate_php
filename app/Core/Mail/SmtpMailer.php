<?php

namespace App\Core\Mail;

require_once dirname(__DIR__, 3) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__, 3) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__, 3) . '/lib/PHPMailer/SMTP.php';

use App\Repositories\SettingRepository;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class SmtpMailer implements MailerInterface
{
    private $settings;

    public function __construct(?SettingRepository $settings = null)
    {
        $this->settings = $settings ?: new SettingRepository();
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $config = $this->settings->all();

        if (trim($config['smtp_host']) === '') {
            error_log('SmtpMailer: SMTP nao configurado, e-mail nao enviado (destinatario: ' . $to . ').');

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['smtp_host'];
            $mail->Port = (int) $config['smtp_port'];
            $mail->SMTPAuth = trim($config['smtp_username']) !== '';
            $mail->Username = $config['smtp_username'];
            $mail->Password = $config['smtp_password'];

            if ($config['smtp_encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($config['smtp_encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($config['smtp_from_address'], $config['smtp_from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            $mail->send();

            return true;
        } catch (PHPMailerException $exception) {
            error_log('SmtpMailer: falha ao enviar e-mail para ' . $to . ': ' . $exception->getMessage());

            return false;
        }
    }
}
