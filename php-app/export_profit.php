<?php
require __DIR__ . '/config.php';
requirePermission('profit', 'view');

$rows = $pdo->query(
    'SELECT t.transaction_date, t.type, t.category, t.description, t.quantity, t.amount,
            (t.amount * t.quantity) AS total, p.product_number
     FROM transactions t LEFT JOIN products p ON p.id = t.product_id
     ORDER BY t.transaction_date DESC, t.id DESC'
)->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="profit-ledger-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Type', 'Category', 'Description', 'Product #', 'Quantity', 'Amount (GBP)', 'Total (GBP)']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['transaction_date'], $r['type'], $r['category'], $r['description'],
        $r['product_number'], $r['quantity'], $r['amount'], $r['total'],
    ]);
}
fclose($out);
