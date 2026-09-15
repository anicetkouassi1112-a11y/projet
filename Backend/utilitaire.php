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
    return ensureAnnee($anneeActive);
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

function traiterRecherche(string $search, int $annee, ?string $typeSession = null): array
{
    // Nettoyer et valider le terme de recherche
    $search = appCleanText($search, 100);
    if ($search === '') {
        return [
            'success'  => false,
            'message'  => 'Terme de recherche vide.',
            'inscrits' => [],
        ];
    }
    
    try {
        $anneeId = ensureAnnee($annee);
        $typeSession = normalizeSessionType($typeSession, currentSessionType());
        $inscrits = appContainer()
            ->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
            ->search($search, $anneeId, $typeSession);

        return [
            'success'  => true,
            'message'  => '',
            'inscrits' => $inscrits,
        ];
    } catch (PDOException $e) {
        error_log('Search error: ' . $e->getMessage());
        return [
            'success'  => false,
            'message'  => 'Erreur pendant la recherche.',
            'inscrits' => [],
        ];
    }
}

function handleCreerSectionAction(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ($_POST['action'] ?? '') !== 'creer_section') {
        return;
    }

    if (!adminHasRole(['directeur'])) {
        setFlashMessage('danger', 'Acces interdit.');
        redirectTo(lien('creer_section'));
    }

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        setFlashMessage('danger', 'Jeton CSRF invalide.');
        redirectTo(lien('creer_section'));
    }

    $ageMin = filter_var(input('age_min'), FILTER_VALIDATE_INT);
    $ageMax = filter_var(input('age_max'), FILTER_VALIDATE_INT);
    $result = creerSection(input('nom_section'), input('description'), input('genre'), $ageMin, $ageMax);
    setFlashMessage($result['alert_type'], $result['message']);
    redirectTo(lien('creer_section'));
}
