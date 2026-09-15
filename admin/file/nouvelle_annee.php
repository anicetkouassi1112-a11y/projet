<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('../home.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    setFlashMessage('danger', 'Jeton CSRF invalide.');
    redirectTo('../home.php');
}

try {
    $anneeActuelle = date('Y');
    $service = new \Patro\Inscription\SessionService();
    $service->ensureAnnee((int) $anneeActuelle);
    setFlashMessage('success', 'Passage à la nouvelle année (' . $anneeActuelle . ') réussi. Les données de l\'année précédente sont conservées.');

} catch (PDOException $e) {
    error_log('Nouvelle annee database error: ' . $e->getMessage());
    setFlashMessage('danger', 'Erreur lors du passage à la nouvelle année.');
} catch (Exception $e) {
    error_log('Nouvelle annee system error: ' . $e->getMessage());
    setFlashMessage('danger', 'Erreur système pendant le passage à la nouvelle année.');
}

redirectTo('../home.php?' . http_build_query([
    'annee' => date('Y'),
    'type_session' => currentSessionType(),
]));
?>
