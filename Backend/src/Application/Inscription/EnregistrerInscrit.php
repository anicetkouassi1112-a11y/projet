<?php

declare(strict_types=1);

namespace Patro\Application\Inscription;

use Patro\Domain\Inscription\Repository\InscriptionRepository;
use Patro\Domain\Inscription\Repository\SectionRepository;
use Patro\Domain\Inscription\Repository\SessionRepository;
use Patro\Infrastructure\Database\TransactionManager;
use PDOException;
use Throwable;

final class EnregistrerInscrit
{
    public function __construct(
        private InscriptionRepository $inscriptions,
        private SectionRepository $sections,
        private SessionRepository $sessions,
        private TransactionManager $transactions
    ) {
    }

    /** @return array<string,mixed> */
    public function execute(EnregistrerInscritCommand $command): array
    {
        $data = $this->normalize($command);
        if ($data['error'] !== '') {
            return ['success' => false, 'message' => $data['error'], 'alert_type' => $data['alert_type']];
        }

        $section = $this->sections->findMatching($data['genre'], $data['age']);
        if ($section === null && $command->sectionObligatoire) {
            return [
                'success' => false,
                'message' => 'Aucune secion ne correspond a cet age et ce genre. Veuillez contacter l administrateur.',
                'alert_type' => 'danger',
            ];
        }

        $sectionName = $section['nom_section'] ?? null;
        $sectionId = isset($section['id_section']) ? (int) $section['id_section'] : null;

        try {
            $this->transactions->begin();
            $sessionId = $this->sessions->ensureSession($command->annee, $command->typeSession);
            $yearId = $this->sessions->findYearId($command->annee);
            if ($yearId === null) {
                throw new \RuntimeException('Année introuvable après création.');
            }

            $existingId = $this->inscriptions->findIdByIdentity(
                $data['nom'], $data['prenom'], $data['dateNaissance'], $yearId, $command->typeSession
            );
            if ($existingId !== null) {
                $this->transactions->commit();
                return [
                    'success' => false,
                    'message' => 'Cette personne est deja inscrite pour cette annee et ce type de session.',
                    'alert_type' => 'warning',
                    'id_inscrit' => $existingId,
                ];
            }

            $identifier = $this->inscriptions->allocateIdentifier(
                $sectionName,
                $data['genre'],
                $command->identifiantDigits
            );
            $userId = $this->inscriptions->createOrUpdateUser(
                $data['nom'], $data['prenom'], $data['dateNaissance'],
                $data['genre'], $data['tel'], $data['adresse']
            );
            $inscriptionId = $this->inscriptions->create(
                $identifier['identifiant'], $userId, $sessionId, $sectionId,
                $data['montantInscription'], $data['prixTeeShirt'],
                $data['tailleTeeShirt'] !== '' ? $data['tailleTeeShirt'] : null,
                'En attente'
            );
            $this->transactions->commit();

            return [
                'success' => true,
                'message' => 'Inscription enregistree avec succes.',
                'alert_type' => 'success',
                'id_inscrit' => $inscriptionId,
                'identifiant' => $identifier['identifiant'],
                'ordre_inscription' => $identifier['ordre_inscription'],
                'section' => $sectionName,
                'id_section' => $sectionId,
                'montant_inscription' => $data['montantInscription'],
                'prix_tee_shirt' => $data['prixTeeShirt'],
                'taille_tee_shirt' => $data['tailleTeeShirt'],
                'annee_id' => $yearId,
                'type_session' => $command->typeSession,
            ];
        } catch (PDOException $exception) {
            $this->transactions->rollback();
            error_log('Register inscrit error: ' . $exception->getMessage());
            return [
                'success' => false,
                'message' => (string) $exception->getCode() === '23000'
                    ? 'Cette inscription existe deja ou viole une contrainte unique.'
                    : 'Erreur base de donnees pendant l inscription.',
                'alert_type' => 'danger',
            ];
        } catch (Throwable $exception) {
            $this->transactions->rollback();
            error_log('Register inscrit error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Erreur pendant la generation de l identifiant.', 'alert_type' => 'danger'];
        }
    }

    /** @return array<string,mixed> */
    private function normalize(EnregistrerInscritCommand $command): array
    {
        $clean = static function (string $value, int $length): string {
            $value = trim(strip_tags($value));
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
        };
        $genre = match ($this->lookup($command->genre)) {
            'garcon', 'garçon', 'masculin', 'm' => 'Garçon',
            'fille', 'feminin', 'f' => 'Fille',
            default => '',
        };
        $tel = preg_replace('/\D+/', '', $command->tel) ?? '';
        $size = strtoupper(trim($command->tailleTeeShirt));
        $allowedSizes = ['S', 'M', 'L', 'XL', 'XXL'];
        $price = trim($command->prixChoisi);
        $withShirt = (string) ($command->montantBase + $command->prixTeeShirt);
        $montantInscription = 0;
        $prixTeeShirt = 0;

        if ($price === (string) $command->montantBase) {
            $size = '';
            $montantInscription = $command->montantBase;
        } elseif ($price === $withShirt) {
            $montantInscription = $command->montantBase;
            $prixTeeShirt = $command->prixTeeShirt;
            if (!in_array($size, $allowedSizes, true)) {
                return ['error' => 'Veuillez selectionner la taille du tee-shirt.', 'alert_type' => 'warning'];
            }
        } else {
            return ['error' => 'Montant d inscription invalide.', 'alert_type' => 'danger'];
        }

        if ($clean($command->nom, 120) === '' || $clean($command->prenom, 120) === '' ||
            $command->dateNaissance === '' || $genre === '' || $tel === '' || $clean($command->adresse, 180) === '') {
            return ['error' => 'Veuillez remplir tous les champs obligatoires.', 'alert_type' => 'danger'];
        }
        if (!in_array($genre, ['Garçon', 'Fille'], true)) {
            return ['error' => 'Genre invalide.', 'alert_type' => 'danger'];
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($command->dateNaissance));
        if (!$date || $date->format('Y-m-d') !== trim($command->dateNaissance)) {
            return ['error' => 'Date de naissance invalide.', 'alert_type' => 'danger'];
        }
        if (!preg_match('/^(01|05|07)[0-9]{8}$/', $tel)) {
            return ['error' => 'Numero de telephone ivoirien invalide.', 'alert_type' => 'danger'];
        }
        $age = $command->annee - (int) $date->format('Y');
        if ($age >= 25) {
            return ['error' => 'Aucune inscription n\'est autorisé pour un age supérieur ou égale à 25 ans. Toutefois, vous pouvez vous inscrit en tant qu\'animateur. Pour plus information veuillez-vous rendre en présentiel.', 'alert_type' => 'warning'];
        }

        return [
            'error' => '', 'alert_type' => 'danger', 'nom' => $clean($command->nom, 120),
            'prenom' => $clean($command->prenom, 120), 'dateNaissance' => trim($command->dateNaissance),
            'genre' => $genre, 'tel' => $tel, 'adresse' => $clean($command->adresse, 180),
            'tailleTeeShirt' => $size, 'montantInscription' => $montantInscription,
            'prixTeeShirt' => $prixTeeShirt, 'age' => $age,
        ];
    }

    private function lookup(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', strtr($value, ['ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e'])) ?? '';
    }
}
