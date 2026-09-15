<?php declare(strict_types=1);

require_once __DIR__ . '/../../Backend/utilitaire.php';

requireRole(['directeur'], '../Auth/login.php');
redirectTo('../home.php?page=admin_config&config_page=sauvegarde');
