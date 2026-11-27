<?php

namespace App\Storage;

/**
 * Onde um arquivo guardado mora. Caminhos relativos, gravados na linha junto
 * com o nome do disco. Falha inesperada (disco cheio, sem permissao) e excecao.
 * Hoje so ha LocalDisk; um disco remoto implementa a mesma interface.
 */
interface Disk
{
    /** Nome gravado na linha ("local"). */
    public function name(): string;

    /** Copia o arquivo local $localFile para $path; o arquivo local fica. */
    public function put(string $path, string $localFile): void;

    public function exists(string $path): bool;

    /** Tamanho em bytes, ou null se nao existe. */
    public function size(string $path): ?int;

    /** Apagar o que nao existe nao e erro. */
    public function delete(string $path): void;

    /** false se a origem nao existe ou nao deu para mover (a origem fica). */
    public function move(string $from, string $to): bool;

    /** Apaga tudo sob a pasta $prefix (inexistente e ignorada). */
    public function deleteDirectory(string $prefix): void;

    /** Copia o conteudo para $stream (ex.: php://output). false se o arquivo nao existe. */
    public function writeTo(string $path, $stream): bool;

    /** Caminho absoluto no servidor (so faz sentido em disco local). */
    public function absolutePath(string $path): string;
}
