<?php

declare(strict_types=1);

namespace Patro\Security;

/**
 * Logger pour les événements de sécurité
 */
class SecurityLogger
{
    private static string $logDir;
    private static int $maxBytes = 5242880; // 5MB

    /**
     * Initialise le logger
     */
    public static function init(string $logDir, int $maxBytes = 5242880): void
    {
        self::$logDir = $logDir;
        self::$maxBytes = $maxBytes;

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0770, true);
        }
    }

    /**
     * Enregistre un événement de sécurité
     */
    public static function log(string $message, array $context = []): void
    {
        if (!isset(self::$logDir)) {
            self::$logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        }

        $path = self::$logDir . DIRECTORY_SEPARATOR . 'security.log';

        if (is_file($path) && filesize($path) > self::$maxBytes) {
            @rename($path, self::$logDir . DIRECTORY_SEPARATOR . 'security-' . date('Ymd-His') . '.log');
        }

        $entry = [
            'timestamp' => date(DATE_ATOM),
            'level' => 'warning',
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'context' => $context,
        ];

        @file_put_contents(
            $path,
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
