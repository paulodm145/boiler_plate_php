<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Migrator;
use App\Core\View;
use App\Database\Database;

class AdminUpdateController
{
    private $rootPath;

    public function __construct()
    {
        $this->rootPath = dirname(__DIR__, 2);
    }

    public function show(): void
    {
        View::render('admin/update', [
            'title' => 'Atualizar banco de dados | Admin',
            'user' => Auth::user(),
            'pending' => $this->migrator()->pending(),
            // O banner de "ha migrations pendentes, va atualizar" nao faz
            // sentido na propria pagina de atualizacao.
            'pendingCount' => 0,
        ]);
    }

    public function run(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Unico guardiao desta rota (routes.php nao registra RequireAuth
        // aqui de proposito) pra poder devolver 401 JSON em vez de um
        // redirect HTML que quebraria o fetch() do polling.
        if (!Auth::check()) {
            http_response_code(401);
            echo json_encode(['error' => 'sessao_expirada']);

            return;
        }

        set_time_limit(0);

        echo json_encode($this->migrator()->runNextPending());
    }

    private function migrator(): Migrator
    {
        return new Migrator(Database::shared(), $this->rootPath . '/database/migrations');
    }
}
