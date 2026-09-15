<?php

declare(strict_types=1);

namespace Patro\Application\Configuration;

use DateTimeImmutable;
use Patro\Domain\Configuration\Repository\ConfigurationRepository;
use Throwable;

final class ConfigurationService
{
    public function __construct(private ConfigurationRepository $repository)
    {
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->repository->find($key, $default);
    }

    public function set(string $key, ?string $value): void
    {
        $this->repository->save($key, $value);
    }

    public function amount(string $key, int $default = 0): int
    {
        $value = filter_var($this->get($key, (string) $default), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return $value === false ? $default : (int) $value;
    }

    public function registrationAmount(): int
    {
        return $this->amount('inscription_montant', 500);
    }

    public function teeShirtPrice(): int
    {
        return $this->amount('tee_shirt_prix', 500);
    }

    public function registrationDateStart(): ?string
    {
        return $this->get('inscription_date_debut');
    }

    public function registrationDateEnd(): ?string
    {
        return $this->get('inscription_date_fin');
    }

    public function forceRegistrationClosed(): bool
    {
        return $this->get('inscription_force_ferme', 'off') === 'on';
    }

    public function registrationsOpen(): bool
    {
        if ($this->forceRegistrationClosed()) {
            return false;
        }

        $start = $this->registrationDateStart();
        $end = $this->registrationDateEnd();
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');

        return (!$start || $today >= $start) && (!$end || $today <= $end);
    }

    public function registrationClosedMessage(): string
    {
        $start = $this->registrationDateStart();
        $end = $this->registrationDateEnd();

        if ($this->forceRegistrationClosed()) {
            return 'Les inscriptions sont actuellement fermees. Veuillez contacter l\'administrateur.';
        }

        if ($start && $end) {
            return sprintf(
                'Les inscriptions sont ouvertes du %s au %s.',
                date('d/m/Y', strtotime($start)),
                date('d/m/Y', strtotime($end))
            );
        }

        return 'Les inscriptions sont fermees.';
    }
}
