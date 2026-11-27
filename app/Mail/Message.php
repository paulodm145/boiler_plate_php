<?php

namespace App\Mail;

use InvalidArgumentException;

/**
 * Um e-mail pronto para o transporte. Imutavel e ja limpo: enderecos conferidos
 * e assunto/nomes sem quebra de linha (injecao de cabecalho).
 *
 * Endereco: 'ana@x.com' ou ['address' => 'ana@x.com', 'name' => 'Ana'].
 * Anexo: ['path' => '/caminho', 'name' => 'a.pdf'] ou ['content' => '...', 'name' => 'a.csv', 'mime' => 'text/csv'].
 */
final class Message
{
    /** @var array<int, array{address: string, name: string}> */
    public readonly array $to;
    public readonly array $cc;
    public readonly array $bcc;
    public readonly ?array $replyTo;
    public readonly string $subject;

    public function __construct(
        array $to,
        string $subject,
        public readonly string $html,
        public readonly string $text,
        array $cc = [],
        array $bcc = [],
        string|array|null $replyTo = null,
        public readonly array $attachments = []
    ) {
        if ($to === []) {
            throw new InvalidArgumentException('E-mail sem destinatario.');
        }

        $this->to = self::addresses($to);
        $this->cc = self::addresses($cc);
        $this->bcc = self::addresses($bcc);
        $this->replyTo = $replyTo === null ? null : self::address($replyTo);
        $this->subject = self::oneLine($subject);
    }

    /** Texto de cabecalho em uma linha so: \r e \n viram espaco. */
    public static function oneLine(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    private static function addresses(array $list): array
    {
        return array_map([self::class, 'address'], array_values($list));
    }

    /** @return array{address: string, name: string} */
    private static function address(string|array $entry): array
    {
        $address = trim(is_array($entry) ? (string) ($entry['address'] ?? '') : $entry);
        $name = is_array($entry) ? self::oneLine((string) ($entry['name'] ?? '')) : '';

        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Endereco de e-mail invalido: ' . $address);
        }

        return ['address' => $address, 'name' => $name];
    }
}
