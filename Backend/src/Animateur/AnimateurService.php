<?php

declare(strict_types=1);

namespace Patro\Animateur;

use Patro\Domain\Animateur\Repository\AnimateurRepository;
use Patro\Http\SessionManager;
use Patro\Security\CsrfProtection;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Service de gestion des animateurs
 */
class AnimateurService
{
    private const VALID_GENRES = ['M', 'F'];

    private AnimateurRepository $repository;
    private SessionManager $session;

    public function __construct(
        private PDO $connection,
        AnimateurRepository $repository,
        SessionManager $session
    )
    {
        $this->repository = $repository;
        $this->session = $session;
    }

    /**
     * Génère un code d'animateur
     */
    public function generateAnimateurCodeValue(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * Crée un code unique d'animateur
     */
    public function createUniqueAnimateurCode(PDO $connect, int $length = 10): string
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = $this->generateAnimateurCodeValue($length);
            $stmt = $connect->prepare('SELECT COUNT(*) FROM code_inscription_animateur WHERE code = :code');
            $stmt->execute([':code' => $code]);

            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
        }

        throw new RuntimeException('Generation de code impossible.');
    }

    /**
     * Crée des codes d'animateur
     */
    public function createAnimateurCodes(int $idSession, int $idAdmin, int $quantite, ?string $dateExpiration = null): array
    {
        CsrfProtection::requireToken();
        
        $activeSessionId = $this->getActiveAdminSessionId();
        if ($idSession !== $activeSessionId) {
            return ['success' => false, 'message' => 'Vous ne pouvez generer des codes que pour la session active.', 'codes' => []];
        }

        $quantite = max(1, min(100, $quantite));
        $connect = $this->connection;
        $codes = [];

        try {
            $connect->beginTransaction();
            $insert = $connect->prepare(
                'INSERT INTO code_inscription_animateur (code, id_session, id_admin, date_expiration)
                 VALUES (:code, :id_session, :id_admin, :date_expiration)'
            );

            for ($i = 0; $i < $quantite; $i++) {
                $code = $this->createUniqueAnimateurCode($connect, $this->getCodeLength());
                $insert->execute([
                    ':code' => $code,
                    ':id_session' => $idSession,
                    ':id_admin' => $idAdmin,
                    ':date_expiration' => $dateExpiration ?: null,
                ]);
                $codes[] = $code;
            }

            $connect->commit();
            return ['success' => true, 'message' => count($codes) . ' code(s) genere(s).', 'codes' => $codes];
        } catch (Throwable $e) {
            if ($connect->inTransaction()) {
                $connect->rollBack();
            }
            error_log('Create animateur codes error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur pendant la generation des codes.', 'codes' => []];
        }
    }

    /**
     * Enregistre un animateur avec un code
     */
    public function registerAnimateurWithCode(string $code, string $nom, string $prenom, string $genre, string $tel, string $password, string $passwordConfirm): array
    {
        CsrfProtection::requireToken();
        
        $code = strtoupper($this->cleanText($code, 20));
        $nom = $this->cleanText($nom, 120);
        $prenom = $this->cleanText($prenom, 120);
        $genre = $this->normalizeAnimateurGenre($genre);
        $tel = $this->normalizeIvorianPhone($tel);

        if ($code === '' || $nom === '' || $prenom === '' || $genre === ''|| $password === '' || $passwordConfirm === '') {
            return ['success' => false, 'message' => 'Veuillez remplir tous les champs obligatoires.', 'alert_type' => 'warning'];
        }

        if (!$this->isValidIvorianPhone($tel)) {
            return ['success' => false, 'message' => 'Numero de telephone ivoirien invalide.', 'alert_type' => 'warning'];
        }

        if ($password !== $passwordConfirm) {
            return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas.', 'alert_type' => 'warning'];
        }

        if (strlen($password) < 8 || strlen($password) > 256) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caracteres.', 'alert_type' => 'warning'];
        }

        $attempts = $this->session->get('animateur_code_attempts', ['count' => 0, 'locked_until' => 0]);
        $lockedUntil = (int) ($attempts['locked_until'] ?? 0);
        
        if ($lockedUntil > time()) {
            return ['success' => false, 'message' => 'Trop de tentatives. Veuillez patienter avant de reessayer.', 'alert_type' => 'danger'];
        }

        $connect = $this->connection;

        try {
            $connect->beginTransaction();

            $currentSessionId = $this->getCurrentAnimateurSessionId($connect);
            
            $stmt = $connect->prepare(
                'SELECT c.*
                 FROM code_inscription_animateur c
                 WHERE c.code = :code
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute([':code' => $code]);
            $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codeRow || (string) $codeRow['statut'] !== 'disponible') {
                $connect->rollBack();
                $this->session->set('animateur_code_attempts', [
                    'count' => ((int) ($attempts['count'] ?? 0)) + 1,
                    'locked_until' => ((int) ($attempts['count'] ?? 0)) + 1 >= 8 ? time() + 600 : 0,
                ]);
                return ['success' => false, 'message' => 'Code invalide ou deja utilise.', 'alert_type' => 'danger'];
            }

            if (!empty($codeRow['date_expiration']) && strtotime((string) $codeRow['date_expiration']) < time()) {
                $connect->rollBack();
                return ['success' => false, 'message' => 'Ce code a expire. Veuillez demander un nouveau code.', 'alert_type' => 'warning'];
            }

            if ((int) $codeRow['id_session'] !== $currentSessionId) {
                $connect->rollBack();
                return ['success' => false, 'message' => 'Ce code ne correspond pas a la session en cours.', 'alert_type' => 'warning'];
            }

            $find = $connect->prepare('SELECT id_animateur, nom_a, prenom_a, genre_a, tel, password, statut, created_at, updated_at FROM animateur WHERE tel = :tel LIMIT 1 FOR UPDATE');
            $find->execute([':tel' => $tel]);
            $animateur = $find->fetch(PDO::FETCH_ASSOC);
            $warning = '';
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            if ($animateur) {
                $idAnimateur = (int) $animateur['id_animateur'];
                if (
                    $this->identifierLookupKey((string) $animateur['nom_a']) !== $this->identifierLookupKey($nom)
                    || $this->identifierLookupKey((string) $animateur['prenom_a']) !== $this->identifierLookupKey($prenom)
                ) {
                    $warning = ' Le nom ou le prenom differe de la fiche existante.';
                }

                $update = $connect->prepare(
                    'UPDATE animateur
                     SET nom_a = :nom,
                         prenom_a = :prenom,
                         genre_a = :genre,
                         password = :password,
                         statut = "actif"
                     WHERE id_animateur = :id_animateur'
                );
                $update->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':genre' => $genre,
                    ':password' => $passwordHash,
                    ':id_animateur' => $idAnimateur,
                ]);
            } else {
                $insert = $connect->prepare(
                    'INSERT INTO animateur (nom_a, prenom_a, genre_a, tel, password, statut)
                     VALUES (:nom, :prenom, :genre, :tel, :password, "actif")'
                );
                $insert->execute([
                    ':nom' => $nom,
                    ':prenom' => $prenom,
                    ':genre' => $genre,
                    ':tel' => $tel,
                    ':password' => $passwordHash,
                ]);
                $idAnimateur = (int) $connect->lastInsertId();
            }

            $sessionInsert = $connect->prepare(
                'INSERT INTO animateur_session (id_animateur, id_session, id_code, id_section)
                 VALUES (:id_animateur, :id_session, :id_code, NULL)'
            );
            $sessionInsert->execute([
                ':id_animateur' => $idAnimateur,
                ':id_session' => (int) $codeRow['id_session'],
                ':id_code' => (int) $codeRow['id_code'],
            ]);

            $consume = $connect->prepare(
                'UPDATE code_inscription_animateur
                 SET statut = "utilise", id_animateur = :id_animateur, utilise_le = CURRENT_TIMESTAMP
                 WHERE id_code = :id_code'
            );
            $consume->execute([
                ':id_animateur' => $idAnimateur,
                ':id_code' => (int) $codeRow['id_code'],
            ]);

            $connect->commit();
            $this->session->remove('animateur_code_attempts');

            return [
                'success' => true,
                'message' => 'Votre enregistrement animateur est confirme.' . $warning,
                'alert_type' => $warning === '' ? 'success' : 'warning',
                'id_animateur' => $idAnimateur,
            ];
        } catch (PDOException $e) {
            if ($connect->inTransaction()) {
                $connect->rollBack();
            }
            error_log('Register animateur error: ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Cet animateur est deja enregistre pour cette session.', 'alert_type' => 'warning'];
            }

            return ['success' => false, 'message' => 'Erreur pendant l enregistrement. Veuillez reessayer.', 'alert_type' => 'danger'];
        } catch (Throwable $e) {
            if ($connect->inTransaction()) {
                $connect->rollBack();
            }
            error_log('Register animateur error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur pendant l enregistrement. Veuillez reessayer.', 'alert_type' => 'danger'];
        }
    }

    /**
     * Connecte un animateur
     */
    public function loginAnimateur(string $nom_a, string $password): array
    {
        $nom_a = $this->cleanText($nom_a, 120);
        if ($nom_a === '' || $password === '') {
            return ['success' => false, 'message' => 'Veuillez renseigner le nom et le mot de passe.'];
        }

        $connect = $this->connection;
        $currentSessionId = $this->getCurrentAnimateurSessionId($connect);
        $animateur = $this->repository->findForLogin($nom_a, $currentSessionId);

        if (!$animateur || empty($animateur['password'])) {
            return ['success' => false, 'message' => 'Identifiants incorrects ou animateur non inscrit pour la session en cours.'];
        }

        $storedPassword = (string) $animateur['password'];
        $passwordHashValid = password_verify($password, $storedPassword);
        $legacyPasswordValid = !$passwordHashValid && hash_equals($storedPassword, md5($password));

        if (!$passwordHashValid && !$legacyPasswordValid) {
            return ['success' => false, 'message' => 'Identifiants incorrects.'];
        }

        if ($legacyPasswordValid) {
            try {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $this->repository->updatePassword((int) $animateur['id_animateur'], $newHash);
                $animateur['password'] = $newHash;
            } catch (PDOException $e) {
                error_log('Password rehash error (animateur): ' . $e->getMessage());
            }
        }

        if ((string) $animateur['statut'] === 'bloque') {
            return ['success' => false, 'message' => 'Votre compte animateur est bloque. Demandez un nouveau code a l administrateur pour la session en cours.'];
        }

        return ['success' => true, 'message' => 'Connexion reussie.', 'animateur' => $animateur];
    }

    /**
     * Bloque les animateurs non inscrits
     */
    public function blockAnimateursNotRegistered(int $idSession): int
    {
        return $this->repository->blockNotRegistered($idSession);
    }

    /**
     * Retourne l'animateur connecté
     */
    public function currentAnimateur(): array
    {
        $animateur = $this->session->get('animateur', []);
        return is_array($animateur) ? $animateur : [];
    }

    /**
     * Vérifie qu'un animateur est connecté
     */
    public function requireAnimateur(string $loginUrl = 'auth/connexion.php'): void
    {
        if (!$this->currentAnimateur()) {
            $this->redirectTo($this->appUrl('public/' . ltrim($loginUrl, '/')));
        }

        if ((string) ($this->currentAnimateur()['statut'] ?? '') === 'bloque') {
            $this->session->remove('animateur');
            $this->session->flash('danger', 'Votre compte animateur est bloqué.');
            $this->redirectTo($this->appUrl('public/' . ltrim($loginUrl, '/')));
        }
    }

    /**
     * Déconnecte l'animateur de manière sécurisée
     */
    public function logoutAnimateur(): void
    {
        $this->session->remove('animateur');
    }

    // Méthodes utilitaires privées

    private function cleanText(string $value, int $maxLength = 255): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    private function normalizeIvorianPhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function isValidIvorianPhone(string $phone): bool
    {
        $phone = $this->normalizeIvorianPhone($phone);
        return (bool) preg_match('/^(01|05|07)[0-9]{8}$/', $phone);
    }

    private function normalizeAnimateurGenre(?string $genre): string
    {
        $key = $this->identifierLookupKey((string) $genre);
        return match ($key) {
            'masculin', 'm' => 'M',
            'feminin', 'f' => 'F',
            default => '',
        };
    }

    private function identifierLookupKey(string $value): string
    {
        $value = trim($value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

        $value = strtr($value, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        ]);

        $value = strtr($value, [
            'Ã ' => 'a', 'Ã¢' => 'a', 'Ã¤' => 'a',
            'Ã§' => 'c',
            'Ã©' => 'e', 'Ã¨' => 'e', 'Ãª' => 'e', 'Ã«' => 'e',
            'Ã®' => 'i', 'Ã¯' => 'i',
            'Ã´' => 'o', 'Ã¶' => 'o',
            'Ã¹' => 'u', 'Ã»' => 'u', 'Ã¼' => 'u',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $value) ?? '';
    }

    private function getCodeLength(): int
    {
        $length = getenv('ANIMATEUR_CODE_LENGTH');
        return $length ? (int) $length : 10;
    }

    private function getActiveAdminSessionId(): int
    {
        $annee = (int) date('Y');
        $type = $this->getCurrentSessionType();
        return $this->ensureSession($annee, $type);
    }

    private function getCurrentAnimateurSessionId(PDO $connect): int
    {
        $annee = (int) $this->session->get('annee_active', date('Y'));
        if ($annee < 2000 || $annee > 2100) {
            $annee = (int) date('Y');
        }

        return $this->ensureSession($annee, $this->getCurrentSessionType(), $connect);
    }

    private function getCurrentSessionType(): string
    {
        $configValue = $this->getConfig('inscription_type_session', 'scolaire');
        return $this->normalizeSessionType($configValue);
    }

    private function normalizeSessionType(?string $type, string $default = 'scolaire'): string
    {
        $type = strtolower(trim((string) $type));
        return in_array($type, ['scolaire', 'vacance'], true) ? $type : $default;
    }

    private function ensureSession(int $anneeVal, string $typeSession, ?PDO $connect = null): int
    {
        $connect = $connect ?: $this->connection;
        $typeSession = $this->normalizeSessionType($typeSession);
        $anneeId = $this->ensureAnnee($anneeVal, $connect);

        $stmt = $connect->prepare(
            'INSERT INTO session (annee_id, type_session) VALUES (:annee_id, :type_session)
             ON DUPLICATE KEY UPDATE type_session = VALUES(type_session)'
        );
        $stmt->execute([
            ':annee_id' => $anneeId,
            ':type_session' => $typeSession,
        ]);

        $select = $connect->prepare(
            'SELECT id_session FROM session WHERE annee_id = :annee_id AND type_session = :type_session LIMIT 1'
        );
        $select->execute([
            ':annee_id' => $anneeId,
            ':type_session' => $typeSession,
        ]);
        $id = $select->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('Session introuvable.');
        }

        return (int) $id;
    }

    private function ensureAnnee(int $anneeVal, PDO $connect): int
    {
        $stmt = $connect->prepare(
            'INSERT INTO annee (ans) VALUES (:annee) ON DUPLICATE KEY UPDATE ans = VALUES(ans)'
        );
        $stmt->execute([':annee' => $anneeVal]);

        $stmt = $connect->prepare('SELECT idannee FROM annee WHERE ans = :annee LIMIT 1');
        $stmt->execute([':annee' => $anneeVal]);
        $id = $stmt->fetchColumn();

        return (int) $id;
    }

    private function getConfig(string $key, ?string $default = null): ?string
    {
        try {
            $stmt = $this->connection->prepare(
                'SELECT config_value FROM configurations WHERE config_key = :key LIMIT 1'
            );
            $stmt->execute([':key' => $key]);
            $value = $stmt->fetchColumn();
            return $value === false ? $default : (string) $value;
        } catch (PDOException $e) {
            error_log('Get config error: ' . $e->getMessage());
            return $default;
        }
    }

    private function appUrl(string $path = ''): string
    {
        $configuredUrl = trim((string) (getenv('APP_URL') ?: ''));
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = trim(preg_replace('#/(Auth|file|partial|public)$#', '', $scriptDir), '/');

        return rtrim($scheme . '://' . $host . ($basePath ? '/' . $basePath : ''), '/') . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    private function redirectTo(string $url): void
    {
        header('Location: ' . $url);
        exit();
    }
}
