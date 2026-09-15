<?php

declare(strict_types=1);

namespace Patro\Auth;

use Patro\Config\Environment;
use Patro\Domain\Admin\Repository\AdminRepository;
use Patro\Application\Auth\AdminAuthenticationService;
use Patro\Application\Auth\AuthorizationService;
use PDOException;

/**
 * Gestion de l'authentification administrateur
 */
class AdminAuth
{
    private const VALID_ROLES = ['directeur', 'suppleant_1', 'suppleant_2'];
    /**
     * Vérifie que l'utilisateur admin est connecté
     */
    public static function requireAuth(string $loginUrl = 'Auth/login.php'): void
    {
        if (empty($_SESSION['adpro'])) {
            self::redirectTo($loginUrl);
        }

        $_SESSION['admin_last_activity'] = time();
    }

    /**
     * Retourne les informations de l'admin connecté
     */
    public static function currentAdmin(): array
    {
        if (self::container()->has(AuthorizationService::class)) {
            return self::container()->get(AuthorizationService::class)->currentAdmin();
        }
        return is_array($_SESSION['adpro'] ?? null) ? $_SESSION['adpro'] : [];
    }

    /**
     * Retourne le rôle de l'admin connecté
     */
    public static function currentRole(): string
    {
        if (self::container()->has(AuthorizationService::class)) {
            return self::container()->get(AuthorizationService::class)->currentRole();
        }
        $admin = self::currentAdmin();
        $role = (string) ($admin['role'] ?? 'directeur');

        return in_array($role, self::VALID_ROLES, true) ? $role : 'directeur';
    }

    /**
     * Vérifie si l'admin a un des rôles spécifiés
     */
    public static function hasRole(array $roles): bool
    {
        if (self::container()->has(AuthorizationService::class)) {
            return self::container()->get(AuthorizationService::class)->hasRole($roles);
        }
        return in_array(self::currentRole(), $roles, true);
    }

    /**
     * Exige un rôle spécifique
     */
    public static function requireRole(array $roles, string $loginUrl = 'Auth/login.php'): void
    {
        self::requireAuth($loginUrl);

        if (!self::hasRole($roles)) {
            self::abortForbidden();
        }
    }

    /**
     * Tente de connecter un admin
     */
    public static function login(string $username, string $password): array
    {
        if (self::container()->has(AdminAuthenticationService::class)) {
            return self::container()->get(AdminAuthenticationService::class)->authenticate($username, $password);
        }
        $admin = self::repository()->findByUsername($username);

        if (!$admin || empty($admin['password'])) {
            return [];
        }

        $storedPassword = (string) $admin['password'];
        $passwordHashValid = password_verify($password, $storedPassword);
        $legacyPasswordValid = !$passwordHashValid && hash_equals($storedPassword, md5($password));

        if (!$passwordHashValid && !$legacyPasswordValid) {
            return [];
        }

        if ($legacyPasswordValid) {
            try {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                self::repository()->updatePassword((int) $admin['id_admin'], $newHash);
                $admin['password'] = $newHash;
            } catch (PDOException $e) {
                error_log('Password rehash error: ' . $e->getMessage());
            }
        }

        return $admin;
    }

    private static function repository(): AdminRepository
    {
        $container = $GLOBALS['patro_container'] ?? null;
        if ($container instanceof \Patro\Shared\Container && $container->has(AdminRepository::class)) {
            return $container->get(AdminRepository::class);
        }

        throw new \RuntimeException('Repository administrateur non enregistré dans le conteneur Patro.');
    }

    /**
     * Déconnecte l'admin de manière sécurisée
     */
    public static function logout(): void
    {
        if (self::container()->has(AdminAuthenticationService::class)) {
            self::container()->get(AdminAuthenticationService::class)->logout();
            return;
        }
        unset($_SESSION['adpro'], $_SESSION['admin_last_activity'], $_SESSION['csrf_token']);
        
        // Destruction complète de la session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Vérifie le timeout de session
     */
    public static function checkSessionTimeout(): void
    {
        if (!empty($_SESSION['adpro'])) {
            $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? time());

            $timeoutSeconds = Environment::int('ADMIN_SESSION_TIMEOUT_SECONDS', 3600);
            if ($timeoutSeconds > 0 && (time() - $lastActivity) > $timeoutSeconds) {
                unset($_SESSION['adpro'], $_SESSION['admin_last_activity'], $_SESSION['csrf_token']);
            } else {
                $_SESSION['admin_last_activity'] = time();
            }

        }
    }

    private static function container(): \Patro\Shared\Container
    {
        $container = $GLOBALS['patro_container'] ?? null;
        if (!$container instanceof \Patro\Shared\Container) {
            throw new \RuntimeException('Conteneur Patro indisponible.');
        }
        return $container;
    }

    /**
     * Redirection sécurisée
     */
    private static function redirectTo(string $url): void
    {
        $url = trim(str_replace(["\r", "\n"], '', $url));
        if ($url === '' || str_starts_with($url, '//')) {
            header('Location: index.php');
            exit();
        }

        header('Location: ' . $url);
        exit();
    }

    /**
     * Affiche une erreur 403
     */
    private static function abortForbidden(string $message = 'Acces interdit.'): void
    {
        http_response_code(403);
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>403 - Acces interdit</title></head><body><h1>403</h1><p>' . $safeMessage . '</p></body></html>';
        exit();
    }
}
