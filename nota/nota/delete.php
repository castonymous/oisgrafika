<?php
require __DIR__ . '/../config.php';
require_login();
$id = $_GET['id'] ?? '';
$invoices = load_json(INVOICES_FILE);
$invoices = array_values(array_filter($invoices, fn($i) => ($i['id'] ?? '') !== $id));
save_json(INVOICES_FILE, $invoices);
header('Location: ../index.php?deleted=1');
exit;
