<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/site.php';

// ============================================================
//  utilitaire.php - Fonctions métier de l'application
//  Les fonctions de base (app_env, getConnection, csrfToken,
//  e, redirectTo, setFlashMessage, getFlashMessages,
//  app_base_url, app_url) sont definies
//  dans functions.php - ne pas les redéclarer ici.
// ============================================================

function input(string $name): string
{
    return \Patro\Http\RequestHelper::input($name);
}

function lien(string $page, array|string $params = [], bool $forPublic = false): string {
    $query = ['page' => $page];

    if (is_string($params) && $params !== '') {
        parse_str(ltrim($params, '&?'), $extra);
        $query = array_merge($query, $extra);
    } elseif (is_array($params)) {
        $query = array_merge($query, $params);
    }

    if ($forPublic) {
        return app_url('public/home.php') . '?' . http_build_query($query);
    }

    return 'home.php?' . http_build_query($query);
}

function displayFlashMessage(): void
{
    $allowedTypes = ['success', 'info', 'warning', 'danger'];

    foreach (getFlashMessages() as $message) {
        $type = (string) ($message['type'] ?? 'info');
        if (!in_array($type, $allowedTypes, true)) {
            $type = 'info';
        }

        $safeType = e($type);
        $content = e((string) ($message['message'] ?? ''));

        echo "<div class=\"alert alert-{$safeType} alert-dismissible fade show\" role=\"alert\">";
        echo $content;
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>';
        echo '</div>';
    }
}

function selectedYearId(int $anneeActive): int
{
    return appContainer()->get(\Patro\Inscription\SessionService::class)->ensureAnnee($anneeActive);
}

function activeYearFromRequest(): int
{
    return \Patro\Http\RequestHelper::activeYearFromRequest();
}

function activeSessionTypeFromRequest(): string
{
    return \Patro\Http\RequestHelper::activeSessionTypeFromRequest(currentSessionType());
}

function displayYearFromRequest(?int $defaultYear = null): int
{
    return \Patro\Http\RequestHelper::displayYearFromRequest($defaultYear);
}

function displaySessionTypeFromRequest(?string $defaultType = null): string
{
    return \Patro\Http\RequestHelper::displaySessionTypeFromRequest($defaultType, currentSessionType());
}
