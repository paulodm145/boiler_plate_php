<?php

namespace App\Http\Requests;

use App\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return ['email' => 'required|email|max:180'];
    }

    public function attributes(): array
    {
        return ['email' => 'e-mail'];
    }

    public function redirectTo(): string
    {
        return '/admin/esqueci-senha';
    }

    protected function prepare(array $input): array
    {
        if (is_string($input['email'] ?? null)) {
            $input['email'] = trim($input['email']);
        }

        return $input;
    }
}
