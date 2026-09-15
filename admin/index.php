<?php
require_once __DIR__ . '/../Backend/utilitaire.php';

redirectTo(!empty($_SESSION['adpro']) ? defaultadminRoute() : 'Auth/login.php');
