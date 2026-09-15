<?php

declare(strict_types=1);

namespace Patro\Inscription;

use Patro\Domain\Inscription\Repository\ThemeRepository;
use Patro\Security\CsrfProtection;
use PDO;
use PDOException;

/**
 * Service de gestion des thèmes
 */
class ThemeService
{
    private SessionService $sessionService;
    private PDO $connection;
    private ThemeRepository $repository;

    public function __construct(
        SessionService $sessionService,
        PDO $connection,
        ThemeRepository $repository
    )
    {
        $this->sessionService = $sessionService;
        $this->connection = $connection;
        $this->repository = $repository;
    }

    /**
     * Ajoute un nouveau thème
     */
    public function addTheme(string $titre, int $sessionId): array
    {
        CsrfProtection::requireToken();
        
        $activeSessionId = $this->sessionService->getActiveAdminSessionId();
        if ($sessionId !== $activeSessionId) {
            return ['success' => false, 'message' => 'Vous ne pouvez ajouter un thème que pour la session active.', 'alert_type' => 'danger'];
        }

        $titre = $this->cleanText($titre, 100);

        if ($titre === '') {
            return ['success' => false, 'message' => 'Le nom du thme est obligatoire.', 'alert_type' => 'warning'];
        }

        if ($sessionId <= 0) {
            return ['success' => false, 'message' => 'Veuillez choisir une session pour ce thme.', 'alert_type' => 'warning'];
        }

        try {
            if (!$this->sessionService->sessionExists($sessionId)) {
                return ['success' => false, 'message' => 'Session invalide.', 'alert_type' => 'danger'];
            }

            if ($this->repository->existsByTitle($titre, $sessionId)) {
                return ['success' => false, 'message' => 'Ce thme existe deja.', 'alert_type' => 'warning'];
            }

            return [
                'success' => true,
                'message' => 'Le thme "' . $titre . '" a ete ajoute avec succes.',
                'alert_type' => 'success',
                'id_theme' => $this->repository->create($titre, $sessionId),
            ];
        } catch (PDOException $e) {
            error_log('Add theme error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erreur base de donnees pendant l ajout de ce thme.', 'alert_type' => 'danger'];
        }
    }

    /**
     * Met à jour un thème
     */
    public function updateTheme(int $id, string $titre, int $sessionId): array
    {
        CsrfProtection::requireToken();
        
        $activeSessionId = $this->sessionService->getActiveAdminSessionId();
        if ($sessionId !== $activeSessionId) {
            return ['success' => false, 'message' => 'Vous ne pouvez modifier un thème que pour la session active.', 'alert_type' => 'danger'];
        }

        $titre = $this->cleanText($titre, 100);

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Thme invalide.', 'alert_type' => 'danger'];
        }

        if ($titre === '') {
            return ['success' => false, 'message' => 'Le nom du thme est obligatoire.', 'alert_type' => 'warning'];
        }

        if ($sessionId <= 0) {
            return ['success' => false, 'message' => 'Veuillez choisir une session pour ce thme.', 'alert_type' => 'warning'];
        }

        try {
            if (!$this->sessionService->sessionExists($sessionId)) {
                return ['success' => false, 'message' => 'Session invalide.', 'alert_type' => 'danger'];
            }

            if (!$this->repository->belongsToSession($id, $activeSessionId)) {
                return ['success' => false, 'message' => 'Thme introuvable.', 'alert_type' => 'danger'];
            }

            if ($this->repository->existsByTitle($titre, $sessionId, $id)) {
                return ['success' => false, 'message' => 'Ce thme existe deja.', 'alert_type' => 'warning'];
            }

            return $this->repository->update($id, $titre, $sessionId)
                ? ['success' => true, 'message' => 'Thme mis a jour avec succes.', 'alert_type' => 'success']
                : ['success' => false, 'message' => 'Thme introuvable.', 'alert_type' => 'warning'];
        } catch (PDOException $e) {
            error_log('Update theme error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erreur base de donnees pendant la mise a jour du thme.', 'alert_type' => 'danger'];
        }
    }

    /**
     * Supprime un thème
     */
    public function deleteTheme(int $id): array
    {
        CsrfProtection::requireToken();
        
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Thme invalide.', 'alert_type' => 'danger'];
        }

        try {
            $activeSessionId = $this->sessionService->getActiveAdminSessionId();
            if (!$this->repository->delete($id, $activeSessionId)) {
                return ['success' => false, 'message' => 'Thme introuvable.', 'alert_type' => 'warning'];
            }

            return ['success' => true, 'message' => 'Thme supprime avec succes.', 'alert_type' => 'success'];
        } catch (PDOException $e) {
            error_log('Delete theme error: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erreur base de donnees pendant la suppression du thme.', 'alert_type' => 'danger'];
        }
    }

    /**
     * Récupère tous les thèmes
     */
    public function getAllThemes(): array
    {
        $activeSessionId = $this->sessionService->getActiveAdminSessionId();
        return $this->repository->findAllForSession($activeSessionId);
    }

    /**
     * Récupère le titre du thème actuel
     */
    public function getCurrentThemeTitle(): string
    {
        $activeSessionId = $this->sessionService->getActiveAdminSessionId();
        return $this->repository->findFirstTitleForSession($activeSessionId) ?? '';
    }

    /**
     * Nettoie un texte
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
}
