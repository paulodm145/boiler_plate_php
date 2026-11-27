<?php

namespace App\Storage;

use RuntimeException;

/**
 * Arquivos no servidor, sob storage/uploads (fora do docroot, com .htaccess
 * negando acesso). Arquivos 0640: o cron pode rodar com outro usuario do
 * mesmo grupo que o servidor web, mas ninguem mais le.
 */
final class LocalDisk implements Disk
{
    public const NAME = 'local';
    private const FILE_MODE = 0640;
    private const FOLDER_MODE = 0755;

    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/');
    }

    public function name(): string
    {
        return self::NAME;
    }

    /** Caminho absoluto, para o que precisa de arquivo local (ex.: processar uma imagem). */
    public function absolutePath(string $path): string
    {
        StoragePath::assertSafe($path);

        return $this->root . '/' . $path;
    }

    public function put(string $path, string $localFile): void
    {
        $target = $this->absolutePath($path);
        $this->ensureFolder(dirname($target));

        if (!@copy($localFile, $target)) {
            throw new RuntimeException('Nao foi possivel gravar o arquivo no disco local.');
        }

        @chmod($target, self::FILE_MODE);
    }

    public function exists(string $path): bool
    {
        return is_file($this->absolutePath($path));
    }

    public function size(string $path): ?int
    {
        $file = $this->absolutePath($path);

        return is_file($file) ? (int) filesize($file) : null;
    }

    public function delete(string $path): void
    {
        $file = $this->absolutePath($path);

        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** A pasta de onde o arquivo saiu e apagada se ficou vazia (nunca a raiz). */
    public function move(string $from, string $to): bool
    {
        $source = $this->absolutePath($from);
        $target = $this->absolutePath($to);

        if (!is_file($source)) {
            return false;
        }

        $this->ensureFolder(dirname($target));

        if (!@rename($source, $target)) {
            return false;
        }

        if (realpath(dirname($source)) !== realpath($this->root)) {
            @rmdir(dirname($source)); // so apaga pasta vazia; com outros arquivos, falha em silencio
        }

        return true;
    }

    public function deleteDirectory(string $prefix): void
    {
        $folder = $this->absolutePath(trim($prefix, '/'));

        if (!is_dir($folder)) {
            return;
        }

        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($entries as $entry) {
            $entry->isDir() && !$entry->isLink() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
        }

        @rmdir($folder);
    }

    public function writeTo(string $path, $stream): bool
    {
        $file = $this->absolutePath($path);
        $source = is_file($file) ? @fopen($file, 'rb') : false;

        if ($source === false) {
            return false;
        }

        stream_copy_to_stream($source, $stream);
        fclose($source);

        return true;
    }

    private function ensureFolder(string $folder): void
    {
        if (!is_dir($folder) && !@mkdir($folder, self::FOLDER_MODE, true) && !is_dir($folder)) {
            throw new RuntimeException('Nao foi possivel criar a pasta no disco local.');
        }
    }
}
