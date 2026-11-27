<?php

namespace App\Core;

use Throwable;

/**
 * Logger em arquivo (storage/logs/app-YYYY-MM-DD.log, data UTC), registry
 * estatico no mesmo formato de Auth. Nunca lanca: se a pasta nao for
 * gravavel, a linha vai para error_log() e a requisicao segue.
 *
 * Formato: [iso8601] ambiente.LEVEL: mensagem {json do contexto}
 */
class Logger
{
    private const LEVELS = [
        'debug' => 100,
        'info' => 200,
        'warning' => 300,
        'error' => 400,
        'critical' => 500,
    ];

    private const SENSITIVE_KEYS = ['password', 'senha', 'token', 'secret', 'csrf', 'authorization', 'cookie'];

    private static $directory;
    private static $environment = 'production';
    private static $minLevel = 'warning';
    private static $retentionDays = 14;
    private static $context = [];
    private static $contextProvider;
    private static $requestId;
    private static $retentionDone = false;

    public static function configure(
        string $directory,
        string $environment = 'production',
        string $minLevel = 'warning',
        int $retentionDays = 14
    ): void {
        self::$directory = rtrim($directory, '/');
        self::$environment = $environment;
        self::$minLevel = isset(self::LEVELS[$minLevel]) ? $minLevel : 'warning';
        self::$retentionDays = $retentionDays;
        self::$retentionDone = false;
    }

    public static function configureFromEnv(string $rootPath): void
    {
        $default = Env::get('APP_DEBUG') === 'true' ? 'debug' : 'warning';

        self::configure(
            rtrim($rootPath, '/') . '/storage/logs',
            Env::get('APP_ENV', 'production') ?: 'production',
            strtolower(Env::get('LOG_LEVEL', $default) ?: $default),
            (int) (Env::get('LOG_RETENTION_DAYS', '14') ?: 14)
        );
    }

    public static function addContext(array $context): void
    {
        self::$context = array_merge(self::$context, $context);
    }

    // Callable chamado a cada escrita; o array retornado e mesclado depois do addContext.
    public static function setContextProvider(?callable $provider): void
    {
        self::$contextProvider = $provider;
    }

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            self::$requestId = bin2hex(random_bytes(4));
        }

        return self::$requestId;
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }

    public static function isWritable(): bool
    {
        if (self::$directory === null) {
            return false;
        }

        if (!is_dir(self::$directory)) {
            @mkdir(self::$directory, 0775, true);
        }

        return is_dir(self::$directory) && is_writable(self::$directory);
    }

    // So para testes: zera config, contexto e request id.
    public static function reset(): void
    {
        self::$directory = null;
        self::$environment = 'production';
        self::$minLevel = 'warning';
        self::$retentionDays = 14;
        self::$context = [];
        self::$contextProvider = null;
        self::$requestId = null;
        self::$retentionDone = false;
    }

    private static function log(string $level, string $message, array $context): void
    {
        try {
            if (self::LEVELS[$level] < self::LEVELS[self::$minLevel]) {
                return;
            }

            $line = self::format($level, $message, $context);

            if (self::$directory === null) {
                // Ainda nao configurado (erro no boot): nao perder a linha.
                @error_log($line);

                return;
            }

            self::write($line);
        } catch (Throwable $error) {
            // Logar nunca pode derrubar a requisicao.
            @error_log('Logger falhou: ' . $error->getMessage());
        }
    }

    private static function format(string $level, string $message, array $context): string
    {
        $auto = ['request_id' => self::requestId()];
        $auto = array_merge($auto, self::$context);

        if (self::$contextProvider !== null) {
            try {
                $provided = call_user_func(self::$contextProvider);
                if (is_array($provided)) {
                    $auto = array_merge($auto, $provided);
                }
            } catch (Throwable $error) {
                // Provider quebrado nao pode impedir o registro.
            }
        }

        $trace = null;
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $exception = $context['exception'];
            $context['exception'] = get_class($exception);
            $context['exception_message'] = self::oneLine($exception->getMessage());
            $context['file'] = self::relativePath($exception->getFile());
            $context['line'] = $exception->getLine();
            $trace = self::buildTrace($exception);
        }

        $all = self::redact(array_merge($auto, $context));
        $json = json_encode($all, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $line = '[' . gmdate('c') . '] ' . self::$environment . '.' . strtoupper($level)
            . ': ' . self::oneLine($message) . ' ' . ($json === false ? '{}' : $json);

        if ($trace !== null) {
            $line .= "\n[stacktrace]\n" . $trace;
        }

        return $line;
    }

    // Escapa CR/LF para que uma entrada seja sempre uma unica linha (sem forjar linhas).
    private static function oneLine(string $text): string
    {
        return str_replace(["\r", "\n"], ['\\r', '\\n'], $text);
    }

    // Igual ao getTraceAsString(), mas sem os argumentos das funcoes (podem ter segredos).
    private static function buildTrace(Throwable $exception): string
    {
        $lines = [];
        $frames = $exception->getTrace();

        foreach ($frames as $i => $frame) {
            $where = isset($frame['file'])
                ? self::relativePath($frame['file']) . '(' . ($frame['line'] ?? 0) . ')'
                : '[internal function]';
            $call = (isset($frame['class']) ? $frame['class'] . ($frame['type'] ?? '->') : '')
                . ($frame['function'] ?? '') . '()';
            $lines[] = '#' . $i . ' ' . $where . ': ' . $call;
        }

        $lines[] = '#' . count($frames) . ' {main}';

        return implode("\n", $lines);
    }

    private static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitive($key)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (strpos($key, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function relativePath(string $path): string
    {
        $root = dirname(__DIR__, 2) . '/';

        return strpos($path, $root) === 0 ? substr($path, strlen($root)) : $path;
    }

    private static function write(string $line): void
    {
        if (!self::isWritable()) {
            @error_log($line);

            return;
        }

        $file = self::$directory . '/app-' . gmdate('Y-m-d') . '.log';

        if (!self::$retentionDone && !file_exists($file)) {
            self::$retentionDone = true;
            self::purgeOld();
        }

        if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) === false) {
            @error_log($line);
        }
    }

    private static function purgeOld(): void
    {
        $limit = gmdate('Y-m-d', time() - self::$retentionDays * 86400);

        foreach ((array) glob(self::$directory . '/app-*.log') as $path) {
            if (preg_match('/app-(\d{4}-\d{2}-\d{2})\.log$/', $path, $m) && $m[1] < $limit) {
                @unlink($path);
            }
        }
    }
}
