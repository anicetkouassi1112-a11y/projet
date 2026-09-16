<?php

declare(strict_types=1);

namespace Patro\Inscription;

use Patro\Domain\Inscription\Genre;
use Patro\Domain\Inscription\Repository\SectionRepository;
use PDO;
use PDOException;

/**
 * Service de gestion des sections
 */
class SectionService
{
    private PDO $connection;
    private SectionRepository $repository;

    public function __construct(PDO $connection, SectionRepository $repository)
    {
        $this->connection = $connection;
        $this->repository = $repository;
    }

    /**
     * Récupère toutes les sections
     */
    public function getAllSections(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Vérifie si une section existe par son nom
     */
    public function nomSectionExiste(string $nom): bool
    {
        return $this->repository->existsByName($nom);
    }

    /**
     * Vérifie s'il y a chevauchement d'intervalles d'âge
     */
    public function sectionIntervalOverlap(string $genre, int $ageMin, int $ageMax): array
    {
        return $this->repository->findAgeOverlap($genre, $ageMin, $ageMax);
    }

    /**
     * Crée une nouvelle section
     */
    public function creerSection(string $nomSection, string $description = '', string $genre = '', int $ageMin = 0, int $ageMax = 0): array
    {
        $nomSection = $this->cleanText($nomSection, 100);
        $description = $this->cleanText($description, 255);
        $genre = $this->normalizeGenre($genre);
        $ageMin = $this->validateAge($ageMin);
        $ageMax = $this->validateAge($ageMax);

        if ($nomSection === '') {
            return ['success' => false, 'message' => 'Le nom de la section est obligatoire.', 'alert_type' => 'warning'];
        }

        if (!in_array($genre, Genre::values(), true)) {
            return ['success' => false, 'message' => 'Le genre de la section est obligatoire.', 'alert_type' => 'warning'];
        }

        if ($ageMin === false || $ageMax === false) {
            return ['success' => false, 'message' => 'Les ages minimum et maximum sont obligatoires.', 'alert_type' => 'warning'];
        }

        if ($ageMin > $ageMax) {
            return ['success' => false, 'message' => 'L age minimum doit etre inferieur ou egal a l age maximum.', 'alert_type' => 'warning'];
        }

        try {
            if ($this->nomSectionExiste($nomSection)) {
                return ['success' => false, 'message' => 'Cette section existe deja.', 'alert_type' => 'warning'];
            }

            $overlap = $this->sectionIntervalOverlap($genre, $ageMin, $ageMax);
            if ($overlap) {
                return [
                    'success' => false,
                    'message' => 'Chevauchement refuse: la section "' . (string) $overlap['nom_section'] . '" couvre deja les ages ' . (int) $overlap['age_min'] . '-' . (int) $overlap['age_max'] . ' pour ce genre.',
                    'alert_type' => 'warning',
                ];
            }

            return [
                'success' => true,
                'message' => 'Section "' . $nomSection . '" ajoutee avec succes.',
                'alert_type' => 'success',
                'id_section' => $this->repository->create(
                    $nomSection,
                    $description !== '' ? $description : null,
                    $genre,
                    $ageMin,
                    $ageMax
                ),
            ];
        } catch (PDOException $e) {
            error_log('Create section error: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Cette section existe deja.', 'alert_type' => 'warning'];
            }

            return ['success' => false, 'message' => 'Erreur base de donnees pendant l ajout de la section.', 'alert_type' => 'danger'];
        }
    }

    /**
     * Trouve une section correspondant à l'âge et au genre
     */
    public function findMatchingSection(string $genre, int $age, ?string $typeSession = null): ?array
    {
        $genre = $this->normalizeGenre($genre);
        if (!in_array($genre, Genre::values(), true)) {
            return null;
        }

        // Session scolaire: pas de repartition par section
        if (!$this->sectionBreakdownEnabled($typeSession)) {
            return null;
        }

        return $this->repository->findMatching($genre, $age);
    }

    /**
     * Nettoie et valide un texte
     */
    private function cleanText(string $value, int $maxLength = 255): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    /**
     * Normalise le genre
     */
    public function normalizeGenre(?string $genre): string
    {
        return Genre::normalize($genre);
    }

    /**
     * Valide un âge
     */
    private function validateAge($age)
    {
        return filter_var($age, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 120]]);
    }

    /**
     * Vérifie si la répartition par section est activée
     */
    public function sectionBreakdownEnabled(?string $typeSession = null): bool
    {
        $typeSession = strtolower(trim((string) $typeSession));
        return in_array($typeSession, ['vacance'], true);
    }

}
