<?php

namespace App\Http;

/**
 * Apoio dos campos de formulario com erro (Bootstrap):
 *
 *     <input class="form-control<?= Form::invalid('email') ?>" name="email" value="<?= e(old('email')) ?>">
 *     <?= Form::feedback('email') ?>
 */
final class Form
{
    private function __construct()
    {
    }

    /** ' is-invalid' quando o campo tem erro; senao ''. */
    public static function invalid(string $field): string
    {
        return errors()->has($field) ? ' is-invalid' : '';
    }

    /** A primeira mensagem do campo dentro de .invalid-feedback, escapada; senao ''. */
    public static function feedback(string $field): string
    {
        $message = errors()->first($field);

        return $message === null ? '' : '<div class="invalid-feedback">' . e($message) . '</div>';
    }
}
