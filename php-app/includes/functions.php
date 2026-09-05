<?php
/**
 * Shared helpers: permissions, auth, formatting, small UI bits.
 * Included once by config.php, which every page loads first.
 */

const FEATURES = ['dashboard', 'inventory', 'products', 'profit', 'users'];
const LEVELS = ['none', 'view', 'manage'];
const LEVEL_RANK = ['none' => 0, 'view' => 1, 'manage' => 2];

const STATUSES = [
    'available' => ['label' => 'Available', 'class' => 'bg-slate-100 text-slate-700'],
    'listed'    => ['label' => 'Listed',    'class' => 'bg-sky-100 text-sky-700'],
    'to_ship'   => ['label' => 'To Ship',   'class' => 'bg-amber-100 text-amber-700'],
    'shipped'   => ['label' => 'Shipped',   'class' => 'bg-indigo-100 text-indigo-700'],
    'sold'      => ['label' => 'Sold',      'class' => 'bg-emerald-100 text-emerald-700'],
    'returned'  => ['label' => 'Returned',  'class' => 'bg-orange-100 text-orange-700'],
    'rejected'  => ['label' => 'Rejected',  'class' => 'bg-red-100 text-red-700'],
];

const CONDITIONS = ['New with tags', 'New without tags', 'Very good', 'Good', 'Satisfactory'];

const DEFAULT_ROLES = [
    [
        'name' => 'Admin',
        'description' => 'Full access to every feature, including user & role management.',
        'permissions' => ['dashboard' => 'manage', 'inventory' => 'manage', 'products' => 'manage', 'profit' => 'manage', 'users' => 'manage'],
    ],
    [
        'name' => 'Manager',
        'description' => 'Runs day-to-day operations: products, inventory and profit tracking.',
        'permissions' => ['dashboard' => 'view', 'inventory' => 'manage', 'products' => 'manage', 'profit' => 'manage', 'users' => 'none'],
    ],
    [
        'name' => 'Staff',
        'description' => 'Handles listing and shipping: can view everything and update products.',
        'permissions' => ['dashboard' => 'view', 'inventory' => 'view', 'products' => 'manage', 'profit' => 'none', 'users' => 'none'],
    ],
];

// ---------------------------------------------------------------- general --

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function money($value): string
{
    return '$' . number_format((float) $value, 2);
}

function fmtDate(?string $value): string
{
    if (!$value) {
        return '—';
    }
    return date('M j, Y', strtotime($value));
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function normalizePermissions($raw): array
{
    $parsed = is_string($raw) ? json_decode($raw, true) : (array) $raw;
    $parsed = is_array($parsed) ? $parsed : [];
    $result = [];
    foreach (FEATURES as $feature) {
        $level = $parsed[$feature] ?? 'none';
        $result[$feature] = in_array($level, LEVELS, true) ? $level : 'none';
    }
    return $result;
}

// -------------------------------------------------------------------- auth --

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function can(string $feature, string $level = 'view'): bool
{
    $user = currentUser();
    if (!$user) {
        return false;
    }
    $current = $user['permissions'][$feature] ?? 'none';
    return LEVEL_RANK[$current] >= LEVEL_RANK[$level];
}

function canAny(array $features, string $level = 'view'): bool
{
    foreach ($features as $feature) {
        if (can($feature, $level)) {
            return true;
        }
    }
    return false;
}

function requirePermission(string $feature, string $level = 'view'): void
{
    requireLogin();
    if (!can($feature, $level)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}

// --------------------------------------------------------------- products --

/** Keep the profit ledger's "purchase" row in sync with a product. */
function syncPurchaseTransaction(PDO $pdo, array $product): void
{
    $stmt = $pdo->prepare('SELECT id FROM transactions WHERE product_id = ? AND type = "purchase" LIMIT 1');
    $stmt->execute([$product['id']]);
    $existingId = $stmt->fetchColumn();

    $description = 'Stock purchase: ' . $product['name'] .
        ($product['product_number'] ? ' (' . $product['product_number'] . ')' : '');
    $date = $product['purchase_date'] ?: date('Y-m-d');
    $amount = $product['bought_price'] ?: 0;

    if ($existingId) {
        $pdo->prepare('UPDATE transactions SET description = ?, amount = ?, transaction_date = ? WHERE id = ?')
            ->execute([$description, $amount, $date, $existingId]);
    } else {
        $pdo->prepare(
            'INSERT INTO transactions (type, product_id, description, amount, quantity, transaction_date, created_by)
             VALUES ("purchase", ?, ?, ?, 1, ?, ?)'
        )->execute([$product['id'], $description, $amount, $date, $product['created_by'] ?? null]);
    }
}

/** Keep the profit ledger's "sale" row in sync with a product's status/price. */
function syncSaleTransaction(PDO $pdo, array $product, ?int $actorId): void
{
    $stmt = $pdo->prepare('SELECT id FROM transactions WHERE product_id = ? AND type = "sale" LIMIT 1');
    $stmt->execute([$product['id']]);
    $existingId = $stmt->fetchColumn();

    $shouldHaveSale = $product['status'] === 'sold' && $product['sold_price'] !== null && $product['sold_price'] !== '';

    if (!$shouldHaveSale) {
        if ($existingId) {
            $pdo->prepare('DELETE FROM transactions WHERE id = ?')->execute([$existingId]);
        }
        return;
    }

    $description = 'Sale: ' . $product['name'] .
        ($product['product_number'] ? ' (' . $product['product_number'] . ')' : '') .
        ($product['buyer'] ? ' to ' . $product['buyer'] : '');
    $date = $product['sold_date'] ?: date('Y-m-d');

    if ($existingId) {
        $pdo->prepare('UPDATE transactions SET description = ?, amount = ?, transaction_date = ? WHERE id = ?')
            ->execute([$description, $product['sold_price'], $date, $existingId]);
    } else {
        $pdo->prepare(
            'INSERT INTO transactions (type, product_id, description, amount, quantity, transaction_date, created_by)
             VALUES ("sale", ?, ?, ?, 1, ?, ?)'
        )->execute([$product['id'], $description, $product['sold_price'], $date, $actorId]);
    }
}

function generateProductNumber(int $id): string
{
    return 'VR-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
}

// --------------------------------------------------------------------- UI --

function navItems(): array
{
    return [
        ['href' => 'index.php', 'label' => 'Home', 'feature' => 'dashboard'],
        ['href' => 'inventory.php', 'label' => 'Inventory', 'feature' => 'inventory'],
        ['href' => 'products.php', 'label' => 'Products', 'feature' => 'products'],
        ['href' => 'profit.php', 'label' => 'Profit', 'feature' => 'profit'],
        ['href' => 'users.php', 'label' => 'Users & Roles', 'feature' => 'users'],
    ];
}

function statusBadge(string $status): string
{
    $meta = STATUSES[$status] ?? ['label' => $status, 'class' => 'bg-slate-100 text-slate-700'];
    return '<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ' . $meta['class'] . '">' . e($meta['label']) . '</span>';
}

const BTN_PRIMARY = 'inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 transition-colors';
const BTN_SECONDARY = 'inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition-colors';
const BTN_DANGER = 'inline-flex items-center gap-2 rounded-lg bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100 transition-colors';
const INPUT = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100';
const LABEL = 'mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500';
const CARD = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
