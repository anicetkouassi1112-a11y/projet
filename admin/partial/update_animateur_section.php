<?php
// Fichier : partial/update_animateur_section.php
// Endpoint AJAX appele par submitAnimateurSectionChange() (Assets/js/script.js)
// depuis partial/animateur_row.php. Attribue ou reattribue la section d'un
// animateur pour la session administrative active.
//
// Requete attendue : POST JSON { id_animateur: int, id_section: int|"" }
// En-tete requis   : X-CSRF-Token
// Reponse JSON      : { success: bool, message: string, section?: string }

declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $httpCode, bool $success, string $message, array $extra = []): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(405, false, 'Methode non autorisee.');
}

if (!adminHasRole(['directeur'])) {
    respond(403, false, 'Acces interdit.');
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    respond(419, false, 'Jeton CSRF invalide. Veuillez recharger la page.');
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    respond(400, false, 'Requete invalide.');
}

$idAnimateur = filter_var($payload['id_animateur'] ?? null, FILTER_VALIDATE_INT);
if (!$idAnimateur) {
    respond(400, false, "Identifiant d'animateur invalide.");
}

$idSectionRaw = trim((string) ($payload['id_section'] ?? ''));
$idSection = $idSectionRaw === '' ? null : filter_var($idSectionRaw, FILTER_VALIDATE_INT);
if ($idSectionRaw !== '' && !$idSection) {
    respond(400, false, 'Identifiant de section invalide.');
}

// Session administrative active : seule celle-ci peut etre modifiee, comme
// pour les autres champs editables de la ligne (cf. animateur_row.php).
$idSessionActive = getActiveAdminSessionId();

// Recuperation de l'animateur + de son affectation sur la session active,
// pour connaitre son genre et verifier qu'il appartient bien a cette session.
$animateurs = appContainer()->get(\Patro\Domain\Animateur\Repository\AnimateurRepository::class);
$sections = appContainer()->get(\Patro\Domain\Inscription\Repository\SectionRepository::class);
$animateurSession = $animateurs->findSessionAssignment($idAnimateur, $idSessionActive);

if (!$animateurSession) {
    respond(404, false, "Cet animateur n'est pas inscrit sur la session active.");
}

$genreAnimateur = normalizeAnimateurGenre((string) ($animateurSession['genre_a'] ?? ''));
$genreSectionAttendu = $genreAnimateur === 'M' ? 'Garçon' : ($genreAnimateur === 'F' ? 'Fille' : null);

$nomSection = null;

if ($idSection !== null) {
    $sectionRow = $sections->findById($idSection);

    if (!$sectionRow) {
        respond(404, false, 'Section introuvable.');
    }

    if ($genreSectionAttendu !== null && normalizeGenre((string) $sectionRow['genre']) !== $genreSectionAttendu) {
        respond(422, false, "Cette section ne correspond pas au genre de l'animateur.");
    }

    $nomSection = (string) $sectionRow['nom_section'];
}

try {
    $updated = $animateurs->updateSessionSection(
        (int) $animateurSession['id_animateur_session'],
        $idSection,
        $idSessionActive
    );
    if (!$updated && $idSection !== null) {
        respond(409, false, 'Affectation introuvable ou deja inchangée.');
    }
} catch (PDOException $e) {
    error_log('Update animateur section error: ' . $e->getMessage());
    respond(500, false, 'Erreur base de donnees pendant la mise a jour.');
}

respond(200, true, $idSection !== null ? 'Section mise a jour.' : 'Section retiree.', [
    'section' => $nomSection ?? 'Non assigne',
]);