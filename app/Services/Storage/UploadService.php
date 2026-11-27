<?php

namespace App\Services\Storage;

use App\Core\Logger;
use App\Helpers\FileName;
use App\Helpers\Uuid;
use App\Services\ServiceResult;
use App\Storage\Disk;
use App\Storage\LocalDisk;
use App\Storage\MimeTypes;
use App\Storage\StoragePath;
use RuntimeException;

/**
 * A porta de entrada de todo arquivo enviado por formulario. Nunca confia no
 * Content-Type nem na extensao do navegador: o tipo real vem do conteudo
 * (finfo) e precisa estar na lista de quem chama (MimeTypes::IMAGES...); o
 * tamanho e o do arquivo, nao o informado; o arquivo e gravado como
 * "<pasta>/<AAAA>/<MM>/<uuid>.<ext>" e o nome original, limpo, so serve para
 * mostrar. Nao toca em banco nem em sessao: quem chama grava a linha.
 *
 *     $result = $uploads->store($_FILES['avatar'] ?? null, 'avatars', 2 * 1024 * 1024, MimeTypes::IMAGES);
 */
final class UploadService
{
    private Disk $disk;
    private string $stagingDirectory;
    /** @var callable(string, string): bool */
    private $mover;

    /** $mover existe so para os testes (arquivos de teste nao vem de upload HTTP). */
    public function __construct(?Disk $disk = null, ?string $stagingDirectory = null, ?callable $mover = null)
    {
        $root = dirname(__DIR__, 3) . '/storage/uploads';
        $this->disk = $disk ?? new LocalDisk($root);
        $this->stagingDirectory = rtrim($stagingDirectory ?? $root . '/.staging', '/');
        $this->mover = $mover ?? 'move_uploaded_file';
    }

    /**
     * @param array|null $file entrada de $_FILES (null/[] se o campo nao veio)
     * @param array<string, string> $allowedMimeToExt MIME real => extensao gravada
     *
     * ok(['path', 'disk', 'mime', 'size', 'original_name']) |
     * fail(missing_file | upload_failed | file_too_large | invalid_type | could_not_save)
     */
    public function store(?array $file, string $folder, int $maxBytes, array $allowedMimeToExt): ServiceResult
    {
        StoragePath::assertSafe(trim($folder, '/'));

        $error = $file['error'] ?? null;

        if ($file === null || $file === [] || $error === UPLOAD_ERR_NO_FILE || !isset($file['tmp_name'])) {
            return ServiceResult::fail('missing_file');
        }

        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return ServiceResult::fail('file_too_large');
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ServiceResult::fail('upload_failed');
        }

        $staged = $this->stage((string) $file['tmp_name']);

        if ($staged === null) {
            return ServiceResult::fail('upload_failed');
        }

        try {
            if ((int) filesize($staged) > $maxBytes) {
                return ServiceResult::fail('file_too_large');
            }

            $mime = MimeTypes::detect($staged);

            if (!isset($allowedMimeToExt[$mime])) {
                return ServiceResult::fail('invalid_type');
            }

            return $this->put($staged, trim($folder, '/'), $mime, $allowedMimeToExt[$mime], (string) ($file['name'] ?? ''));
        } finally {
            @unlink($staged);
        }
    }

    /** Apaga da pasta de passagem o que ficou de envio interrompido e e mais velho que $beforeTimestamp. */
    public function removeStaleStaging(int $beforeTimestamp): int
    {
        $removed = 0;

        foreach (glob($this->stagingDirectory . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $beforeTimestamp && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /** O temporario do PHP sai por move_uploaded_file (que confere que veio de um upload) para a passagem. */
    private function stage(string $uploadedFile): ?string
    {
        if (!is_dir($this->stagingDirectory) && !@mkdir($this->stagingDirectory, 0775, true) && !is_dir($this->stagingDirectory)) {
            return null;
        }

        $staged = $this->stagingDirectory . '/' . Uuid::v4();

        return @call_user_func($this->mover, $uploadedFile, $staged) ? $staged : null;
    }

    private function put(string $staged, string $folder, string $mime, string $extension, string $originalName): ServiceResult
    {
        $path = $folder . '/' . gmdate('Y/m') . '/' . Uuid::v4() . '.' . $extension;

        try {
            $this->disk->put($path, $staged);
        } catch (RuntimeException $failure) {
            Logger::error('Arquivo enviado nao foi gravado no disco.', ['disk' => $this->disk->name(), 'error' => $failure->getMessage()]);

            return ServiceResult::fail('could_not_save');
        }

        return ServiceResult::ok([
            'path' => $path,
            'disk' => $this->disk->name(),
            'mime' => $mime,
            'size' => (int) filesize($staged),
            'original_name' => FileName::clean($originalName),
        ]);
    }
}
