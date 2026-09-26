<?php

namespace App\Database;

use App\Core\Env;
use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private $pdo;

    private static $shared;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: $this->createConnection();
    }

    /**
     * Uma unica conexao PDO reaproveitada por request, pra quem so precisa
     * "a conexao real configurada no .env" (Repositories/Migrator sem
     * Database injetado). Sem isso, cada `new Database()` abre seu proprio
     * handshake MySQL — uma unica request admin podia abrir 2-3 conexoes
     * (Migrator + UserRepository + o InstallState::pendingCount() do
     * shell-top.php). Nunca use isto em teste — testes sempre injetam sua
     * propria Database isolada, ligada ao banco de teste.
     */
    public static function shared(): self
    {
        if (self::$shared === null) {
            self::$shared = new self();
        }

        return self::$shared;
    }

    public function connection(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return $result === false ? null : $result;
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    private function createConnection(): PDO
    {
        $connection = Env::get('DB_CONNECTION', 'mysql');

        if ($connection !== 'mysql') {
            throw new PDOException('Unsupported database connection: ' . $connection);
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $database = Env::get('DB_DATABASE', 'app');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');
        $username = Env::get('DB_USERNAME', 'root');
        $password = Env::get('DB_PASSWORD', '');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new PDOException('Database connection failed: ' . $exception->getMessage(), (int) $exception->getCode());
        }
    }
}
