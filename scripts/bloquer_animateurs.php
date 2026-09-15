<?php

require_once __DIR__ . '/../Backend/utilitaire.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acces interdit.');
}

$options = getopt('', ['annee::', 'type_session::', 'days-after-open::', 'force']);
$annee = isset($options['annee']) ? (int) $options['annee'] : (int) date('Y');
$typeSession = normalizeSessionType((string) ($options['type_session'] ?? currentSessionType()));
$daysAfterOpen = isset($options['days-after-open']) ? (int) $options['days-after-open'] : null;
$force = array_key_exists('force', $options);

if ($annee < 2000 || $annee > 2100) {
    fwrite(STDERR, "Annee invalide.\n");
    exit(1);
}

if ($daysAfterOpen !== null && !$force) {
    $dateDebut = getConfigDateDebut();
    if (!$dateDebut) {
        fwrite(STDOUT, "Blocage ignore: aucune date d ouverture configuree.\n");
        exit(0);
    }

    $threshold = strtotime($dateDebut . ' +' . max(0, $daysAfterOpen) . ' days');
    if ($threshold === false || time() < $threshold) {
        fwrite(STDOUT, "Blocage ignore: delai apres ouverture non atteint.\n");
        exit(0);
    }
}

try {
    $conn = getConnection();
    $idSession = ensureSession($annee, $typeSession, $conn);
    $blocked = blockAnimateursNotRegistered($idSession, $conn);
    fwrite(STDOUT, $blocked . " animateur(s) bloque(s) pour " . sessionLabelById($idSession, $conn) . ".\n");
    exit(0);
} catch (Throwable $e) {
    error_log('CLI block animateurs error: ' . $e->getMessage());
    fwrite(STDERR, "Erreur pendant le blocage des animateurs.\n");
    exit(1);
}
