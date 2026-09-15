<?php

declare(strict_types=1);

namespace Patro\Domain\Inscription\Repository;

use PDO;

final class BackupRepository
{
    public function __construct(private PDO $connection)
    {
    }

    /** @return list<array<string,mixed>> */
    public function findForSession(int $yearId, string $sessionType): array
    {
        $statement = $this->connection->prepare(
            'SELECT i.id_inscription AS id_inscrit, i.identifiant, i.etat,
                    i.montant_inscription, i.prix_tee_shirt, i.taille_tee_shirt,
                    u.nom, u.prenom, u.date_naissance, u.genre, u.tel, u.adresse,
                    s.nom_section AS section, ses.type_session, a.ans AS annee
             FROM inscription i
             INNER JOIN utilisateur u ON u.id_utilisateur = i.id_utilisateur
             INNER JOIN section s ON s.id_section = i.id_section
             INNER JOIN session ses ON ses.id_session = i.id_session
             INNER JOIN annee a ON a.idannee = ses.annee_id
             WHERE ses.annee_id = :year_id AND ses.type_session = :session_type
             ORDER BY i.id_inscription ASC'
        );
        $statement->execute([':year_id' => $yearId, ':session_type' => $sessionType]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
