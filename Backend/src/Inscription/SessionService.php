<?php

declare(strict_types=1);

namespace Patro\Inscription;

use Patro\Database\DatabaseConnection;
use Patro\Domain\Configuration\Repository\ConfigurationRepository;
use Patro\Domain\Inscription\SessionType;
use Patro\Domain\Inscription\Repository\SessionRepository;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Service de gestion des sessions et années
 */
class SessionService
{
    private PDO $connection;
    private SessionRepository $repository;
    private ConfigurationRepository $configurationRepository;

    public function __construct(
        ?PDO $connection = null,
        ?SessionRepository $repository = null,
        ?ConfigurationRepository $configurationRepository = null
    )
    {
        $this->connection = $connection ?? DatabaseConnection::getConnection();
        $this->repository = $repository ?? new SessionRepository($this->connection);
        $this->configurationRepository = $configurationRepository ?? new ConfigurationRepository($this->connection);
    }

    /**
     * Assure l'existence d'une année
     */
    public function ensureAnnee(int $anneeVal): int
    {
        return $this->repository->ensureYear($anneeVal);
    }

    /**
     * Récupère l'ID d'une année par sa valeur
     */
    public function getAnneeIdByValue(int $anneeVal): ?int
    {
        return $this->repository->findYearId($anneeVal);
    }

    /**
     * Récupère la valeur d'une année par son ID
     */
    public function getAnneeValueById(int $anneeId): ?int
    {
        return $this->repository->findYearValue($anneeId);
    }

    /**
     * Récupère toutes les années distinctes
     */
    public function getDistinctYears(): array
    {
        try {
            return $this->repository->findAllYears();
        } catch (PDOException $e) {
            error_log('Distinct years error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Assure l'existence d'une session
     */
    public function ensureSession(int $anneeVal, string $typeSession): int
    {
        return $this->repository->ensureSession($anneeVal, $typeSession);
    }

    /**
     * Récupère l'ID de la session active pour l'administration
     */
    public function getActiveAdminSessionId(): int
    {
        $annee = (int) date('Y');
        $type = $this->getCurrentSessionType();
        return $this->ensureSession($annee, $type);
    }

    /**
     * Récupère le label d'une session par son ID
     */
    public function sessionLabelById(int $idSession): string
    {
        $session = $this->repository->findLabelData($idSession);

        if (!$session) {
            return 'Session inconnue';
        }

        return (string) $session['year'] . ' - ' . $this->sessionTypeLabel((string) $session['type']);
    }

    /**
     * Récupère toutes les sessions
     */
    public function getAllSessions(): array
    {
        return $this->repository->findAll();
    }

    /**
     * Vérifie si une session existe
     */
    public function sessionExists(int $idSession): bool
    {
        return $this->repository->exists($idSession);
    }

    /**
     * Normalise le type de session
     */
    public function normalizeSessionType(?string $type, string $default = 'scolaire'): string
    {
        $fallback = SessionType::normalize($default);
        return SessionType::normalize($type, $fallback)->value;
    }

    /**
     * Retourne le label du type de session
     */
    public function sessionTypeLabel(?string $type): string
    {
        return SessionType::normalize($type)->label();
    }

    /**
     * Récupère le type de session actuel depuis la configuration
     */
    public function getCurrentSessionType(): string
    {
        $configValue = $this->configurationRepository->find('inscription_type_session', 'scolaire');
        return $this->normalizeSessionType($configValue);
    }

    /**
     * Récupère une valeur de configuration
     */
    private function getConfig(string $key, ?string $default = null): ?string
    {
        try {
            return $this->configurationRepository->find($key, $default);
        } catch (PDOException $e) {
            error_log('Get config error: ' . $e->getMessage());
            return $default;
        }
    }
}
