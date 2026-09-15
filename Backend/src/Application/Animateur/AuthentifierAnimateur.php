<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

use Patro\Domain\Animateur\Repository\AnimateurRepository;

final class AuthentifierAnimateur
{
    public function __construct(private AnimateurRepository $animateurs)
    {
    }

    /** @return array<string,mixed> */
    public function execute(string $name, string $password, int $sessionId): array
    {
        $name = trim(strip_tags($name));
        if ($name === '' || $password === '') {
            return ['success' => false, 'message' => 'Veuillez renseigner le nom et le mot de passe.'];
        }
        $animateur = $this->animateurs->findForLogin($name, $sessionId);
        if (!$animateur || empty($animateur['password'])) {
            return ['success' => false, 'message' => 'Identifiants incorrects ou animateur non inscrit pour la session en cours.'];
        }

        $stored = (string) $animateur['password'];
        $valid = password_verify($password, $stored);
        $legacy = !$valid && hash_equals($stored, md5($password));
        if (!$valid && !$legacy) {
            return ['success' => false, 'message' => 'Identifiants incorrects.'];
        }
        if ($legacy) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->animateurs->updatePassword((int) $animateur['id_animateur'], $hash);
            $animateur['password'] = $hash;
        }
        if ((string) $animateur['statut'] === 'bloque') {
            return ['success' => false, 'message' => 'Votre compte animateur est bloque. Demandez un nouveau code a l administrateur pour la session en cours.'];
        }

        return ['success' => true, 'message' => 'Connexion reussie.', 'animateur' => $animateur];
    }
}
