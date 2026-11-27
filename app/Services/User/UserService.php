<?php

namespace App\Services\User;

use App\Database\Page;
use App\Http\ListQuery;
use App\Repositories\UserRepository;
use App\Services\ServiceResult;

/**
 * Regras de usuario do admin. Recebe dados ja validados no formato
 * (StoreUserRequest/UpdateUserRequest) e confere o que depende do banco ou
 * de quem pede: e-mail unico (de novo aqui, por corrida e por chamadas fora
 * do formulario) e ninguem exclui a propria conta.
 */
final class UserService
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    public function list(ListQuery $list): Page
    {
        return $this->users->paginate($list);
    }

    /** Usuario pelo uuid publico, sem id interno nem hash. */
    public function find(string $uuid): ?array
    {
        $user = $this->users->findByUuid($uuid);

        return $user === null ? null : $this->publicFields($user);
    }

    /** @param array{name: string, email: string, password: string} $data */
    public function create(array $data): ServiceResult
    {
        if ($this->users->findByEmail($data['email']) !== null) {
            return ServiceResult::fail('email_taken', 'Este e-mail já está em uso.');
        }

        $this->users->create($data);

        return ServiceResult::ok($this->publicFields((array) $this->users->findByEmail($data['email'])));
    }

    /** Senha vazia ou ausente mantem a atual. */
    public function update(string $uuid, array $data): ServiceResult
    {
        $user = $this->users->findByUuid($uuid);

        if ($user === null) {
            return ServiceResult::fail('not_found');
        }

        $owner = $this->users->findByEmail($data['email']);

        if ($owner !== null && (int) $owner['id'] !== (int) $user['id']) {
            return ServiceResult::fail('email_taken', 'Este e-mail já está em uso.');
        }

        $this->users->update((int) $user['id'], $data);

        return ServiceResult::ok($this->find($uuid));
    }

    /** @param array $actor usuario logado (Auth::user()) */
    public function delete(string $uuid, array $actor): ServiceResult
    {
        $user = $this->users->findByUuid($uuid);

        if ($user === null) {
            return ServiceResult::fail('not_found');
        }

        if (($actor['uuid'] ?? null) === $uuid) {
            return ServiceResult::fail('cannot_delete_self', 'Você não pode excluir o próprio usuário.');
        }

        $this->users->delete((int) $user['id']);

        return ServiceResult::ok();
    }

    private function publicFields(array $user): array
    {
        return array_intersect_key($user, array_flip(['uuid', 'name', 'email', 'created_at']));
    }
}
