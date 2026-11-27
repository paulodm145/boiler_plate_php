<?php

namespace App\Services\Auth;

use App\Core\Env;
use App\Mail\Mailer;
use App\Mail\PasswordResetMail;
use App\Repositories\PasswordResetRepository;
use App\Repositories\UserRepository;
use App\Services\ServiceResult;

/**
 * Recuperacao de senha por link com token (60 minutos). So o hash SHA-256 do
 * token fica no banco. O limite de tentativas por IP e protecao HTTP e fica
 * no controller.
 */
final class PasswordResetService
{
    private const TOKEN_MINUTES = 60;

    private UserRepository $users;
    private PasswordResetRepository $resets;

    public function __construct(?UserRepository $users = null, ?PasswordResetRepository $resets = null)
    {
        $this->users = $users ?? new UserRepository();
        $this->resets = $resets ?? new PasswordResetRepository();
    }

    /**
     * Envia o link se o e-mail existir; em silencio se nao existir (a resposta ao
     * usuario e a mesma). Ainda sobra um canal por tempo: so o e-mail existente paga
     * o custo de gravar o token e falar com o SMTP. Fechar isso pede fila/envio
     * depois da resposta, que o boilerplate nao tem; o limite por IP (3 tentativas
     * em 15 min) restringe quantas medicoes um atacante faz.
     */
    public function requestLink(string $email): void
    {
        if ($this->users->findByEmail($email) === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->resets->create($email, hash('sha256', $token), self::TOKEN_MINUTES);

        $link = rtrim((string) Env::get('APP_URL', ''), '/') . '/admin/redefinir-senha/' . $token;

        Mailer::send((new PasswordResetMail($link, self::TOKEN_MINUTES))->to($email));
    }

    public function isValidToken(string $token): bool
    {
        return $this->resets->findByToken($token) !== null;
    }

    /** Troca a senha e apaga o token. ok($user) para o controller autenticar; invalid_token se nao der. */
    public function reset(string $token, string $password): ServiceResult
    {
        $reset = $this->resets->findByToken($token);

        if ($reset === null) {
            return ServiceResult::fail('invalid_token', 'Este link é inválido ou já expirou.');
        }

        $user = $this->users->findByEmail($reset['email']);

        if ($user === null) {
            // Token orfao: o usuario foi apagado (ou trocou de e-mail) depois do pedido;
            // nada apaga password_resets em cascata.
            $this->resets->deleteByToken($token);

            return ServiceResult::fail('invalid_token', 'Este link é inválido ou já expirou.');
        }

        $this->users->updatePassword((int) $user['id'], $password);
        $this->resets->deleteByToken($token);

        return ServiceResult::ok($user);
    }
}
