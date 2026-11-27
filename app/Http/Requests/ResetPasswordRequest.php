<?php

namespace App\Http\Requests;

use App\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
{
    public function __construct(private readonly string $token = '')
    {
    }

    public function rules(): array
    {
        return ['password' => 'required|string|min:8|confirmed'];
    }

    public function attributes(): array
    {
        return ['password' => 'nova senha'];
    }

    public function redirectTo(): string
    {
        return '/admin/redefinir-senha/' . $this->token;
    }
}
