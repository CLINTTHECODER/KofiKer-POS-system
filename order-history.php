
<?php
require __DIR__ . '/config/db.php';

// Search keyword
$search = trim($_GET['search'] ?? '');

try {
    $sql = "
        SELECT
            o.id,
            o.order_number,
            o.status,
            o.subtotal,
            o.discount_amount,
            o.total_amount,
            o.notes,
            o.created_at,

            COALESCE(items.item_count, 0) AS item_count,
            payments.payment_method,
            payments.payment_status

        FROM public.orders o

        LEFT JOIN (
            SELECT
                order_id,
                SUM(quantity) AS item_count
            FROM public.order_items
            GROUP BY order_id
        ) items ON items.order_id = o.id

        LEFT JOIN (
            SELECT
                order_id,
                STRING_AGG(DISTINCT payment_method, ', ') AS payment_method,
                STRING_AGG(DISTINCT status, ', ') AS payment_status
            FROM public.payments
            GROUP BY order_id
        ) payments ON payments.order_id = o.id
    ";

    if ($search !== '') {
        $sql .= "
            WHERE o.order_number ILIKE :search
        ";
    }

    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);

    if ($search !== '') {
        $stmt->execute([
            ':search' => '%' . $search . '%'
        ]);
    } else {
        $stmt->execute();
    }

    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Order history error: " . $e->getMessage());
    die("Unable to load order history. Please check the database connection.");
}

// Prevent HTML injection
function escape($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Kofi Ker POS</title>
</head>

<body>

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main>
    <h1>Order History</h1>
    <p>View and search previous customer transactions.</p>

    <form method="GET" action="/orders">
        <input
            type="text"
            name="search"
            placeholder="Search order number..."
            value="<?= escape($search) ?>"
        >

        <button type="submit">Search</button>

        <a href="/orders">Clear</a>
    </form>

    <br>

    <table border="1" cellpadding="10" cellspacing="0">
        <thead>
            <tr>
                <th>Order Number</th>
                <th>Date</th>
                <th>Items</th>
                <th>Payment Method</th>
                <th>Payment Status</th>
                <th>Order Status</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>

        <?php if (empty($orders)): ?>

            <tr>
                <td colspan="7">No orders found.</td>
                
            </tr>

        <?php else: ?>

            <?php foreach ($orders as $order): ?>

                <tr>
                    <td><?= escape($order['order_number']) ?></td>

                    <td>
                        <?= escape(date('M d, Y h:i A', strtotime($order['created_at']))) ?>
                    </td>

                    <td><?= escape($order['item_count']) ?></td>

                    <td><?= escape($order['payment_method'] ?? 'N/A') ?></td>

                    <td><?= escape($order['payment_status'] ?? 'N/A') ?></td>

                    <td><?= escape($order['status']) ?></td>

                    <td>
                        ₱<?= number_format((float)$order['total_amount'], 2) ?>
                    </td>
                    <td>
                        <a href="/order-details?id=<?= escape($order['id']) ?>">View Details</a>
                    </td>
                </tr>

            <?php endforeach; ?>

        <?php endif; ?>

        </tbody>
    </table>

    <p>Total records displayed: <?= count($orders) ?></p>

</main>

</body>
</html>
