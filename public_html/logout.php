<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();
session_start();
setFlash('success', 'Sesión cerrada correctamente.');
redirect('/login.php');
