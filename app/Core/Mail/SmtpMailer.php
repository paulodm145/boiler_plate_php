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

    /** Configuracao de settings com as variaveis SMTP_* do .env por cima (as que valem, ver envOverrides()). */
    public static function resolveConfig(array $settings): array
    {
        $overrides = self::envOverrides();

        if (isset($overrides['smtp_password'])) {
            // Senha do .env: a conexao inteira sai do .env, nunca da tela. Senao,
            // quem edita a tela trocaria o host e receberia a senha do servidor.
            $settings['smtp_port'] = '587';
            $settings['smtp_encryption'] = 'tls';
        }

        $settings = array_merge($settings, $overrides);

        $allowed = isset($overrides['smtp_password']) ? ['tls', 'ssl'] : ['tls', 'ssl', 'none'];
        $settings['smtp_encryption'] = in_array($settings['smtp_encryption'] ?? '', $allowed, true)
            ? $settings['smtp_encryption']
            : 'tls';

        return $settings;
    }

    /** @return string[] chaves de settings que a tela nao controla (vem do .env), na ordem de ENV_KEYS */
    public static function envOverriddenKeys(): array
    {
        $overrides = self::envOverrides();

        return array_values(array_filter(array_keys(self::ENV_KEYS), static function (string $key) use ($overrides): bool {
            // Com a senha no .env, porta e criptografia tambem saem de la (padrao 587/tls).
            return isset($overrides[$key])
                || (isset($overrides['smtp_password']) && in_array($key, ['smtp_port', 'smtp_encryption'], true));
        }));
    }

    /**
     * Variaveis SMTP_* preenchidas, por chave de settings. SMTP_PASSWORD so vale
     * junto com SMTP_HOST: sem host no .env a senha do .env e ignorada.
     *
     * @return array<string, string>
     */
    private static function envOverrides(): array
    {
        $overrides = [];

        foreach (self::ENV_KEYS as $settingKey => $envKey) {
            $value = (string) Env::get($envKey, '');

            if ($value !== '') {
                $overrides[$settingKey] = $value;
            }
        }

        if (isset($overrides['smtp_password']) && !isset($overrides['smtp_host'])) {
            unset($overrides['smtp_password']);
        }

        return $overrides;
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
