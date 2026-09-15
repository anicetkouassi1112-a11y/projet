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
        $conn    = getConnection();
        $anneeId = ensureAnnee($annee, $conn);
        $typeSession = normalizeSessionType($typeSession, currentSessionType());
        $stmt    = $conn->prepare(
            'SELECT u.*,
                    i.id_inscription AS id_inscrit,
                    i.id_inscription,
                    i.id_section,
                    i.identifiant,
                    i.etat,
                    i.montant_inscription,
                    i.prix_tee_shirt,
                    i.taille_tee_shirt,
                    s.nom_section AS section
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             LEFT JOIN section s ON s.id_section = i.id_section
             INNER JOIN session ses ON ses.id_session = i.id_session
             WHERE ses.annee_id = :annee_id
               AND ses.type_session = :type_session
               AND i.etat = :etat
               AND (u.nom LIKE :search_nom OR u.prenom LIKE :search_prenom OR CONCAT(u.nom, " ", u.prenom) LIKE :search_fullname)
             ORDER BY i.id_inscription ASC'
        );
        $searchTerm = '%' . $search . '%';
        $stmt->execute([
            ':annee_id' => $anneeId,
            ':type_session' => $typeSession,
            ':etat' => 'inscrit',
            ':search_nom' => $searchTerm,
            ':search_prenom' => $searchTerm,
            ':search_fullname' => $searchTerm,
        ]);

        return [
            'success'  => true,
            'message'  => '',
            'inscrits' => $stmt->fetchAll(PDO::FETCH_ASSOC),
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
