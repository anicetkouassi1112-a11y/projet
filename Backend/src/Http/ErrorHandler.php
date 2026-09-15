<?php

declare(strict_types=1);

namespace Patro\Http;

use Patro\Config\Environment;
use Patro\Security\SecurityLogger;
use Throwable;

/**
 * Centralise la journalisation et la réponse aux exceptions non interceptées.
 */
final class ErrorHandler
{
    public function register(string $logDirectory, string $errorPage): void
    {
        if (!is_dir($logDirectory)) {
            @mkdir($logDirectory, 0770, true);
        }

        ini_set('log_errors', '1');
        ini_set('error_log', $logDirectory . DIRECTORY_SEPARATOR . 'php-errors.log');
        error_reporting(E_ALL);

        SecurityLogger::init(
            $logDirectory,
            Environment::int('LOG_MAX_BYTES', 5242880)
        );

        if (Environment::bool('APP_DEBUG', false) || PHP_SAPI === 'cli') {
            return;
        }

        set_exception_handler(function (Throwable $exception) use ($errorPage): void {
            error_log(sprintf(
                'Uncaught exception [%s] %s in %s:%d',
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));

            if (!headers_sent()) {
                http_response_code(500);
            }

            if (is_file($errorPage)) {
                require $errorPage;
            } else {
                echo 'Erreur serveur.';
            }
            exit;
        });
    }
}
