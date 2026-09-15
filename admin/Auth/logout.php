<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

// Utiliser AdminAuth si disponible pour une déconnexion propre
if (class_exists('\Patro\Auth\AdminAuth')) {
    \Patro\Auth\AdminAuth::logout();
} else {
    // Wrapper de compatibilité
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

redirectTo('login.php');
