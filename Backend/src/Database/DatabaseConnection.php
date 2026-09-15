<?php

declare(strict_types=1);

namespace Patro\Database;

use Patro\Infrastructure\Database\PdoConnectionFactory;
use PDO;

/**
 * Gestion de la connexion à la base de données
 * Compatibilité historique autour d'une connexion PDO partagée.
 *
 * Les nouveaux composants doivent recevoir PDO par injection via le conteneur.
 */
class DatabaseConnection
{
    private static ?PDO $instance = null;

    /**
     * Établit et retourne une connexion PDO à la base de données
     * 
     * @return PDO Instance de connexion à la base de données
     * @throws \Throwable En cas d'erreur de connexion
     */
    public static function getConnection(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        self::$instance = (new PdoConnectionFactory())->create();
        return self::$instance;
    }

    /**
     * Réinitialise l'instance (utile pour les tests)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
