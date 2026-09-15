<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

use Patro\Domain\Animateur\Repository\AnimateurRepository;
use Patro\Infrastructure\Database\TransactionManager;
use PDOException;
use Throwable;

final class InscrireAnimateurParCode
{
    public function __construct(
        private AnimateurRepository $animateurs,
        private TransactionManager $transactions
    ) {
    }

    /** @return array<string,mixed> */
    public function execute(InscrireAnimateurParCodeCommand $command): array
    {
        $data = $this->validate($command);
        if ($data['error'] !== '') {
            return ['success' => false, 'message' => $data['error'], 'alert_type' => $data['alert_type']];
        }

        try {
            $this->transactions->begin();
            $code = $this->animateurs->findCodeForUpdate($data['code']);
            if (!$code || (string) $code['statut'] !== 'disponible') {
                $this->transactions->rollback();
                return ['success' => false, 'message' => 'Code invalide ou deja utilise.', 'alert_type' => 'danger'];
            }
            if (!empty($code['date_expiration']) && strtotime((string) $code['date_expiration']) < time()) {
                $this->transactions->rollback();
                return ['success' => false, 'message' => 'Ce code a expire. Veuillez demander un nouveau code.', 'alert_type' => 'warning'];
            }
            if ((int) $code['id_session'] !== $command->sessionId) {
                $this->transactions->rollback();
                return ['success' => false, 'message' => 'Ce code ne correspond pas a la session en cours.', 'alert_type' => 'warning'];
            }

            $existing = $this->animateurs->findByPhoneForUpdate($data['tel']);
            $hash = password_hash($command->password, PASSWORD_DEFAULT);
            $warning = '';
            if ($existing) {
                $id = (int) $existing['id_animateur'];
                if ($this->lookup((string) $existing['nom_a']) !== $this->lookup($data['nom'])
                    || $this->lookup((string) $existing['prenom_a']) !== $this->lookup($data['prenom'])) {
                    $warning = ' Le nom ou le prenom differe de la fiche existante.';
                }
                $this->animateurs->updateFromRegistration($id, $data['nom'], $data['prenom'], $data['genre'], $hash);
            } else {
                $id = $this->animateurs->create($data['nom'], $data['prenom'], $data['genre'], $data['tel'], $hash);
            }
            $this->animateurs->attachToSession($id, (int) $code['id_session'], (int) $code['id_code']);
            $this->animateurs->consumeCode((int) $code['id_code'], $id);
            $this->transactions->commit();

            return [
                'success' => true,
                'message' => 'Votre enregistrement animateur est confirme.' . $warning,
                'alert_type' => $warning === '' ? 'success' : 'warning',
                'id_animateur' => $id,
            ];
        } catch (PDOException $exception) {
            $this->transactions->rollback();
            error_log('Register animateur error: ' . $exception->getMessage());
            return [
                'success' => false,
                'message' => (string) $exception->getCode() === '23000'
                    ? 'Cet animateur est deja enregistre pour cette session.'
                    : 'Erreur pendant l enregistrement. Veuillez reessayer.',
                'alert_type' => 'danger',
            ];
        } catch (Throwable $exception) {
            $this->transactions->rollback();
            error_log('Register animateur error: ' . $exception->getMessage());
            return ['success' => false, 'message' => 'Erreur pendant l enregistrement. Veuillez reessayer.', 'alert_type' => 'danger'];
        }
    }

    /** @return array{error:string,alert_type:string,code:string,nom:string,prenom:string,genre:string,tel:string} */
    private function validate(InscrireAnimateurParCodeCommand $command): array
    {
        $clean = static function (string $value, int $length): string {
            $value = trim(strip_tags($value));
            $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
            return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
        };
        $code = strtoupper($clean($command->code, 20));
        $nom = $clean($command->nom, 120);
        $prenom = $clean($command->prenom, 120);
        $genre = match ($this->lookup($command->genre)) {
            'masculin', 'm' => 'M',
            'feminin', 'f' => 'F',
            default => '',
        };
        $tel = preg_replace('/\D+/', '', $command->tel) ?? '';
        if ($code === '' || $nom === '' || $prenom === '' || $genre === '' || $command->password === '' || $command->passwordConfirm === '') {
            return ['error' => 'Veuillez remplir tous les champs obligatoires.', 'alert_type' => 'warning', 'code' => $code, 'nom' => $nom, 'prenom' => $prenom, 'genre' => $genre, 'tel' => $tel];
        }
        if (!preg_match('/^(01|05|07)[0-9]{8}$/', $tel)) {
            return ['error' => 'Numero de telephone ivoirien invalide.', 'alert_type' => 'warning', 'code' => $code, 'nom' => $nom, 'prenom' => $prenom, 'genre' => $genre, 'tel' => $tel];
        }
        if ($command->password !== $command->passwordConfirm) {
            return ['error' => 'Les mots de passe ne correspondent pas.', 'alert_type' => 'warning', 'code' => $code, 'nom' => $nom, 'prenom' => $prenom, 'genre' => $genre, 'tel' => $tel];
        }
        if (strlen($command->password) < 8 || strlen($command->password) > 256) {
            return ['error' => 'Le mot de passe doit contenir au moins 8 caracteres.', 'alert_type' => 'warning', 'code' => $code, 'nom' => $nom, 'prenom' => $prenom, 'genre' => $genre, 'tel' => $tel];
        }

        return ['error' => '', 'alert_type' => 'success', 'code' => $code, 'nom' => $nom, 'prenom' => $prenom, 'genre' => $genre, 'tel' => $tel];
    }

    private function lookup(string $value): string
    {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', strtr($value, ['ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e'])) ?? '';
    }
}
