<?php declare(strict_types=1);

// =====================================================================
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Administration Patro - gestion des inscriptions et contenus.">
        <meta name="theme-color" content="#2454d6">
        <meta property="og:title" content="<?= e($pageTitle ?? 'Patro') ?>">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="fr_FR">
        <meta name="robots" content="noindex, nofollow">
        <?php if (function_exists('csrfToken')): ?>
            <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
            <meta name="update-inscrit-url" content="<?= e(app_url('admin/partial/update_inscrit.php')) ?>">
            <meta name="update-animateur-section-url" content="<?= e(app_url('admin/partial/update_animateur_section.php')) ?>">
        <?php endif; ?>
        <title><?= e($pageTitle ?? 'Patro') ?></title>

        <link href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/b.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/topbar-admin.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/bootstrap-icons.min.css">
        <?php renderFaviconTags($assetBase ?? app_url('Backend/Assets')); ?>
    </head>
    <body>
