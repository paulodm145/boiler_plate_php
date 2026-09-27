<?php

namespace App\Repositories;

use App\Database\Database;

class PasswordResetRepository
{
    private $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?: Database::shared();
    }

    public function create(string $email, string $tokenHash, int $expiresInMinutes): void
    {
        $expiry = gmdate('Y-m-d H:i:s', time() + $expiresInMinutes * 60);
        $now = gmdate('Y-m-d H:i:s');

        $this->database->execute(
            'INSERT INTO password_resets (email, token_hash, expires_at, created_at)
             VALUES (:email, :hash, :expiry, :created)
             ON DUPLICATE KEY UPDATE
                token_hash = :hash2, expires_at = :expiry2, created_at = :created2',
            [
                'email' => $email,
                'hash' => $tokenHash,
                'expiry' => $expiry,
                'created' => $now,
                'hash2' => $tokenHash,
                'expiry2' => $expiry,
                'created2' => $now,
            ]
        );
    }

    public function findByToken(string $rawToken): ?array
    {
        return $this->database->fetch(
            'SELECT email, token_hash, expires_at FROM password_resets WHERE token_hash = :hash AND expires_at >= UTC_TIMESTAMP() LIMIT 1',
            ['hash' => hash('sha256', $rawToken)]
        );
    }

    public function deleteByToken(string $rawToken): void
    {
        $this->database->execute(
            'DELETE FROM password_resets WHERE token_hash = :hash',
            ['hash' => hash('sha256', $rawToken)]
        );
    }
}
