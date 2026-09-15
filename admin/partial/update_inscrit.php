<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

header('Content-Type: application/json; charset=utf-8');

// 1. Vérifier la session administrateur
if (class_exists('\Patro\Auth\AdminAuth')) {
    \Patro\Auth\AdminAuth::requireAuth();
    if (!\Patro\Auth\AdminAuth::hasRole(['directeur'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acces interdit.']);
        exit();
    }
} else {
    if (empty($_SESSION['adpro'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Session administrateur expiree.']);
        exit();
    }

    if (!adminHasRole(['directeur'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acces interdit.']);
        exit();
    }
}

// 2. Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode non autorisee.']);
    exit();
}

// 3. Vérifier le Content-Type
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!str_contains($contentType, 'application/json')) {
    http_response_code(415);
    echo json_encode(['success' => false, 'message' => 'Content-Type doit etre application/json.']);
    exit();
}

// 4. Vérifier le token CSRF
if (class_exists('\Patro\Security\CsrfProtection')) {
    \Patro\Security\CsrfProtection::requireToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
} else {
    if (!verifyCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide.']);
        exit();
    }
}

// 5. Récupérer et valider le payload JSON avec limite de taille
$maxPayloadSize = 1024 * 1024; // 1MB
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxPayloadSize) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Payload trop volumineux.']);
    exit();
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || $rawInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donnees JSON invalides.']);
    exit();
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Donnees JSON invalides.']);
    exit();
}

$idInscrit = filter_var($payload['id_inscrit'] ?? null, FILTER_VALIDATE_INT);
if (!$idInscrit || $idInscrit <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'ID d inscrit invalide.']);
    exit();
}

// 6. Récupérer les données de l'inscrit
$existing = appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)->findById((int) $idInscrit);
if (!$existing) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Inscrit introuvable.']);
    exit();
}

// 7. Vérifier que l'inscrit appartient à la session active
$sessionId = (int) ($existing['id_session'] ?? 0);
if ($sessionId !== getActiveAdminSessionId()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Vous ne pouvez modifier que les inscrits de la session active.']);
    exit();
}

// 8. Récupérer le type de session de l'inscrit
$typeSession = (string) ($existing['type_session'] ?? currentSessionType());

// 9. Récupérer et valider les champs modifiés
$nom = appCleanText((string) ($payload['nom'] ?? ''), 120);
$prenom = appCleanText((string) ($payload['prenom'] ?? ''), 120);
$dateNaissance = trim((string) ($payload['date_naissance'] ?? ''));
$genre = normalizeGenre((string) ($payload['genre'] ?? ''));
$tel = normalizeIvorianPhone((string) ($payload['tel'] ?? ''));
$adresse = appCleanText((string) ($payload['adresse'] ?? ''), 180);

if ($nom === '' || $prenom === '' || $dateNaissance === '' || $genre === '' || $tel === '' || $adresse === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.']);
    exit();
}

if (!in_array($genre, validGenres(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Genre invalide.']);
    exit();
}

if (!isValidDateString($dateNaissance)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Date de naissance invalide.']);
    exit();
}

if (!isValidIvorianPhone($tel)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Numero ivoirien invalide.']);
    exit();
}

// 10. Mise a jour via le cas d usage applicatif
$annee = (int) ($existing['annee'] ?? date('Y'));
$age = calculateAge($dateNaissance, $annee);
if ($age === null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Age invalide pour une inscription patronier.']);
    exit();
}

$section = findMatchingSection($genre, $dateNaissance, $annee, null, $typeSession);
if ($section === null && sectionBreakdownEnabled($typeSession)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Aucune section ne correspond a cet age et ce genre. Veuillez contacter l administrateur.']);
    exit();
}

$result = appContainer()->get(\Patro\Application\Inscription\ModifierInscrit::class)->execute(
    new \Patro\Application\Inscription\ModifierInscritCommand(
        (int) $idInscrit,
        (int) $existing['id_utilisateur'],
        $nom, $prenom, $dateNaissance, $genre, $tel, $adresse,
        $section ? (int) $section['id_section'] : null
    )
);
if (!$result['success']) {
    http_response_code(500);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit();
}

if (ob_get_length()) {
    ob_clean();
}

echo json_encode([
    'success' => true,
    'message' => $result['message'],
    'genre' => $genre,
    'section' => $section ? (string) $section['nom_section'] : null,
], JSON_UNESCAPED_UNICODE);