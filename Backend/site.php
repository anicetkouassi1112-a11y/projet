<?php

/**
 * Contenu public et animateur: jeux, images d'activite, contact.
 */

/**
 * Gère l'accès et l'identité de l'utilisateur (Animateur).
 */
function currentAnimateur(): array
{
    if (appContainer()->has(\Patro\Application\Animateur\AnimateurAuthorizationService::class)) {
        return appContainer()->get(\Patro\Application\Animateur\AnimateurAuthorizationService::class)->current();
    }
    
    if (is_array($_SESSION['animateur'] ?? null)) {
        return $_SESSION['animateur'];
    }
    return [];
}

function requireAnimateur(string $loginUrl = 'auth/connexion.php'): void
{
    // 1. Strictement réservé aux animateurs. Si la session animateur est vide, on redirige.
    // L'administrateur n'aura donc pas accès via sa session "adpro".
    $authorization = appContainer()->get(\Patro\Application\Animateur\AnimateurAuthorizationService::class);
    if (!$authorization->isAuthenticated()) {
        redirectTo(app_url('public/' . ltrim($loginUrl, '/')));
    }

    // 2. Vérification du statut bloqué pour l'animateur
    if ($authorization->isBlocked()) {
        $authorization->logout();
        appContainer()->get(\Patro\Http\SessionManager::class)->flash('danger', 'Votre compte animateur est bloqué.');
        redirectTo(app_url('public/' . ltrim($loginUrl, '/')));
    }
}