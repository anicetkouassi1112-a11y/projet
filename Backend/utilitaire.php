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
    if (class_exists('\Patro\Http\RequestHelper')) {
        return \Patro\Http\RequestHelper::input($name);
    }
    
    $value = $_POST[$name] ?? '';
    return appCleanText((string) $value, 255);
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
    return ensureAnnee($anneeActive);
}

function activeYearFromRequest(): int
{
    if (class_exists('\Patro\Http\RequestHelper')) {
        return \Patro\Http\RequestHelper::activeYearFromRequest();
    }
    
    $defaultYear = (int) ($_SESSION['annee_active'] ?? date('Y'));
    $year = requestIntParam('annee', $defaultYear, 2000, 2100);
    
    if ($year < 2000 || $year > 2100) {
        $year = (int) date('Y');
    }

    $_SESSION['annee_active'] = $year;
    return $year;
}

function activeSessionTypeFromRequest(): string
{
    if (class_exists('\Patro\Http\RequestHelper')) {
        return \Patro\Http\RequestHelper::activeSessionTypeFromRequest(currentSessionType());
    }
    
    $requestedType = requestTextParam('type_session', 50);
    $sessionType = (string) ($_SESSION['type_session_active'] ?? currentSessionType());
    
    $typeSession = $requestedType !== '' 
        ? normalizeSessionType($requestedType, currentSessionType())
        : normalizeSessionType($sessionType, currentSessionType());

    $_SESSION['type_session_active'] = $typeSession;
    return $typeSession;
}

function displayYearFromRequest(?int $defaultYear = null): int
{
    if (class_exists('\Patro\Http\RequestHelper')) {
        return \Patro\Http\RequestHelper::displayYearFromRequest($defaultYear);
    }
    
    $year = requestIntParam('annee', $defaultYear ?? (int) ($_SESSION['annee_active'] ?? date('Y')), 2000, 2100);
    if ($year < 2000 || $year > 2100) {
        return (int) date('Y');
    }

    return $year;
}

function displaySessionTypeFromRequest(?string $defaultType = null): string
{
    if (class_exists('\Patro\Http\RequestHelper')) {
        return \Patro\Http\RequestHelper::displaySessionTypeFromRequest($defaultType, currentSessionType());
    }
    
    $fallback = normalizeSessionType($defaultType ?? (string) ($_SESSION['type_session_active'] ?? currentSessionType()), currentSessionType());
    $requestedType = requestTextParam('type_session', 50);

    return $requestedType !== '' 
        ? normalizeSessionType($requestedType, $fallback)
        : $fallback;
}

function redirectWithoutActionParams(int $anneeActive, ?string $typeSession = null): void
{
    $params = $_GET;
    unset($params['delete_id'], $params['update_message'], $params['modified_id'], $params['csrf_token']);
    $params['annee'] = $anneeActive;
    if ($typeSession !== null) {
        $params['type_session'] = normalizeSessionType($typeSession);
    }

    $currentFile = basename($_SERVER['PHP_SELF']);
    header('Location: ' . $currentFile . '?' . http_build_query($params));
    exit();
}
