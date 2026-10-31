
<?php
require __DIR__ . '/config/db.php';

// Default date range: first day of this month until today
$today = date('Y-m-d');
$firstDay = date('Y-m-01');

$startDate = $_GET['start_date'] ?? $firstDay;
$endDate = $_GET['end_date'] ?? $today;

// Validate date inputs
$validDate = function ($date) {
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed && $parsed->format('Y-m-d') === $date;
};

if (!$validDate($startDate) || !$validDate($endDate) || $startDate > $endDate) {
    $startDate = $firstDay;
    $endDate = $today;
}

// Use an exclusive end date so the entire selected end date is included
$endExclusive = (new DateTime($endDate))
    ->modify('+1 day')
    ->format('Y-m-d');

// 1. Sales summary
$summaryQuery = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(total_amount), 0) AS total_revenue,
        COALESCE(AVG(total_amount), 0) AS average_order
     FROM public.orders
     WHERE status = 'completed'
       AND created_at >= :start_date
       AND created_at < :end_date"
);

$summaryQuery->execute([
    'start_date' => $startDate,
    'end_date' => $endExclusive
]);

$summary = $summaryQuery->fetch(PDO::FETCH_ASSOC);

// 2. Daily sales
$dailyQuery = $pdo->prepare(
    "SELECT
        DATE(created_at) AS sales_date,
        COUNT(*) AS total_orders,
        SUM(total_amount) AS total_revenue
     FROM public.orders
     WHERE status = 'completed'
       AND created_at >= :start_date
       AND created_at < :end_date
     GROUP BY DATE(created_at)
     ORDER BY sales_date DESC"
);

$dailyQuery->execute([
    'start_date' => $startDate,
    'end_date' => $endExclusive
]);

$dailySales = $dailyQuery->fetchAll(PDO::FETCH_ASSOC);

// 3. Payment method breakdown
$paymentQuery = $pdo->prepare(
    "SELECT
        p.payment_method,
        COUNT(*) AS payment_count,
        COALESCE(SUM(p.amount), 0) AS payment_total
     FROM public.payments p
     INNER JOIN public.orders o ON o.id = p.order_id
     WHERE o.status = 'completed'
       AND p.status = 'completed'
       AND o.created_at >= :start_date
       AND o.created_at < :end_date
     GROUP BY p.payment_method
     ORDER BY payment_total DESC"
);

$paymentQuery->execute([
    'start_date' => $startDate,
    'end_date' => $endExclusive
]);

$paymentBreakdown = $paymentQuery->fetchAll(PDO::FETCH_ASSOC);

// Safe output helper
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/includes/navbar.php';
?>

<main style="max-width:1200px; margin:30px auto; padding:20px;">

    <h1>Sales Reports</h1>
    <p>View your sales performance and payment transactions.</p>

    <!-- Date Filter -->
    <form method="GET" action="/sales-reports"
          style="display:flex; flex-wrap:wrap; gap:15px; align-items:end; margin:25px 0;">

        <div>
            <label for="start_date">Start Date</label><br>
            <input type="date" id="start_date" name="start_date"
                   value="<?= e($startDate) ?>" required>
        </div>

        <div>
            <label for="end_date">End Date</label><br>
            <input type="date" id="end_date" name="end_date"
                   value="<?= e($endDate) ?>" required>
        </div>

        <button type="submit">Generate Report</button>

        <a href="/sales-reports">Reset</a>
    </form>

    <!-- Summary Cards -->
    <section style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-bottom:30px;">

        <div style="padding:20px; border:1px solid #ddd; border-radius:8px;">
            <p>Total Revenue</p>
            <h2>₱<?= number_format((float)$summary['total_revenue'], 2) ?></h2>
        </div>

        <div style="padding:20px; border:1px solid #ddd; border-radius:8px;">
            <p>Total Orders</p>
            <h2><?= number_format((int)$summary['total_orders']) ?></h2>
        </div>

        <div style="padding:20px; border:1px solid #ddd; border-radius:8px;">
            <p>Average Order Value</p>
            <h2>₱<?= number_format((float)$summary['average_order'], 2) ?></h2>
        </div>

    </section>

    <!-- Daily Sales -->
    <section style="margin-bottom:30px;">
        <h2>Daily Sales</h2>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Date</th>
                        <th style="text-align:center; padding:12px; border-bottom:1px solid #ddd;">Orders</th>
                        <th style="text-align:right; padding:12px; border-bottom:1px solid #ddd;">Revenue</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($dailySales as $day): ?>
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <?= e(date('M d, Y', strtotime($day['sales_date']))) ?>
                            </td>

                            <td style="text-align:center; padding:12px; border-bottom:1px solid #eee;">
                                <?= number_format((int)$day['total_orders']) ?>
                            </td>

                            <td style="text-align:right; padding:12px; border-bottom:1px solid #eee;">
                                ₱<?= number_format((float)$day['total_revenue'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($dailySales)): ?>
                        <tr>
                            <td colspan="3" style="padding:15px; text-align:center;">
                                No sales found for this date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Payment Breakdown -->
    <section>
        <h2>Payment Method Breakdown</h2>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:12px; border-bottom:1px solid #ddd;">Payment Method</th>
                        <th style="text-align:center; padding:12px; border-bottom:1px solid #ddd;">Transactions</th>
                        <th style="text-align:right; padding:12px; border-bottom:1px solid #ddd;">Total Amount</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($paymentBreakdown as $payment): ?>
                        <tr>
                            <td style="padding:12px; border-bottom:1px solid #eee;">
                                <?= e(strtoupper($payment['payment_method'])) ?>
                            </td>

                            <td style="text-align:center; padding:12px; border-bottom:1px solid #eee;">
                                <?= number_format((int)$payment['payment_count']) ?>
                            </td>

                            <td style="text-align:right; padding:12px; border-bottom:1px solid #eee;">
                                ₱<?= number_format((float)$payment['payment_total'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($paymentBreakdown)): ?>
                        <tr>
                            <td colspan="3" style="padding:15px; text-align:center;">
                                No completed payments found for this date range.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <p style="margin-top:25px; color:#777; font-size:13px;">
        Note: Reports include completed test orders and completed payments.
    </p>

</main>