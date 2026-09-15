<?php declare(strict_types=1);

require_once __DIR__ . '/utilitaire.php';

function backupDirectory(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
}

function backupTableExists(string $table, ?PDO $connect = null): bool
{
    $connect = $connect ?: getConnection();
    try {
        $stmt = $connect->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table'
        );
        $stmt->execute([':table' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log('Backup table check error: ' . $e->getMessage());
        return false;
    }
}

function backupFetchByInscritIds(string $table, string $idColumn, array $ids, ?PDO $connect = null): array
{
    return [];
}

function createDatabaseBackup(int $annee, string $typeSession): array
{
    $typeSession = normalizeSessionType($typeSession);
    $connect = getConnection();
    $anneeId = selectedYearId($annee);

    $stmt = $connect->prepare(
        'SELECT i.id_inscription AS id_inscrit,
                i.identifiant,
                i.etat,
                i.montant_inscription,
                i.prix_tee_shirt,
                i.taille_tee_shirt,
                u.nom,
                u.prenom,
                u.date_naissance,
                u.genre,
                u.tel,
                u.adresse,
                s.nom_section AS section,
                ses.type_session,
                a.ans AS annee
         FROM inscription i
         INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
         INNER JOIN section s ON s.id_section = i.id_section
         INNER JOIN session ses ON ses.id_session = i.id_session
         INNER JOIN annee a ON a.idannee = ses.annee_id
         WHERE ses.annee_id = :annee_id
           AND ses.type_session = :type_session
         ORDER BY i.id_inscription ASC'
    );
    $stmt->execute([
        ':annee_id' => $anneeId,
        ':type_session' => $typeSession,
    ]);
    $inscrits = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
