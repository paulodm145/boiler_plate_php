<?php

namespace App\Http\Requests;

use App\Http\FormRequest;

final class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:180|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha'];
    }

    public function redirectTo(): string
    {
        return '/admin/users/create';
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
