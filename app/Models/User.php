<?php

namespace App\Models;

use App\Core\Model;

/**
 * Exemplo de referencia do Active Record sobre a tabela "users" ja
 * existente (database/migrations/001_create_users_table.sql +
 * 003_add_uuid_to_users.sql). Nao e usado por nenhum Controller —
 * App\Repositories\UserRepository continua sendo o que
 * App\Controllers\AdminAuthController e App\Controllers\AdminUserController
 * usam. Se o schema de um projeto ganhar colunas booleanas, declare
 * $casts aqui do mesmo jeito.
 */
class User extends Model
{
    protected $table = 'users';
    protected $fillable = ['name', 'email', 'password'];
    protected $uuidColumn = 'uuid';
}
