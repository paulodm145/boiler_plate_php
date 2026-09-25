<?php

namespace App\Core\Middleware;

interface MiddlewareInterface
{
    /**
     * null = segue adiante na cadeia (e depois pro controller, se for a
     * ultima). Qualquer outra string = tratada como Location e interrompe
     * a cadeia, exceto o valor especial Router::HALT (a resposta ja foi
     * enviada, ex: ErrorPage::forbidden() — nao despachar nem redirecionar).
     */
    public function handle(array $routeParams): ?string;
}
