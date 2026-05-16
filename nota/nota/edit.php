<?php
require __DIR__ . '/../config.php';
require_login();
$id = $_GET['id'] ?? '';
header('Location: create.php?id=' . urlencode($id));
exit;
