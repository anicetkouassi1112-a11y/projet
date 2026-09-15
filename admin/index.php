<?php
require_once __DIR__ . '/partial/utilitaire.php';

redirectTo(!empty($_SESSION['adpro']) ? defaultadminRoute() : 'Auth/login.php');
