<?php

namespace App\Core;

use App\Helpers\LogPath;
use Throwable;

/**
 * Registra os handlers globais (excecao nao capturada, erro PHP, fatal) e
 * liga tudo ao Logger. Chamado cedo em public/index.php e nos scripts CLI.
 */
class ErrorHandler
{
    private const FATAL_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    public static function register(string $rootPath): void
    {
        Logger::configureFromEnv($rootPath);

        if (PHP_SAPI === 'cli') {
            $script = $_SERVER['argv'][0] ?? 'cli';
            Logger::addContext(['cli' => basename((string) $script)]);
        } else {
            Logger::addContext([
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                // Sem query string e sem o token de links de convite/senha (LogPath).
                'uri' => LogPath::mask((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
        }

        // A sessao so inicia depois do register (Auth::start() em index.php),
        // entao o usuario e resolvido a cada escrita, nunca aqui.
        Logger::setContextProvider(function (): array {
            if (session_status() !== PHP_SESSION_ACTIVE || !Auth::check()) {
                return [];
            }

            $user = Auth::user();

            return ['user_uuid' => $user['uuid'] ?? null];
        });

        // Ate o .env dizer o contrario (showErrorsOnScreen), nada de erro na tela da web: o php.ini
        // da hospedagem pode vir com display_errors ligado e vazar caminhos de arquivo.
        if (PHP_SAPI !== 'cli') {
            self::showErrorsOnScreen(false);
        }

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /** So com APP_DEBUG=true (chamado pelo public/index.php depois de ler o .env). */
    public static function showErrorsOnScreen(bool $show): void
    {
        ini_set('display_errors', $show ? '1' : '0');
    }

    public static function handleError(int $level, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        Logger::warning($message, ['level' => $level, 'file' => $file, 'line' => $line]);

        return false;
    }

    public static function handleException(Throwable $exception): void
    {
        Logger::error($exception->getMessage(), ['exception' => $exception]);

        if (Env::get('APP_DEBUG', 'false') === 'true' && PHP_SAPI !== 'cli') {
            if (!headers_sent()) {
                http_response_code(500);
            }

            self::printDetail(get_class($exception) . ': ' . $exception->getMessage()
                . "\nem " . $exception->getFile() . ':' . $exception->getLine());

            return;
        }

        ErrorPage::serverError();
    }

    // set_exception_handler nao pega Fatal Error "de verdade" (out of memory,
    // erro de sintaxe num arquivo carregado depois); sem isto vira tela em
    // branco com display_errors desligado.
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], self::FATAL_TYPES, true)) {
            return;
        }

        Logger::critical($error['message'], [
            'level' => $error['type'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        if (PHP_SAPI === 'cli') {
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (Env::get('APP_DEBUG', 'false') === 'true') {
            self::printDetail($error['message'] . "\nem " . $error['file'] . ':' . $error['line']);

            return;
        }

        ErrorPage::serverError();
    }

    private static function printDetail(string $text): void
    {
        echo '<pre style="white-space:pre-wrap;padding:2rem;font-family:monospace;font-size:.85rem">';
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    }
}
