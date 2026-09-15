<?php

declare(strict_types=1);

namespace Patro\Config;

/**
 * Gestion de la configuration et des variables d'environnement
 */
class Environment
{
    private static bool $loaded = false;

    /**
     * Charge les variables d'environnement depuis un fichier .env
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) < 2) {
                continue;
            }

            [$key, $value] = array_map('trim', $parts);
            if ($key === '' || getenv($key) !== false || isset($_ENV[$key], $_SERVER[$key])) {
                continue;
            }

            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        self::$loaded = true;
    }

    /**
     * Récupère une variable d'environnement
     */
    public static function get(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return ($value === false || $value === null) ? $default : $value;
    }

    /**
     * Récupère une variable d'environnement comme booléen
     */
    public static function bool(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Récupère une variable d'environnement comme entier
     */
    public static function int(string $key, int $default): int
    {
        $value = filter_var(self::get($key, $default), FILTER_VALIDATE_INT);
        return $value === false ? $default : (int) $value;
    }
}
