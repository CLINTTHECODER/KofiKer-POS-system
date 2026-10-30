
<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/config/db.php';

function respond(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, [
        'success' => false,
        'message' => 'Use POST to submit a test order.'
    ]);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(400, [
        'success' => false,
        'message' => 'Invalid request data.'
    ]);
}

$cart = $input['cart'] ?? null;
$method = $input['payment_method'] ?? '';
$cashReceived = $input['cash_received'] ?? null;

if (!is_array($cart) || count($cart) === 0 || count($cart) > 100) {
    respond(400, [
        'success' => false,
        'message' => 'Your cart is empty or invalid.'
    ]);
}

$allowedMethods = ['cash', 'gcash', 'card'];

if (!in_array($method, $allowedMethods, true)) {
    respond(400, [
        'success' => false,
        'message' => 'Choose cash, GCash, or card.'
    ]);
}

// Combine repeated product IDs and validate quantities.
$quantities = [];

foreach ($cart as $item) {
    if (!is_array($item)) {
        respond(400, [
            'success' => false,
            'message' => 'Invalid cart item.'
        ]);
    }

    $id = filter_var(
        $item['id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $quantity = filter_var(
        $item['quantity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 99]]
    );

    if ($id === false || $quantity === false) {
        respond(400, [
            'success' => false,
            'message' => 'Invalid product or quantity.'
        ]);
    }

    $key = (string) $id;
    $quantities[$key] = ($quantities[$key] ?? 0) + $quantity;

    if ($quantities[$key] > 99) {
        respond(400, [
            'success' => false,
            'message' => 'Maximum quantity per product is 99.'
        ]);
    }
}

try {
    $pdo->beginTransaction();

    $items = [];
    $subtotal = 0.00;

    // Always get prices from Supabase, not from the browser.
    $productQuery = $pdo->prepare(
        'SELECT id, name, price
         FROM public.products
         WHERE id = :id AND is_available = TRUE'
    );

    foreach ($quantities as $id => $quantity) {
        $productQuery->execute(['id' => $id]);
        $product = $productQuery->fetch();

        if (!$product) {
            throw new DomainException(
                'A product is unavailable or no longer exists. Please refresh the menu.'
            );
        }

        $price = (float) $product['price'];
        $lineTotal = round($price * $quantity, 2);
        $subtotal = round($subtotal + $lineTotal, 2);

        $items[] = [
            'product_id' => (int) $product['id'],
            'product_name' => $product['name'],
            'quantity' => $quantity,
            'unit_price' => $price,
            'line_total' => $lineTotal
        ];
    }

    $total = $subtotal;

    // Calculate the total ingredient quantities needed for this cart.
$ingredientRequirements = [];

$recipeQuery = $pdo->prepare(
    'SELECT ingredient_id, quantity_required
     FROM public.product_ingredients
     WHERE product_id = :product_id
     ORDER BY ingredient_id'
);

foreach ($quantities as $productId => $quantity) {
    $recipeQuery->execute([
        ':product_id' => $productId,
    ]);

    $recipeRows = $recipeQuery->fetchAll(PDO::FETCH_ASSOC);

    if (empty($recipeRows)) {
        throw new DomainException(
            "No ingredient recipe is configured for product ID {$productId}."
        );
    }

    foreach ($recipeRows as $recipe) {
        $ingredientId = (int) $recipe['ingredient_id'];
        $quantityRequired = (float) $recipe['quantity_required'];

        if (!is_finite($quantityRequired) || $quantityRequired <= 0) {
            throw new DomainException(
                "Invalid ingredient recipe for product ID {$productId}."
            );
        }

        if (!isset($ingredientRequirements[$ingredientId])) {
            $ingredientRequirements[$ingredientId] = 0.0;
        }

        $ingredientRequirements[$ingredientId] +=
            $quantityRequired * $quantity;
    }
}

// Lock ingredient records and check stock before deducting anything.
ksort($ingredientRequirements);

$ingredientQuery = $pdo->prepare(
    'SELECT id, name, unit, stock_quantity
     FROM public.ingredients
     WHERE id = :id
     FOR UPDATE'
);

$ingredientUpdates = [];

foreach ($ingredientRequirements as $ingredientId => $requiredQuantity) {
    $ingredientQuery->execute([
        ':id' => $ingredientId,
    ]);

    $ingredient = $ingredientQuery->fetch(PDO::FETCH_ASSOC);

    if (!$ingredient) {
        throw new DomainException(
            "Ingredient ID {$ingredientId} does not exist."
        );
    }

    $availableStock = (float) $ingredient['stock_quantity'];

    if ($availableStock + 0.000000001 < $requiredQuantity) {
        throw new DomainException(
            "Not enough {$ingredient['name']} in stock. " .
            "Available: {$availableStock} {$ingredient['unit']}; " .
            "Required: {$requiredQuantity} {$ingredient['unit']}."
        );
    }

    $ingredientUpdates[] = [
        'id' => $ingredientId,
        'new_stock' => $availableStock - $requiredQuantity,
    ];
}

// Deduct the ingredients inside the existing checkout transaction.
$updateIngredientQuery = $pdo->prepare(
    'UPDATE public.ingredients
     SET stock_quantity = :stock_quantity,
         updated_at = NOW()
     WHERE id = :id'
);

foreach ($ingredientUpdates as $update) {
    $updateIngredientQuery->execute([
        ':stock_quantity' => $update['new_stock'],
        ':id' => $update['id'],
    ]);
}

    if ($method === 'cash') {
        if (
            !is_numeric($cashReceived) ||
            !is_finite((float) $cashReceived) ||
            (float) $cashReceived < $total
        ) {
            throw new DomainException(
                'Cash received must be at least the order total.'
            );
        }

        $cashReceived = round((float) $cashReceived, 2);
    } else {
        $cashReceived = null;
    }

    // Every order made by this development checkout is marked as a test.
    $orderNumber = 'TEST-' . date('Ymd-His') . '-'
        . strtoupper(bin2hex(random_bytes(3)));

    $orderQuery = $pdo->prepare(
        "INSERT INTO public.orders
            (order_number, status, subtotal, discount_amount,
             total_amount, notes)
         VALUES
            (:order_number, 'completed', :subtotal, 0,
             :total, 'TEST ORDER - development checkout')
         RETURNING id"
    );

    $orderQuery->execute([
        'order_number' => $orderNumber,
        'subtotal' => $subtotal,
        'total' => $total
    ]);

    $orderId = $orderQuery->fetchColumn();

    $itemQuery = $pdo->prepare(
        'INSERT INTO public.order_items
            (order_id, product_id, product_name, quantity,
             unit_price, line_total)
         VALUES
            (:order_id, :product_id, :product_name, :quantity,
             :unit_price, :line_total)'
    );

    foreach ($items as $item) {
        $itemQuery->execute([
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'line_total' => $item['line_total']
        ]);
    }

    $paymentQuery = $pdo->prepare(
        "INSERT INTO public.payments
            (order_id, payment_method, amount, status, reference_number)
         VALUES
            (:order_id, :method, :amount, 'completed', :reference)"
    );

    $paymentQuery->execute([
        'order_id' => $orderId,
        'method' => $method,
        'amount' => $total,
        'reference' => $orderNumber
    ]);

    $pdo->commit();

    respond(200, [
        'success' => true,
        'message' => 'Test order saved successfully.',
        'order_number' => $orderNumber,
        'items' => $items,
        'subtotal' => $subtotal,
        'total' => $total,
        'payment_method' => $method,
        'cash_received' => $cashReceived,
        'change' => $method === 'cash'
            ? round($cashReceived - $total, 2)
            : null,
        'test_order' => true
    ]);

} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond(400, [
        'success' => false,
        'message' => $e->getMessage()
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Checkout failed: ' . $e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Unable to save the test order. Check the PHP server log.'
    ]);
}
