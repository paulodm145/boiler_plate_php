<?php

namespace App\Http\Requests;

use App\Http\FormRequest;

/** Edicao: o proprio e-mail nao conta como repetido, e senha vazia mantem a atual. */
final class UpdateUserRequest extends FormRequest
{
    public function __construct(private readonly string $userUuid)
    {
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:180|unique:users,email,' . $this->userUuid . ',uuid',
            'password' => 'nullable|string|min:8|confirmed',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha'];
    }

    public function redirectTo(): string
    {
        return '/admin/users/' . $this->userUuid . '/edit';
    }

    protected function prepare(array $input): array
    {
        foreach (['name', 'email'] as $field) {
            if (is_string($input[$field] ?? null)) {
                $input[$field] = trim($input[$field]);
            }
        }

        return $input;
    }
}
