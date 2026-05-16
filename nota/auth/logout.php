<?php
require __DIR__ . '/../config.php';
$_SESSION = [];
session_destroy();
header('Location: ' . app_url('auth/login.php'));
exit;
