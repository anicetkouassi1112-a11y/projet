<?php

declare(strict_types=1);

namespace Patro\Application\Auth;

use Patro\Domain\Admin\Repository\AdminRepository;
use Patro\Http\SessionManager;
use PDOException;

final class AdminAuthenticationService
{
    public function __construct(
        private AdminRepository $admins,
        private SessionManager $session
    ) {
    }

    /** @return array<string,mixed> */
    public function authenticate(string $username, string $password): array
    {
        $admin = $this->admins->findByUsername($username);
        if (!$admin || empty($admin['password'])) {
            return [];
        }
        $stored = (string) $admin['password'];
        $valid = password_verify($password, $stored);
        $legacy = !$valid && hash_equals($stored, md5($password));
        if (!$valid && !$legacy) {
            return [];
        }
        if ($legacy) {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $this->admins->updatePassword((int) $admin['id_admin'], $hash);
                $admin['password'] = $hash;
            } catch (PDOException $exception) {
                error_log('Password rehash error (admin): ' . $exception->getMessage());
            }
        }
        return $admin;
    }

    /** @param array<string,mixed> $admin */
    public function establish(array $admin): void
    {
        $this->session->regenerate();
        $this->session->set('adpro', $admin);
        $this->session->set('admin_last_activity', time());
    }

    public function logout(): void
    {
        $this->session->destroy();
    }
}
