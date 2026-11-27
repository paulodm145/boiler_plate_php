<?php

namespace App\Http\Requests;

use App\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'site_name' => 'required|string|max:120',
            'site_description' => 'nullable|string|max:255',
            'smtp_host' => 'nullable|string|max:190',
            'smtp_port' => 'nullable|integer|between:1,65535',
            'smtp_encryption' => 'required|in:tls,ssl,none',
            'smtp_username' => 'nullable|string|max:190',
            'smtp_password' => 'nullable|string|max:190',
            'smtp_from_address' => 'nullable|email|max:190',
            'smtp_from_name' => 'nullable|string|max:120',
        ];
    }

    public function attributes(): array
    {
        return [
            'site_name' => 'nome da aplicação',
            'site_description' => 'descrição',
            'smtp_host' => 'servidor SMTP',
            'smtp_port' => 'porta',
            'smtp_encryption' => 'criptografia',
            'smtp_username' => 'usuário',
            'smtp_password' => 'senha',
            'smtp_from_address' => 'e-mail do remetente',
            'smtp_from_name' => 'nome do remetente',
        ];
    }

    public function redirectTo(): string
    {
        return '/admin/settings';
    }
}
