<?php

namespace App\Mail;

use App\Core\View;
use App\Repositories\SettingRepository;

/**
 * Um tipo de e-mail do sistema, no estilo dos Mailables do Laravel: assunto,
 * template em app/Views/emails/<view>.php e os dados dele. O HTML sai dentro de
 * emails/_layout.php e o texto alternativo e gerado do HTML (ou de text()).
 *
 *     Mailer::send((new PasswordResetMail($link))->to($email));
 */
abstract class Mailable
{
    private array $to = [];
    private array $cc = [];
    private array $bcc = [];
    private ?array $replyTo = null;

    abstract public function subject(): string;

    /** Nome do template em app/Views/emails, sem .php. */
    abstract protected function view(): string;

    /** Variaveis do template. */
    protected function data(): array
    {
        return [];
    }

    /** Anexos (formato em App\Mail\Message). */
    protected function attachments(): array
    {
        return [];
    }

    /** Texto alternativo proprio; null = gerado do HTML. */
    protected function text(): ?string
    {
        return null;
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->to[] = $this->entry($address, $name);

        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->cc[] = $this->entry($address, $name);

        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->bcc[] = $this->entry($address, $name);

        return $this;
    }

    public function replyTo(string|array $address, ?string $name = null): static
    {
        $this->replyTo = $this->entry($address, $name);

        return $this;
    }

    public function build(): Message
    {
        $content = View::capture('emails/' . $this->view(), $this->data());
        $html = View::capture('emails/_layout', [
            'content' => $content,
            'subject' => $this->subject(),
            'siteName' => self::siteName(),
        ]);

        return new Message(
            $this->to,
            $this->subject(),
            $html,
            $this->text() ?? self::textFromHtml($content),
            $this->cc,
            $this->bcc,
            $this->replyTo,
            $this->attachments()
        );
    }

    /** Texto puro a partir do HTML do e-mail: blocos viram quebra de linha e links viram "texto (url)". */
    public static function textFromHtml(string $html): string
    {
        $text = (string) preg_replace_callback(
            '#<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>#is',
            static fn (array $m): string => trim(strip_tags($m[3])) . ' (' . $m[2] . ')',
            $html
        );
        $text = (string) preg_replace('#<br\s*/?>|</(p|div|li|h[1-6]|tr)>#i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = array_map('trim', explode("\n", str_replace("\r", '', $text)));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    private function entry(string|array $address, ?string $name): string|array
    {
        return is_string($address) && $name !== null ? ['address' => $address, 'name' => $name] : $address;
    }

    private static function siteName(): string
    {
        try {
            return (string) ((new SettingRepository())->all()['site_name'] ?? 'App');
        } catch (\Throwable $ignored) {
            return 'App';
        }
    }
}
