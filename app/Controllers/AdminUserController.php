<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\ErrorPage;
use App\Core\InstallState;
use App\Core\View;
use App\Http\Flash;
use App\Http\ListQuery;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Repositories\UserRepository;
use App\Services\User\UserService;
use App\Validation\ValidationException;

/**
 * Exemplo de referencia das camadas: le a requisicao pelo FormRequest, chama
 * o UserService e escolhe a resposta. Sem SQL, sem regra de negocio e sem
 * metodo privado de validacao. Entrada invalida volta ao formulario sozinha
 * (ValidationException tratada no Router).
 */
class AdminUserController
{
    private UserService $users;

    public function __construct()
    {
        $this->users = new UserService();
    }

    public function index(): void
    {
        $list = ListQuery::fromRequest(UserRepository::SORTABLE, 'name');
        $data = ['page' => $this->users->list($list), 'list' => $list];

        if ($list->isPartial()) {
            View::render('admin/partials/users-table', $data);

            return;
        }

        View::render('admin/users', $data + $this->shell('Usuários | Admin'));
    }

    public function create(): void
    {
        View::render('admin/user-form', [
            'adminUser' => ['uuid' => null, 'name' => '', 'email' => ''],
            'isNew' => true,
        ] + $this->shell('Novo usuário | Admin'));
    }

    public function store(): void
    {
        $data = StoreUserRequest::validate();
        $result = $this->users->create($data);

        if (!$result->isOk()) {
            throw ValidationException::withMessages(['email' => $result->message()], $data, '/admin/users/create');
        }

        Flash::success('Usuário criado.');
        $this->redirect('/admin/users');
    }

    public function edit(string $userUuid): void
    {
        $user = $this->users->find($userUuid);

        if ($user === null) {
            ErrorPage::notFound();

            return;
        }

        View::render('admin/user-form', ['adminUser' => $user, 'isNew' => false] + $this->shell('Editar usuário | Admin'));
    }

    public function update(string $userUuid): void
    {
        $request = new UpdateUserRequest($userUuid);
        $result = $this->users->update($userUuid, $request->validated());

        if ($result->code() === 'not_found') {
            ErrorPage::notFound();

            return;
        }

        if ($result->code() === 'email_taken') {
            throw ValidationException::withMessages(['email' => $result->message()], $_POST, $request->redirectTo());
        }

        Flash::success('Usuário atualizado.');
        $this->redirect('/admin/users');
    }

    public function delete(string $userUuid): void
    {
        $result = $this->users->delete($userUuid, (array) Auth::user());

        if ($result->code() === 'not_found') {
            ErrorPage::notFound();

            return;
        }

        if (!$result->isOk()) {
            Flash::error((string) $result->message());
            $this->redirect('/admin/users/' . $userUuid . '/edit');

            return;
        }

        Flash::success('Usuário excluído.');
        $this->redirect('/admin/users');
    }

    /** Dados do layout do admin (titulo, usuario logado, aviso de migrations). */
    private function shell(string $title): array
    {
        return [
            'title' => $title,
            'user' => Auth::user(),
            'pendingCount' => (new InstallState(dirname(__DIR__, 2)))->pendingCount(),
        ];
    }

    private function redirect(string $path): void
    {
        http_response_code(303);
        header('Location: ' . $path);
    }
}
