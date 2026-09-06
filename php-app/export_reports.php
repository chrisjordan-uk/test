<?php
require __DIR__ . '/config.php';
requirePermission('reports', 'view');

$type = $_GET['type'] === 'purchased' ? 'purchased' : 'sold';
$from = $_GET['from'] ?? date('Y-m-d');
$to = $_GET['to'] ?? date('Y-m-d');

if ($type === 'sold') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE status = "sold" AND sold_date BETWEEN ? AND ? ORDER BY sold_date DESC, id DESC');
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sold-' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sold on', 'Product #', 'Name', 'Brand', 'Bought (GBP)', 'Sold (GBP)', 'Margin %', 'Buyer']);
    foreach ($rows as $p) {
        $margin = marginPercent($p['bought_price'], $p['sold_price']);
        fputcsv($out, [
            $p['sold_date'], $p['product_number'], $p['name'], $p['brand'],
            $p['bought_price'], $p['sold_price'], $margin !== null ? round($margin, 1) : '', $p['buyer'],
        ]);
    }
} else {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE purchase_date BETWEEN ? AND ? ORDER BY purchase_date DESC, id DESC');
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="purchased-' . $from . '_to_' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Purchased on', 'Product #', 'Name', 'Brand', 'Bought (GBP)', 'Status']);
    foreach ($rows as $p) {
        fputcsv($out, [
            $p['purchase_date'], $p['product_number'], $p['name'], $p['brand'],
            $p['bought_price'], STATUSES[$p['status']]['label'] ?? $p['status'],
        ]);
    }
}
fclose($out);
