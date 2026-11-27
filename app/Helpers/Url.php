<?php

namespace App\Helpers;

use App\Core\Env;

/** URLs absolutas a partir do APP_URL (links de e-mail, callback OAuth). */
class Url
{
    public static function absolute(string $path): string
    {
        return rtrim((string) Env::get('APP_URL', ''), '/') . $path;
    }
}
