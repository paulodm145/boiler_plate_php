<?php

namespace App\Helpers;

/** Entradas do $_FILES no formato de um arquivo por item, venha o campo com varios (name="x[]") ou com um so. */
class UploadedFiles
{
    private const KEYS = ['name', 'type', 'tmp_name', 'error', 'size'];

    public static function fromField(?array $field): array
    {
        if ($field === null || !isset($field['name'])) {
            return [];
        }

        if (!is_array($field['name'])) {
            return [$field];
        }

        $files = [];

        foreach (array_keys($field['name']) as $index) {
            $file = [];

            foreach (self::KEYS as $key) {
                $file[$key] = $field[$key][$index] ?? null;
            }

            $files[] = $file;
        }

        return $files;
    }
}
