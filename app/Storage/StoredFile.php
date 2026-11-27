<?php

namespace App\Storage;

/**
 * Um arquivo guardado, pronto para o download: disco e caminho, o tipo gravado
 * na linha, o nome que a pessoa ve e se deve abrir no navegador. Os Services
 * devolvem isto; so App\Http\FileResponse manda ao navegador.
 */
final readonly class StoredFile
{
    public function __construct(
        public Disk $disk,
        public string $path,
        public string $mimeType,
        public string $downloadName,
        public bool $inline = false,
        public ?int $size = null
    ) {
    }

    public function exists(): bool
    {
        return $this->disk->exists($this->path);
    }
}
