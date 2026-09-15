<?php

declare(strict_types=1);

namespace Patro\Application\Animateur;

use Patro\Http\SessionManager;

final class AnimateurAuthorizationService
{
    public function __construct(private SessionManager $session)
    {
    }

    /** @return array<string,mixed> */
    public function current(): array
    {
        $value = $this->session->get('animateur', []);
        return is_array($value) ? $value : [];
    }

    public function isAuthenticated(): bool
    {
        return $this->current() !== [];
    }

    public function isBlocked(): bool
    {
        return (string) ($this->current()['statut'] ?? '') === 'bloque';
    }

    public function logout(): void
    {
        $this->session->remove('animateur');
    }
}
