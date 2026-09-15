<?php declare(strict_types=1);

$page = $currentPage ?? requestTextParam('page', 40);
$routes = [
    'statistique' => __DIR__ . '/statistique.php',
    'Tee_shirts' => __DIR__ . '/Tee_shirts.php',
    'attente' => __DIR__ . '/attente.php',
    'add_section' => __DIR__ . '/add_section.php',
    'animateurs' => __DIR__ . '/animateurs.php',
    'animateur_session' => __DIR__ . '/animateur_session.php',
    'liste_type_jeux' => __DIR__ . '/liste_type_jeux.php',
    'liste_jeu' => __DIR__ . '/liste_jeu.php',
    'liste_jeux_detail' => __DIR__ . '/liste_jeux_detail.php',
    'accueil_admin' => __DIR__ . '/accueil_admin.php',
    'activite_admin' => __DIR__ . '/activite_admin.php',
    'admin_config' => __DIR__ . '/../partial/admin_config.php',
    'modifier_mot_de_passe' => __DIR__ . '/modifier_mot_de_passe.php',
    'garcon' => __DIR__ . '/../garcon.php',
    'fille' => __DIR__ . '/../fille.php',
    'Animateur' => __DIR__ . '/../Animateur.php',
    'Animatrice' => __DIR__ . '/../Animatrice.php',
];

require $routes[$page] ?? $routes['statistique'];