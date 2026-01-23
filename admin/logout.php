<?php
/**
 * Déconnexion
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$db = new Database();
$auth = new Auth($db);
$auth->logout();

header('Location: login.php');
exit;
