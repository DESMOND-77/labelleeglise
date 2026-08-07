<?php

namespace App\Core;

/**
 * Journalisation légère dans Storage/logs/.
 * Sans dépendance externe.
 */
class Logger
{
    /** Initialise la gestion d'erreurs globale (au boot). */
    public static function init(): void
    {
        if (!is_dir(APP_LOG_PATH)) {
            @mkdir(APP_LOG_PATH, 0775, true);
        }
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    /** Écrit une ligne dans le journal du jour. */
    public static function write(string $level, string $message, array $context = []): void
    {
        $file = APP_LOG_PATH . '/app-' . date('Y-m-d') . '.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        $line .= PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /** Handler d'erreur PHP (transforme en exception, sauf @). */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    /** Handler d'exception globale. */
    public static function handleException(\Throwable $e): void
    {
        self::error('Exception: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        if ((defined('APP_DEBUG') && APP_DEBUG) || (isset($GLOBALS['appConfig']['debug']) && $GLOBALS['appConfig']['debug'])) {
            echo '<pre>' . htmlspecialchars((string) $e) . '</pre>';
        } else {
            echo 'Une erreur interne est survenue.';
        }
    }
}
