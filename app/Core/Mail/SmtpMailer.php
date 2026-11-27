<?php

namespace App\Core\Mail;

require_once dirname(__DIR__, 3) . '/lib/PHPMailer/Exception.php';
require_once dirname(__DIR__, 3) . '/lib/PHPMailer/PHPMailer.php';
require_once dirname(__DIR__, 3) . '/lib/PHPMailer/SMTP.php';

use App\Core\Env;
use App\Repositories\SettingRepository;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class SmtpMailer implements MailerInterface
{
    /**
     * Variaveis do .env que, quando definidas, tem prioridade sobre os
     * campos de SMTP da tela Configuracoes (tabela settings). Assim a senha
     * do e-mail de producao fica so no .env do servidor, fora do banco.
     */
    public const ENV_KEYS = [
        'smtp_host' => 'SMTP_HOST',
        'smtp_port' => 'SMTP_PORT',
        'smtp_encryption' => 'SMTP_ENCRYPTION',
        'smtp_username' => 'SMTP_USERNAME',
        'smtp_password' => 'SMTP_PASSWORD',
        'smtp_from_address' => 'SMTP_FROM_ADDRESS',
        'smtp_from_name' => 'SMTP_FROM_NAME',
    ];

    private $settings;

    public function __construct(?SettingRepository $settings = null)
    {
        $this->settings = $settings ?: new SettingRepository();
    }

    /** Configuracao de settings com as variaveis SMTP_* do .env por cima (as que existirem). */
    public static function resolveConfig(array $settings): array
    {
        foreach (self::ENV_KEYS as $settingKey => $envKey) {
            $value = Env::get($envKey);

            if ($value !== null && $value !== '') {
                $settings[$settingKey] = $value;
            }
        }

        $settings['smtp_encryption'] = in_array($settings['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true)
            ? $settings['smtp_encryption']
            : 'tls';

        return $settings;
    }

    /** @return string[] chaves de settings que estao vindo do .env */
    public static function envOverriddenKeys(): array
    {
        return array_keys(array_filter(self::ENV_KEYS, static fn (string $envKey): bool => (string) Env::get($envKey, '') !== ''));
    }

    public function send(string $to, string $subject, string $body): bool
    {
        $config = self::resolveConfig($this->settings->all());

        if (trim($config['smtp_host']) === '') {
            error_log('SmtpMailer: SMTP nao configurado, e-mail nao enviado (destinatario: ' . $to . ').');

            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $this->configure($mail, $config);
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

    // Separado de send() pra ser testavel sem rede: so seta propriedades no
    // objeto PHPMailer, nunca chama ->send(). CharSet explicito porque o
    // default do PHPMailer e ISO-8859-1 -- sem isso, um nome de remetente
    // (smtp_from_name) com acento (comum, e um campo livre no painel admin)
    // sai mal codificado no cabecalho From pro cliente de e-mail do
    // destinatario.
    private function configure(PHPMailer $mail, array $config): void
    {
        $mail->CharSet = PHPMailer::CHARSET_UTF8;
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
    }
}
