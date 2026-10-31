<?php
require __DIR__ . '/config/db.php';

// Today's date range
$todayStart = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Today's sales and orders
$salesQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_sales
     FROM public.orders
     WHERE status = 'completed'
       AND created_at >= :today
       AND created_at < :tomorrow"
);

$salesQuery->execute([
    'today' => $todayStart,
    'tomorrow' => $tomorrow
]);

$todayStats = $salesQuery->fetch(PDO::FETCH_ASSOC);

// Low-stock ingredients
$lowStockQuery = $pdo->query(
    "SELECT name, stock_quantity, unit, reorder_level
     FROM public.ingredients
     WHERE stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC"
);

$lowStock = $lowStockQuery->fetchAll(PDO::FETCH_ASSOC);

// Best-selling products today
$bestSellerQuery = $pdo->prepare(
    "SELECT
        oi.product_name,
        SUM(oi.quantity) AS quantity_sold,
        SUM(oi.line_total) AS total_revenue
     FROM public.order_items oi
     INNER JOIN public.orders o ON o.id = oi.order_id
     WHERE o.status = 'completed'
       AND o.created_at >= :today
       AND o.created_at < :tomorrow
     GROUP BY oi.product_name
     ORDER BY quantity_sold DESC
     LIMIT 5"
);

$bestSellerQuery->execute([
    'today' => $todayStart,
    'tomorrow' => $tomorrow
]);

$bestSellers = $bestSellerQuery->fetchAll(PDO::FETCH_ASSOC);

// Recent transactions
$recentQuery = $pdo->query(
    "SELECT id, order_number, total_amount, status, created_at
     FROM public.orders
     ORDER BY created_at DESC
     LIMIT 5"
);

$recentOrders = $recentQuery->fetchAll(PDO::FETCH_ASSOC);

// Safe output helper
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kofi Ker | Dashboard</title>

    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>

<body>

<div class="dashboard-layout">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="brand">
            <div class="brand-icon">☕</div>

            <div>
                <div class="brand-name">Kofi Ker</div>
                <div class="brand-subtitle">MILK TEA POS</div>
            </div>
        </div>

        <div class="nav-label">MAIN MENU</div>

        <nav class="sidebar-nav">

            <a href="/" class="nav-link">
                <span class="nav-icon">🛒</span>
                <span>Point of Sale</span>
            </a>

            <a href="/dashboard" class="nav-link active">
                <span class="nav-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="/products" class="nav-link">
                <span class="nav-icon">🥤</span>
                <span>Products</span>
            </a>

            <a href="/inventory" class="nav-link">
                <span class="nav-icon">📦</span>
                <span>Inventory</span>
            </a>

            <a href="/recipes" class="nav-link">
                <span class="nav-icon">📋</span>
                <span>Recipes</span>
            </a>

            <a href="/orders" class="nav-link">
                <span class="nav-icon">🧾</span>
                <span>Order History</span>
            </a>

            <a href="/sales-reports" class="nav-link">
                <span class="nav-icon">📊</span>
                <span>Sales Reports</span>
            </a>

        </nav>

        <div class="sidebar-footer">
            Kofi Ker POS Management System<br>
            Development Version
        </div>

    </aside>

    <!-- MAIN DASHBOARD -->
    <main class="dashboard-main">

        <header class="dashboard-header">

            <div>
                <h1>Dashboard Overview</h1>
                <p>Welcome back! Here's what's happening at Kofi Ker today.</p>
            </div>

            <div class="header-date">
                📅 <?= e(date('F d, Y')) ?>
            </div>

        </header>

        <!-- SUMMARY CARDS -->
        <section class="stats-grid">

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Today's Sales</span>
                    <span class="stat-icon">💰</span>
                </div>

                <div class="stat-value">
                    ₱<?= number_format((float)$todayStats['total_sales'], 2) ?>
                </div>

                <div class="stat-note">Total completed sales today</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Today's Orders</span>
                    <span class="stat-icon">🛍️</span>
                </div>

                <div class="stat-value">
                    <?= number_format((int)$todayStats['total_orders']) ?>
                </div>

                <div class="stat-note">Completed transactions today</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Low-stock Ingredients</span>
                    <span class="stat-icon">⚠️</span>
                </div>

                <div class="stat-value">
                    <?= number_format(count($lowStock)) ?>
                </div>

                <div class="stat-note">Ingredients needing attention</div>
            </div>

            <div class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Best-selling Product</span>
                    <span class="stat-icon">⭐</span>
                </div>

                <div class="stat-value" style="font-size:19px;">
                    <?= !empty($bestSellers)
                        ? e($bestSellers[0]['product_name'])
                        : 'No sales yet' ?>
                </div>

                <div class="stat-note">Based on today's completed orders</div>
            </div>

        </section>

        <!-- LOW STOCK AND BEST SELLERS -->
        <section class="content-grid">

            <!-- LOW STOCK -->
            <div class="panel">

                <div class="panel-header">
                    <h2>Low-stock Ingredients</h2>
                    <a href="/inventory" class="panel-link">Manage Inventory →</a>
                </div>

                <div class="table-wrapper">
                    <table class="dashboard-table">

                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Current Stock</th>
                                <th>Reorder Level</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($lowStock as $ingredient): ?>
                                <?php
                                    $stock = (float)$ingredient['stock_quantity'];
                                    $reorder = (float)$ingredient['reorder_level'];

                                    $critical = $stock <= ($reorder * 0.5);
                                ?>

                                <tr>
                                    <td><?= e($ingredient['name']) ?></td>

                                    <td>
                                        <?= e($ingredient['stock_quantity']) ?>
                                        <?= e($ingredient['unit']) ?>
                                    </td>

                                    <td>
                                        <?= e($ingredient['reorder_level']) ?>
                                        <?= e($ingredient['unit']) ?>
                                    </td>

                                    <td>
                                        <span class="status <?= $critical ? 'status-critical' : 'status-low' ?>">
                                            <?= $critical ? 'Critical' : 'Low Stock' ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            <?php if (empty($lowStock)): ?>
                                <tr>
                                    <td colspan="4" class="empty-state">
                                        ✓ All ingredients have sufficient stock.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </div>

            <!-- BEST SELLERS -->
            <div class="panel">

                <div class="panel-header">
                    <h2>Best-selling Products</h2>
                    <span class="panel-link">Today</span>
                </div>

                <div class="table-wrapper">
                    <table class="dashboard-table">

                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Sold</th>
                                <th class="text-right">Revenue</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($bestSellers as $index => $product): ?>
                                <tr>
                                    <td>
                                        <span class="product-rank"><?= $index + 1 ?></span>
                                        <?= e($product['product_name']) ?>
                                    </td>

                                    <td class="text-center">
                                        <?= number_format((int)$product['quantity_sold']) ?>
                                    </td>

                                    <td class="text-right">
                                        ₱<?= number_format((float)$product['total_revenue'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (empty($bestSellers)): ?>
                                <tr>
                                    <td colspan="3" class="empty-state">
                                        No product sales today.
                                    </td>
                                </tr>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>

            </div>

        </section>

        <!-- RECENT TRANSACTIONS -->
        <section class="panel">

            <div class="panel-header">
                <h2>Recent Transactions</h2>
                <a href="/orders" class="panel-link">View All Orders →</a>
            </div>

            <div class="table-wrapper">
                <table class="dashboard-table">

                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Date</th>
                            <th class="text-right">Total</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($recentOrders as $order): ?>
                            <tr>

                                <td>
                                    <strong><?= e($order['order_number']) ?></strong>
                                </td>

                                <td>
                                    <?= e(date('M d, Y h:i A', strtotime($order['created_at']))) ?>
                                </td>

                                <td class="text-right">
                                    ₱<?= number_format((float)$order['total_amount'], 2) ?>
                                </td>

                                <td class="text-center">
                                    <span class="status <?= strtolower($order['status']) === 'completed' ? 'status-completed' : 'status-low' ?>">
                                        <?= e(ucfirst($order['status'])) ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <a
                                        href="/order-details?id=<?= urlencode((string)$order['id']) ?>"
                                        class="panel-link"
                                    >
                                        View Details
                                    </a>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($recentOrders)): ?>
                            <tr>
                                <td colspan="5" class="empty-state">
                                    No transactions found.
                                </td>
                            </tr>
                        <?php endif; ?>

                    </tbody>
                </table>
            </div>

        </section>

    </main>

</div>

</body>
</html>
