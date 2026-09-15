<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Backend/bootstrap/app.php';

$container = $GLOBALS['patro_container'] ?? null;
if (!$container instanceof Patro\Shared\Container) {
    fwrite(STDERR, "Bootstrap container unavailable." . PHP_EOL);
    exit(1);
}

$requiredServices = [
    Patro\Http\SessionManager::class,
    Patro\Http\ErrorHandler::class,
    Patro\Infrastructure\Database\PdoConnectionFactory::class,
    Patro\Domain\Inscription\Repository\SessionRepository::class,
    Patro\Domain\Inscription\Repository\SectionRepository::class,
    Patro\Domain\Inscription\Repository\ThemeRepository::class,
    Patro\Domain\Configuration\Repository\ConfigurationRepository::class,
    Patro\Domain\Admin\Repository\AdminRepository::class,
    Patro\Domain\Animateur\Repository\AnimateurRepository::class,
    Patro\Domain\Statistics\Repository\StatisticsRepository::class,
    Patro\Domain\Inscription\Repository\InscriptionRepository::class,
    Patro\Infrastructure\Database\PdoTransactionManager::class,
    Patro\Application\Inscription\EnregistrerInscrit::class,
    Patro\Animateur\AnimateurService::class,
    Patro\Application\Animateur\InscrireAnimateurParCode::class,
    Patro\Application\Animateur\GenererCodesAnimateur::class,
    Patro\Application\Animateur\AuthentifierAnimateur::class,
    Patro\Application\Animateur\AnimateurAuthorizationService::class,
    Patro\Application\Auth\AdminAuthenticationService::class,
    Patro\Application\Auth\AuthorizationService::class,
    Patro\Domain\Jeu\Repository\JeuRepository::class,
    Patro\Inscription\SessionService::class,
    Patro\Inscription\SectionService::class,
    Patro\Inscription\ThemeService::class,
];

foreach ($requiredServices as $service) {
    if (!$container->has($service)) {
        fwrite(STDERR, 'Missing service: ' . $service . PHP_EOL);
        exit(1);
    }
}

echo 'Bootstrap services OK' . PHP_EOL;
