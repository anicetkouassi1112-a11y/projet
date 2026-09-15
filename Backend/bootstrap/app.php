<?php

declare(strict_types=1);

use Patro\Config\Environment;
use Patro\Http\ErrorHandler;
use Patro\Http\SessionManager;
use Patro\Inscription\SectionService;
use Patro\Inscription\SessionService;
use Patro\Inscription\ThemeService;
use Patro\Domain\Configuration\Repository\ConfigurationRepository;
use Patro\Domain\Admin\Repository\AdminRepository;
use Patro\Domain\Animateur\Repository\AnimateurRepository;
use Patro\Domain\Statistics\Repository\StatisticsRepository;
use Patro\Domain\Inscription\Repository\SectionRepository;
use Patro\Domain\Inscription\Repository\SessionRepository;
use Patro\Domain\Inscription\Repository\ThemeRepository;
use Patro\Domain\Inscription\Repository\InscriptionRepository;
use Patro\Infrastructure\Database\PdoConnectionFactory;
use Patro\Infrastructure\Database\PdoTransactionManager;
use Patro\Application\Inscription\EnregistrerInscrit;
use Patro\Application\Animateur\InscrireAnimateurParCode;
use Patro\Application\Animateur\GenererCodesAnimateur;
use Patro\Application\Animateur\AuthentifierAnimateur;
use Patro\Application\Animateur\AnimateurAuthorizationService;
use Patro\Application\Auth\AdminAuthenticationService;
use Patro\Application\Auth\AuthorizationService;
use Patro\Domain\Jeu\Repository\JeuRepository;
use Patro\Domain\Activite\Repository\ActiviteImageRepository;
use Patro\Domain\Paiement\Repository\CinetPayTransactionRepository;
use Patro\Paiement\CinetPayService;
use Patro\Shared\Container;

$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadPath)) {
    require_once $autoloadPath;
}

if (!class_exists(Environment::class)) {
    throw new RuntimeException('Autoload Composer indisponible pour le bootstrap Patro.');
}

Environment::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$root = dirname(__DIR__, 2);
$logDirectory = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

if (!isset($GLOBALS['patro_container']) || !$GLOBALS['patro_container'] instanceof Container) {
    $container = new Container();
    $container->singleton(PdoConnectionFactory::class, static fn (): PdoConnectionFactory => new PdoConnectionFactory());
    $container->singleton(PDO::class, static fn (Container $container): PDO => $container->get(PdoConnectionFactory::class)->create());
    $container->singleton(PdoTransactionManager::class, static fn (Container $container): PdoTransactionManager => new PdoTransactionManager($container->get(PDO::class)));
    $container->singleton(InscrireAnimateurParCode::class, static fn (Container $container): InscrireAnimateurParCode => new InscrireAnimateurParCode(
        $container->get(AnimateurRepository::class),
        $container->get(PdoTransactionManager::class)
    ));
    $container->singleton(GenererCodesAnimateur::class, static fn (Container $container): GenererCodesAnimateur => new GenererCodesAnimateur(
        $container->get(AnimateurRepository::class),
        $container->get(PdoTransactionManager::class)
    ));
    $container->singleton(AuthentifierAnimateur::class, static fn (Container $container): AuthentifierAnimateur => new AuthentifierAnimateur(
        $container->get(AnimateurRepository::class)
    ));
    $container->singleton(AnimateurAuthorizationService::class, static fn (Container $container): AnimateurAuthorizationService => new AnimateurAuthorizationService(
        $container->get(SessionManager::class)
    ));
    $container->singleton(AdminAuthenticationService::class, static fn (Container $container): AdminAuthenticationService => new AdminAuthenticationService(
        $container->get(AdminRepository::class),
        $container->get(SessionManager::class)
    ));
    $container->singleton(AuthorizationService::class, static fn (Container $container): AuthorizationService => new AuthorizationService(
        $container->get(SessionManager::class)
    ));
    $container->singleton(SessionRepository::class, static fn (Container $container): SessionRepository => new SessionRepository($container->get(PDO::class)));
    $container->singleton(SectionRepository::class, static fn (Container $container): SectionRepository => new SectionRepository($container->get(PDO::class)));
    $container->singleton(ThemeRepository::class, static fn (Container $container): ThemeRepository => new ThemeRepository($container->get(PDO::class)));
    $container->singleton(ConfigurationRepository::class, static fn (Container $container): ConfigurationRepository => new ConfigurationRepository($container->get(PDO::class)));
    $container->singleton(AdminRepository::class, static fn (Container $container): AdminRepository => new AdminRepository($container->get(PDO::class)));
    $container->singleton(AnimateurRepository::class, static fn (Container $container): AnimateurRepository => new AnimateurRepository($container->get(PDO::class)));
    $container->singleton(StatisticsRepository::class, static fn (Container $container): StatisticsRepository => new StatisticsRepository($container->get(PDO::class)));
    $container->singleton(InscriptionRepository::class, static fn (Container $container): InscriptionRepository => new InscriptionRepository($container->get(PDO::class)));
    $container->singleton(JeuRepository::class, static fn (Container $container): JeuRepository => new JeuRepository($container->get(PDO::class)));
    $container->singleton(ActiviteImageRepository::class, static fn (Container $container): ActiviteImageRepository => new ActiviteImageRepository($container->get(PDO::class)));
    $container->singleton(CinetPayTransactionRepository::class, static fn (Container $container): CinetPayTransactionRepository => new CinetPayTransactionRepository($container->get(PDO::class)));
    $container->singleton(CinetPayService::class, static fn (Container $container): CinetPayService => new CinetPayService(
        $container->get(InscriptionRepository::class),
        $container->get(CinetPayTransactionRepository::class)
    ));
    $container->singleton(EnregistrerInscrit::class, static fn (Container $container): EnregistrerInscrit => new EnregistrerInscrit(
        $container->get(InscriptionRepository::class),
        $container->get(SectionRepository::class),
        $container->get(SessionRepository::class),
        $container->get(PdoTransactionManager::class)
    ));
    $container->singleton(SessionService::class, static fn (Container $container): SessionService => new SessionService(
        $container->get(PDO::class),
        $container->get(SessionRepository::class),
        $container->get(ConfigurationRepository::class)
    ));
    $container->singleton(SectionService::class, static fn (Container $container): SectionService => new SectionService(
        $container->get(PDO::class),
        $container->get(SectionRepository::class)
    ));
    $container->singleton(ThemeService::class, static fn (Container $container): ThemeService => new ThemeService(
        $container->get(SessionService::class),
        $container->get(PDO::class),
        $container->get(ThemeRepository::class)
    ));
    $container->singleton(SessionManager::class, static fn (): SessionManager => new SessionManager());
    $container->singleton(ErrorHandler::class, static fn (): ErrorHandler => new ErrorHandler());
    $GLOBALS['patro_container'] = $container;
} else {
    $container = $GLOBALS['patro_container'];
}

/** @var SessionManager $sessionManager */
$sessionManager = $container->get(SessionManager::class);
$sessionManager->start();
$sessionManager->enforceAdminTimeout(Environment::int('ADMIN_SESSION_TIMEOUT_SECONDS', 3600));

/** @var ErrorHandler $errorHandler */
$errorHandler = $container->get(ErrorHandler::class);
$errorHandler->register($logDirectory, $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . '500.php');

if (PHP_SAPI !== 'cli' && Environment::bool('APP_FORCE_HTTPS', false)) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (!$isHttps) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
}

return $container;
