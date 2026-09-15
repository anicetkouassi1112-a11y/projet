<?php

declare(strict_types=1);

namespace Patro\Http;

use Patro\Config\Environment;
use RuntimeException;

/**
 * Encapsule le démarrage et la durée de vie de la session HTTP.
 */
final class SessionManager
{
    private bool $started = false;

    public function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = session_status() === PHP_SESSION_ACTIVE;
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf('La session ne peut pas démarrer après l’envoi des en-têtes (%s:%d).', $file, $line));
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $secure || Environment::bool('APP_FORCE_HTTPS', false),
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');

        session_start();
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started || session_status() === PHP_SESSION_ACTIVE;
    }

    public function enforceAdminTimeout(int $timeoutSeconds): void
    {
        if (empty($_SESSION['adpro'])) {
            return;
        }

        $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? time());
        if ($timeoutSeconds > 0 && (time() - $lastActivity) > $timeoutSeconds) {
            unset($_SESSION['adpro'], $_SESSION['admin_last_activity'], $_SESSION['csrf_token']);
            return;
        }

        $_SESSION['admin_last_activity'] = time();
    }

    public function regenerate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Impossible de renouveler l’identifiant de session.');
        }
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        $params = session_get_cookie_params();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        $this->started = false;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function flash(string $type, string $message): void
    {
        $messages = $this->get('flash_messages', []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = ['type' => $type, 'message' => $message];
        $this->set('flash_messages', $messages);
    }
}
