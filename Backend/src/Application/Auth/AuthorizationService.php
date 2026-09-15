<?php

declare(strict_types=1);

namespace Patro\Application\Auth;

use Patro\Http\SessionManager;

final class AuthorizationService
{
    private const ROLES = ['directeur', 'suppleant_1', 'suppleant_2'];

    public function __construct(private SessionManager $session)
    {
    }

    /** @return array<string,mixed> */
    public function currentAdmin(): array
    {
        $admin = $this->session->get('adpro', []);
        return is_array($admin) ? $admin : [];
    }

    public function currentRole(): string
    {
        $role = (string) ($this->currentAdmin()['role'] ?? 'directeur');
        return in_array($role, self::ROLES, true) ? $role : 'directeur';
    }

    /** @param list<string> $roles */
    public function hasRole(array $roles): bool
    {
        return in_array($this->currentRole(), $roles, true);
    }
}
