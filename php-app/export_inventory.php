<?php
require __DIR__ . '/config.php';
requirePermission('inventory', 'view');

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$brandFilter = $_GET['brand'] ?? '';

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR product_number LIKE ? OR brand LIKE ?)';
    $like = "%$search%";
    array_push($params, $like, $like, $like);
}
if ($statusFilter !== '' && array_key_exists($statusFilter, STATUSES)) {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
}
if ($brandFilter !== '') {
    $where[] = 'brand = ?';
    $params[] = $brandFilter;
}

$sql = 'SELECT * FROM products' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY updated_at DESC LIMIT 5000';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="inventory-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, [
    'Product #', 'Name', 'Brand', 'Category', 'Size', 'Condition', 'Status',
    'Bought (GBP)', 'Sold (GBP)', 'Margin %', 'Purchase date', 'Sold date', 'Days', 'Buyer',
]);
foreach ($products as $p) {
    $margin = marginPercent($p['bought_price'], $p['sold_price']);
    $days = daysBetween($p['purchase_date'], $p['sold_date'] ?: null);
    fputcsv($out, [
        $p['product_number'], $p['name'], $p['brand'], $p['category'], $p['size'], $p['item_condition'],
        STATUSES[$p['status']]['label'] ?? $p['status'], $p['bought_price'], $p['sold_price'],
        $margin !== null ? round($margin, 1) : '', $p['purchase_date'], $p['sold_date'],
        $days, $p['buyer'],
    ]);
}
fclose($out);
