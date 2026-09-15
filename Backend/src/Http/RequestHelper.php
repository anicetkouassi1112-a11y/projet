<?php

declare(strict_types=1);

namespace Patro\Http;

use Patro\Domain\Inscription\SessionType;

/**
 * Helper pour la gestion des requêtes HTTP (GET, POST, sessions)
 */
class RequestHelper
{
    /**
     * Récupère et nettoie un paramètre POST
     */
    public static function input(string $name, int $maxLength = 255): string
    {
        $value = $_POST[$name] ?? '';
        return self::cleanText((string) $value, $maxLength);
    }

    /**
     * Récupère et valide un paramètre entier (GET ou POST)
     */
    public static function intParam(string $name, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $value = filter_var($_GET[$name] ?? $_POST[$name] ?? $default, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $default;
        }
        return max($min, min($max, (int) $value));
    }

    /**
     * Récupère et nettoie un paramètre texte (GET)
     */
    public static function textParam(string $name, int $maxLength = 120): string
    {
        return self::cleanText((string) ($_GET[$name] ?? ''), $maxLength);
    }

    /**
     * Récupère l'année active depuis la requête
     */
    public static function activeYearFromRequest(): int
    {
        $defaultYear = (int) ($_SESSION['annee_active'] ?? date('Y'));
        $year = self::intParam('annee', $defaultYear, 2000, 2100);
        
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }

        $_SESSION['annee_active'] = $year;
        return $year;
    }

    /**
     * Récupère le type de session actif depuis la requête
     */
    public static function activeSessionTypeFromRequest(string $currentSessionType): string
    {
        $requestedType = self::textParam('type_session', 50);
        $sessionType = (string) ($_SESSION['type_session_active'] ?? $currentSessionType);
        
        $typeSession = $requestedType !== '' 
            ? self::normalizeSessionType($requestedType, $currentSessionType)
            : self::normalizeSessionType($sessionType, $currentSessionType);

        $_SESSION['type_session_active'] = $typeSession;
        return $typeSession;
    }

    /**
     * Récupère l'année pour affichage depuis la requête
     */
    public static function displayYearFromRequest(?int $defaultYear = null): int
    {
        $year = self::intParam('annee', $defaultYear ?? (int) ($_SESSION['annee_active'] ?? date('Y')), 2000, 2100);
        if ($year < 2000 || $year > 2100) {
            return (int) date('Y');
        }

        return $year;
    }

    /**
     * Récupère le type de session pour affichage depuis la requête
     */
    public static function displaySessionTypeFromRequest(?string $defaultType = null, string $currentSessionType = 'scolaire'): string
    {
        $fallback = self::normalizeSessionType($defaultType ?? (string) ($_SESSION['type_session_active'] ?? $currentSessionType), $currentSessionType);
        $requestedType = self::textParam('type_session', 50);

        return $requestedType !== '' 
            ? self::normalizeSessionType($requestedType, $fallback)
            : $fallback;
    }

    /**
     * Nettoie un texte
     */
    private static function cleanText(string $value, int $maxLength = 255): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    /**
     * Normalise le type de session
     */
    private static function normalizeSessionType(?string $type, string $default = 'scolaire'): string
    {
        return SessionType::normalize($type, SessionType::normalize($default))->value;
    }
}
