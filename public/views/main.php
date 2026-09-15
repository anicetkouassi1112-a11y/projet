<?php

$page = $currentPage ?? requestTextParam('page', 40);

switch ($page) {
    case 'apropos':
        require __DIR__ . '/apropos.php';
        break;
    case 'activites':
        require __DIR__ . '/activites.php';
        break;
    case 'contact':
        require __DIR__ . '/contact.php';
        break;
    case 'politique_confidentialite':
        require __DIR__ . '/../politique_confidentialite.php';
        break;
    case 'mentions_legales':
        require __DIR__ . '/../mentions_legales.php';
        break;
    case 'accueil':
    default:
        require __DIR__ . '/accueil.php';
        break;
}
