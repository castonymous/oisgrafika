<?php
require __DIR__ . '/../config.php';
require_login();

$invoices = load_json(INVOICES_FILE);
usort($invoices, fn($a, $b) => strcmp($b['created'] ?? '', $a['created'] ?? ''));

$filename = 'nota-ois-' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// UTF-8 BOM untuk Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['No. Nota','Tanggal','Pelanggan','HP','Metode','Status','Total','DP','Sisa','Items','Catatan','Dibuat']);

foreach ($invoices as $inv) {
    $itemsStr = implode(' | ', array_map(
        fn($it) => $it['qty'] . 'x ' . $it['item_name'] . ' @' . number_format($it['unit_price'],0,',','.'),
        $inv['items'] ?? []
    ));
    fputcsv($out, [
        $inv['invoice_number'] ?? '',
        $inv['invoice_date']   ?? '',
        $inv['customer_name']  ?? '',
        $inv['customer_phone'] ?? '',
        $inv['payment_method'] ?? '',
        $inv['payment_status'] ?? '',
        $inv['total']          ?? 0,
        $inv['down_payment']   ?? 0,
        $inv['remaining']      ?? 0,
        $itemsStr,
        $inv['notes']          ?? '',
        $inv['created']        ?? '',
    ]);
}
fclose($out);
exit;
