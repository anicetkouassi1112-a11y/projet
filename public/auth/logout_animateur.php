<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

// Si une session PHP n'est pas démarrée, on s'en assure (géré par utilitaire.php normalement)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['animateur']);

// Optionnel : Ajouter un message flash confirmant la déconnexion
setFlashMessage('success', 'Vous avez été déconnecté de l\'espace animateur.');

// Redirection vers la page de connexion de l'animateur
redirectTo(app_url('public/auth/connexion.php'));