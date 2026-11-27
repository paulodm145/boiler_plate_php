<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Mail\ArrayTransport;
use App\Mail\LogTransport;
use App\Mail\Mailable;
use App\Mail\Mailer;
use App\Mail\Message;
use App\Mail\Transport;

echo "\nMailable / Mailer\n";

class TestWelcomeMail extends Mailable
{
    public function __construct(private readonly string $name)
    {
    }

    public function subject(): string
    {
        return 'Bem-vindo, ' . $this->name;
    }

    protected function view(): string
    {
        return 'test-welcome';
    }

    protected function data(): array
    {
        return ['name' => $this->name];
    }
}

class FailingTransport implements Transport
{
    public function send(Message $message): bool
    {
        return false;
    }
}

/** Template de teste em app/Views/emails, apagado ao final de cada caso. */
function withTestEmailView(callable $body): void
{
    $path = dirname(__DIR__, 2) . '/app/Views/emails/test-welcome.php';
    file_put_contents($path, '<p>Olá, <?= e($name) ?>!</p><p><a href="https://x.test/painel">Abrir o painel</a></p>');

    try {
        $body();
    } finally {
        @unlink($path);
    }
}

it('build renderiza o template no layout, com os dados escapados', function (): void {
    withTestEmailView(function (): void {
        $message = (new TestWelcomeMail('<script>x</script>'))->to('ana@exemplo.com', 'Ana')->build();

        assertContains('Olá, &lt;script&gt;x&lt;/script&gt;!', $message->html);
        assertContains('<html', $message->html);
        assertSame([['address' => 'ana@exemplo.com', 'name' => 'Ana']], $message->to);
        assertContains('Abrir o painel (https://x.test/painel)', $message->text);
    });
});

it('textFromHtml troca paragrafos por quebras e links por texto (url)', function (): void {
    assertSame("Oi\nclique (https://x.test/a)", Mailable::textFromHtml('<p>Oi</p><p><a href="https://x.test/a">clique</a></p>'));
});

it('assunto e nome com quebra de linha saem em uma linha so', function (): void {
    $message = new Message([['address' => 'ana@exemplo.com', 'name' => "Ana\r\nBcc: x@y.com"]], "A\r\nBcc: x@y.com", '<p>x</p>', 'x');

    assertSame('A  Bcc: x@y.com', $message->subject);
    assertFalse(str_contains($message->to[0]['name'], "\n"));
});

it('destinatario invalido lanca excecao', function (): void {
    assertThrows(fn () => new Message(['nao-e-email'], 'A', '<p>x</p>', 'x'));
});

it('Mailer::fake guarda a mensagem enviada', function (): void {
    withTestEmailView(function (): void {
        $fake = Mailer::fake();

        assertTrue(Mailer::send((new TestWelcomeMail('Ana'))->to('ana@exemplo.com')));
        assertSame(1, count($fake->messages));
        assertSame('ana@exemplo.com', $fake->last()->to[0]['address']);
        assertSame('Bem-vindo, Ana', $fake->last()->subject);
        Mailer::reset();
    });
});

it('transporte que falha faz send devolver false e registrar warning', function (): void {
    withTestEmailView(function (): void {
        $dir = tempDir();
        Logger::reset();
        Logger::configure($dir, 'testing', 'debug');
        Mailer::useTransport(new FailingTransport());

        assertFalse(Mailer::send((new TestWelcomeMail('Ana'))->to('ana@exemplo.com')));
        assertContains('WARNING: E-mail nao enviado', (string) file_get_contents($dir . '/app-' . gmdate('Y-m-d') . '.log'));
        Mailer::reset();
        Logger::reset();
    });
});

it('MAIL_TRANSPORT=log escolhe o LogTransport', function (): void {
    putenv('MAIL_TRANSPORT=log');

    try {
        Mailer::reset();
        assertTrue(Mailer::defaultTransport() instanceof LogTransport);
    } finally {
        putenv('MAIL_TRANSPORT');
        Mailer::reset();
    }
});

it('ArrayTransport sem mensagens devolve null em last', function (): void {
    assertSame(null, (new ArrayTransport())->last());
});

it('LogTransport fora do debug mascara tokens do corpo; em debug mostra o link', function (): void {
    $dir = tempDir();
    Logger::reset();
    Logger::configure($dir, 'testing', 'debug');
    $token = str_repeat('ab12', 16);
    $message = new Message(['ana@exemplo.com'], 'Reset', '<p>x</p>', 'Link: https://x.test/admin/redefinir-senha/' . $token);

    (new LogTransport(false))->send($message);
    $production = (string) file_get_contents($dir . '/app-' . gmdate('Y-m-d') . '.log');
    assertFalse(str_contains($production, $token), 'o token nao pode ir para o log em producao');
    assertContains('redefinir-senha/:token', $production);

    (new LogTransport(true))->send($message);
    assertContains($token, (string) file_get_contents($dir . '/app-' . gmdate('Y-m-d') . '.log'));
    Logger::reset();
});
