<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

function backupDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
}

function createDatabaseBackup(int $annee, string $typeSession): array
{
    $typeSession = normalizeSessionType($typeSession);
    $anneeId = selectedYearId($annee);
    $inscrits = appContainer()->get(\Patro\Domain\Inscription\Repository\InscriptionRepository::class)
        ->findForBackup($anneeId, $typeSession);
    $ids = array_map(static fn (array $row): int => (int) ($row['id_inscrit'] ?? 0), $inscrits);
    $ids = array_values(array_filter($ids, static fn (int $id): bool => $id > 0));

    $payload = [
        'schema_version' => 1,
        'generated_at' => date('c'),
        'application' => (string) app_env('APP_NAME', 'Patro'),
        'filters' => [
            'annee' => $annee,
            'annee_id' => $anneeId,
            'type_session' => $typeSession,
            'type_session_label' => sessionTypeLabel($typeSession),
        ],
        'counts' => [
            'inscrits' => count($inscrits),
        ],
        'data' => [
            'inscrits' => $inscrits,
        ],
    ];

    $directory = backupDirectory();
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Impossible de creer le dossier de sauvegarde.');
    }

    $filename = sprintf(
        'patro_backup_%d_%s_%s.json',
        $annee,
        $typeSession,
        date('Ymd_His')
    );
    $path = $directory . DIRECTORY_SEPARATOR . $filename;

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
        throw new RuntimeException('Impossible d ecrire le fichier de sauvegarde.');
    }

    @chmod($path, 0600);

    return [
        'filename' => $filename,
        'path' => $path,
        'bytes' => filesize($path) ?: strlen($json),
        'counts' => $payload['counts'],
    ];
}
