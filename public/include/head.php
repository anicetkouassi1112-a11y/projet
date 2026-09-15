<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php
        $resolvedTitle = $pageTitle ?? 'Fun&Loisirs';
        $resolvedDescription = $pageDescription ?? 'Centre de loisirs Fun&Loisirs - activites, inscriptions et informations pratiques.';
        $canonicalPage = requestTextParam('page', 60) ?: 'accueil';
        $canonicalUrl = app_url('public/home.php') . '?' . http_build_query(['page' => $canonicalPage]);
        $socialImage = app_url('Backend/Assets/img/android-chrome-512x512.png');
        ?>
        <meta name="description" content="<?= e($resolvedDescription) ?>">
        <meta name="theme-color" content="#6b3fbc">
        <meta property="og:title" content="<?= e($resolvedTitle) ?>">
        <meta property="og:description" content="<?= e($resolvedDescription) ?>">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="fr_FR">
        <meta property="og:url" content="<?= e($canonicalUrl) ?>">
        <meta property="og:image" content="<?= e($socialImage) ?>">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="<?= e($resolvedTitle) ?>">
        <meta name="twitter:description" content="<?= e($resolvedDescription) ?>">
        <meta name="twitter:image" content="<?= e($socialImage) ?>">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="<?= e($canonicalUrl) ?>">
        <title><?= e($resolvedTitle) ?></title>

        <link href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/app-public.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/topbar.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/app-login.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/inscriptin.css">
        <link rel="stylesheet" href="<?= e($assetBase ?? app_url('Backend/Assets')) ?>/css/bootstrap-icons.min.css">
        <?php renderFaviconTags($assetBase ?? app_url('Backend/Assets')); ?>
    </head>
    <body>
