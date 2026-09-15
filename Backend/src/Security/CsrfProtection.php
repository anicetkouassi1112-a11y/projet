<?php

declare(strict_types=1);

namespace Patro\Security;

/**
 * Protection CSRF pour les formulaires
 */
class CsrfProtection
{
    /**
     * Génère ou retourne le token CSRF pour la session actuelle
     */
    public static function generateToken(): string
    {
        self::ensureSession();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Vérifie la validité d'un token CSRF
     */
    public static function verifyToken(?string $token): bool
    {
        self::ensureSession();

        if (!$token || empty($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Vérifie le token CSRF et arrête l'exécution si invalide
     */
    public static function requireToken(?string $token = null): void
    {
        $token = $token ?? ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null);
        if (!self::verifyToken($token)) {
            http_response_code(403);
            SecurityLogger::log('CSRF token validation failed', ['ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            exit('Erreur de validation CSRF. Veuillez recharger la page et reessayer.');
        }
    }

    /**
     * Génère le champ input HTML pour le token CSRF
     */
    public static function htmlField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::generateToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
