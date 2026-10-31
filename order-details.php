
<?php
require __DIR__ . '/config/db.php';

// Get the order ID from the URL
$orderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId <= 0) {
    http_response_code(400);
    exit('Invalid order ID.');
}

// Get the main order information
$orderQuery = $pdo->prepare(
    "SELECT
        id,
        order_number,
        status,
        subtotal,
        discount_amount,
        total_amount,
        notes,
        created_at
     FROM public.orders
     WHERE id = :id
     LIMIT 1"
);

$orderQuery->execute(['id' => $orderId]);
$order = $orderQuery->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// Get the products included in this order
$itemQuery = $pdo->prepare(
    "SELECT
        product_name,
        quantity,
        unit_price,
        line_total
     FROM public.order_items
     WHERE order_id = :order_id
     ORDER BY id ASC"
);

$itemQuery->execute(['order_id' => $orderId]);
$items = $itemQuery->fetchAll(PDO::FETCH_ASSOC);

// Get payment information
$paymentQuery = $pdo->prepare(
    "SELECT
        payment_method,
        amount,
        status,
        reference_number
     FROM public.payments
     WHERE order_id = :order_id"
);

$paymentQuery->execute(['order_id' => $orderId]);
$payments = $paymentQuery->fetchAll(PDO::FETCH_ASSOC);

// Escape output safely
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/includes/navbar.php';
?>

<main class="container" style="max-width: 1100px; margin: 30px auto; padding: 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <div>
            <h1>Order Details</h1>
            <p>Order Number: <strong><?= e($order['order_number']) ?></strong></p>
        </div>

        <a href="/orders">← Back to Order History</a>
    </div>

    <section style="padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">
        <h2>Order Information</h2>

        <p><strong>Date:</strong>
            <?= e(date('M d, Y h:i A', strtotime($order['created_at']))) ?>
        </p>

        <p><strong>Status:</strong> <?= e(ucfirst($order['status'])) ?></p>

        <p><strong>Notes:</strong> <?= e($order['notes'] ?: 'No notes') ?></p>
    </section>

    <section style="padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">
        <h2>Ordered Products</h2>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:1px solid #ddd;">Product</th>
                        <th style="text-align:center; padding:10px; border-bottom:1px solid #ddd;">Quantity</th>
                        <th style="text-align:right; padding:10px; border-bottom:1px solid #ddd;">Unit Price</th>
                        <th style="text-align:right; padding:10px; border-bottom:1px solid #ddd;">Total</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td style="padding:10px; border-bottom:1px solid #eee;">
                                <?= e($item['product_name']) ?>
                            </td>

                            <td style="text-align:center; padding:10px; border-bottom:1px solid #eee;">
                                <?= e($item['quantity']) ?>
                            </td>

                            <td style="text-align:right; padding:10px; border-bottom:1px solid #eee;">
                                ₱<?= number_format((float)$item['unit_price'], 2) ?>
                            </td>

                            <td style="text-align:right; padding:10px; border-bottom:1px solid #eee;">
                                ₱<?= number_format((float)$item['line_total'], 2) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" style="padding:15px; text-align:center;">
                                No products found for this order.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section style="padding:20px; border:1px solid #ddd; border-radius:8px; margin-bottom:20px;">
        <h2>Order Summary</h2>

        <p>Subtotal: <strong>₱<?= number_format((float)$order['subtotal'], 2) ?></strong></p>

        <p>Discount: <strong>₱<?= number_format((float)$order['discount_amount'], 2) ?></strong></p>

        <h3>Total Amount: ₱<?= number_format((float)$order['total_amount'], 2) ?></h3>
    </section>

    <section style="padding:20px; border:1px solid #ddd; border-radius:8px;">
        <h2>Payment Information</h2>

        <?php if (!empty($payments)): ?>
            <?php foreach ($payments as $payment): ?>
                <div style="padding:10px 0; border-bottom:1px solid #eee;">
                    <p><strong>Method:</strong> <?= e(strtoupper($payment['payment_method'])) ?></p>
                    <p><strong>Amount:</strong> ₱<?= number_format((float)$payment['amount'], 2) ?></p>
                    <p><strong>Status:</strong> <?= e(ucfirst($payment['status'])) ?></p>
                    <p><strong>Reference:</strong> <?= e($payment['reference_number'] ?: 'N/A') ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No payment information found.</p>
        <?php endif; ?>
    </section>

</main>
