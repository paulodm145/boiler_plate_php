<?php

namespace App\Core;

use App\Database\Database;
use DateTime;
use DateTimeZone;

class RateLimiter
{
    private static $connection;

    public static function setConnection(Database $database): void
    {
        self::$connection = $database;
    }

    public static function connection(): Database
    {
        if (self::$connection === null) {
            self::$connection = Database::shared();
        }

        return self::$connection;
    }

    public static function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $row = self::connection()->fetch(
            'SELECT attempts FROM rate_limits WHERE rate_key = :key AND expires_at >= UTC_TIMESTAMP() LIMIT 1',
            ['key' => $key]
        );

        return $row !== null && (int) $row['attempts'] >= $maxAttempts;
    }

    public static function hit(string $key, int $decayMinutes): void
    {
        $expiry = gmdate('Y-m-d H:i:s', time() + $decayMinutes * 60);

        self::connection()->execute(
            'INSERT INTO rate_limits (rate_key, attempts, expires_at)
             VALUES (:key, 1, :expiry)
             ON DUPLICATE KEY UPDATE
                attempts = IF(expires_at < UTC_TIMESTAMP(), 1, attempts + 1),
                expires_at = IF(expires_at < UTC_TIMESTAMP(), :expiry2, expires_at)',
            ['key' => $key, 'expiry' => $expiry, 'expiry2' => $expiry]
        );

        if (mt_rand(1, 50) === 1) {
            self::connection()->execute('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP()');
        }
    }

    public static function clear(string $key): void
    {
        self::connection()->execute('DELETE FROM rate_limits WHERE rate_key = :key', ['key' => $key]);
    }

    public static function availableInSeconds(string $key): int
    {
        $row = self::connection()->fetch(
            'SELECT expires_at FROM rate_limits WHERE rate_key = :key LIMIT 1',
            ['key' => $key]
        );

        if ($row === null) {
            return 0;
        }

        $expiresAt = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));

        return max(0, $expiresAt->getTimestamp() - $now->getTimestamp());
    }
}
