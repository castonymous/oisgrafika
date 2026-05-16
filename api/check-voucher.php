<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/shipping-helpers.php';

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'msg' => 'Login dulu']);
    exit;
}

$code = strtoupper(trim($_POST['code'] ?? ''));
$subtotal = (float)($_POST['subtotal'] ?? 0);

$result = checkVoucher($code, $subtotal, $_SESSION['user_id'], $pdo);
echo json_encode($result);
