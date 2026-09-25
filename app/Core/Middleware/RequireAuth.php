<?php

namespace App\Core\Middleware;

use App\Core\Auth;

class RequireAuth implements MiddlewareInterface
{
    public function handle(array $routeParams): ?string
    {
        return Auth::check() ? null : '/admin/login';
    }
}
