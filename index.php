
<?php
require __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->query(
        "SELECT id, name, description, price, is_available
         FROM public.products
         ORDER BY id ASC"
    );

    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    exit('Unable to load products. Check your server logs.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kofi Ker | Milk Tea POS</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f7f3ee;
            color: #34251f;
        }

        header {
            background: #442c24;
            color: white;
            padding: 22px 6%;
        }

        header h1 {
            margin: 0 0 5px;
        }

        header p {
            margin: 0;
            color: #eadbd1;
        }

        main {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 25px;
            align-items: start;
        }

        .menu-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .menu-heading h2 {
            margin: 0;
        }

        .count {
            background: #eadbd1;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .products {
            display: grid;
            grid-template-columns: repeat(
                auto-fit, minmax(190px, 1fr)
            );
            gap: 16px;
        }

        .product, .cart {
            background: white;
            border: 1px solid #eadfd7;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 4px 12px #34251f0a;
        }

        .product h3 {
            margin: 0 0 8px;
        }

        .description {
            min-height: 34px;
            color: #786a62;
            font-size: 13px;
        }

        .price {
            color: #80543c;
            font-size: 21px;
            font-weight: bold;
            margin: 16px 0;
        }

        button {
            border: none;
            border-radius: 8px;
            padding: 10px 13px;
            font-weight: bold;
            cursor: pointer;
        }

        .add-button {
            width: 100%;
            background: #80543c;
            color: white;
        }

        button:disabled {
            background: #c9c2bd;
            color: #625b56;
            cursor: not-allowed;
        }

        .cart {
            position: sticky;
            top: 20px;
        }

        .cart h2 {
            margin-top: 0;
        }

        .empty {
            color: #786a62;
            font-size: 14px;
            padding: 15px 0;
        }

        .cart-item {
            border-bottom: 1px solid #eee5df;
            padding: 13px 0;
        }

        .cart-item h4 {
            margin: 0 0 7px;
        }

        .item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .quantity {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .quantity button {
            padding: 5px 10px;
            background: #eadbd1;
        }

        .remove {
            background: transparent;
            color: #b42318;
            padding: 5px 0;
            font-size: 12px;
        }

        .totals {
            border-top: 2px solid #eadfd7;
            margin-top: 15px;
            padding-top: 15px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
        }

        .grand-total {
            font-size: 22px;
            font-weight: bold;
            color: #80543c;
        }

        .clear-button {
            width: 100%;
            margin-top: 12px;
            background: #eadbd1;
            color: #442c24;
        }

        .notice {
            font-size: 12px;
            color: #786a62;
            line-height: 1.5;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #786a62;
        }

        @media (max-width: 800px) {
            main {
                grid-template-columns: 1fr;
            }

            .cart {
                position: static;
            }
        }
    </style>
</head>

<body>
    <?php require __DIR__ . '/includes/navbar.php'; ?>
    <main>
        <section>
            <div class="menu-heading">
                <h2>Product Menu</h2>
                <span class="count">
                    <?= count($products) ?> products
                </span>
            </div>

            <div class="products">
                <?php foreach ($products as $product): ?>
                    <article class="product">
                        <h3>
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>

                        <p class="description">
                            <?= htmlspecialchars(
                                $product['description']
                                ?? 'Freshly prepared for you.'
                            ) ?>
                        </p>

                        <p class="price">
                            ₱<?= number_format(
                                (float) $product['price'], 2
                            ) ?>
                        </p>

                        <?php if ($product['is_available']): ?>
                            <button
                                class="add-button"
                                onclick='addToCart(
                                    <?= json_encode(
                                        (string) $product['id']
                                    ) ?>,
                                    <?= json_encode($product['name']) ?>,
                                    <?= json_encode(
                                        (float) $product['price']
                                    ) ?>
                                )'
                            >
                                + Add to Order
                            </button>
                        <?php else: ?>
                            <button class="add-button" disabled>
                                Unavailable
                            </button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="cart">
            <h2>Current Order</h2>
            <div id="cart-items">
                <p class="empty">No products added yet.</p>
            </div>

            <div class="totals">
                <div class="total-row">
                    <span>Items</span>
                    <span id="item-count">0</span>
                </div>

                <div class="total-row grand-total">
                    <span>Total</span>
                    <span id="grand-total">₱0.00</span>
                </div>

                <button
                    class="clear-button"
                    onclick="clearCart()"
                >
                    Clear Order
                </button>

                <div style="margin-top: 18px;">
    <label for="payment-method">Payment Method</label>
    <select
        id="payment-method"
        onchange="updateCashField()"
        style="width:100%; padding:10px; margin-top:8px;"
    >
        <option value="cash">Cash</option>
        <option value="gcash">GCash (Test)</option>
        <option value="card">Card (Test)</option>
    </select>
</div>

<div id="cash-field" style="margin-top:15px;">
    <label for="cash-received">Cash Received (₱)</label>
    <input
        id="cash-received"
        type="number"
        min="0"
        step="0.01"
        placeholder="Enter amount received"
        style="width:100%; padding:10px; margin-top:8px;"
    >
</div>

<button
    class="add-button"
    style="margin-top:18px;"
    onclick="checkoutOrder()"
>
    Complete Test Order
</button>

<p id="checkout-message" role="status" aria-live="polite"></p>

                <p class="notice">
                    This is a temporary cart. Orders are not saved
                    to the database yet.
                </p>
            </div>
        </aside>
    </main>

    <footer>
        Kofi Ker POS &copy; <?= date('Y') ?>
    </footer>

    <script>
        const cart = {};

        function peso(amount) {
            return '₱' + amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function addToCart(id, name, price) {
            if (cart[id]) {
                cart[id].quantity++;
            } else {
                cart[id] = {
                    id: id,
                    name: name,
                    price: price,
                    quantity: 1
                };
            }

            renderCart();
        }

        function changeQuantity(id, amount) {
            if (!cart[id]) return;

            cart[id].quantity += amount;

            if (cart[id].quantity <= 0) {
                delete cart[id];
            }

            renderCart();
        }

        function removeItem(id) {
            delete cart[id];
            renderCart();
        }

        function clearCart() {
            for (const id of Object.keys(cart)) {
                delete cart[id];
            }

            renderCart();
        }

        function renderCart() {
            const cartItems = document.getElementById('cart-items');
            const entries = Object.values(cart);

            let total = 0;
            let itemCount = 0;

            if (entries.length === 0) {
                cartItems.innerHTML =
                    '<p class="empty">No products added yet.</p>';
            } else {
                cartItems.innerHTML = '';

                entries.forEach(item => {
                    const lineTotal = item.price * item.quantity;

                    total += lineTotal;
                    itemCount += item.quantity;

                    const row = document.createElement('div');
                    row.className = 'cart-item';

                    const title = document.createElement('h4');
                    title.textContent = item.name;

                    const bottom = document.createElement('div');
                    bottom.className = 'item-bottom';

                    const quantity = document.createElement('div');
                    quantity.className = 'quantity';

                    const minus = document.createElement('button');
                    minus.textContent = '−';
                    minus.setAttribute('aria-label', 'Decrease quantity');
                    minus.addEventListener('click', () =>
                        changeQuantity(item.id, -1)
                    );

                    const number = document.createElement('span');
                    number.textContent = item.quantity;

                    const plus = document.createElement('button');
                    plus.textContent = '+';
                    plus.setAttribute('aria-label', 'Increase quantity');
                    plus.addEventListener('click', () =>
                        changeQuantity(item.id, 1)
                    );

                    quantity.append(minus, number, plus);

                    const linePrice = document.createElement('strong');
                    linePrice.textContent = peso(lineTotal);

                    bottom.append(quantity, linePrice);

                    const remove = document.createElement('button');
                    remove.className = 'remove';
                    remove.textContent = 'Remove';
                    remove.addEventListener('click', () =>
                        removeItem(item.id)
                    );

                    row.append(title, bottom, remove);
                    cartItems.appendChild(row);
                });
            }

            document.getElementById('item-count').textContent = itemCount;
            document.getElementById('grand-total').textContent = peso(total);
        }
        
function updateCashField() {
    const method = document.getElementById('payment-method').value;
    const cashField = document.getElementById('cash-field');

    cashField.style.display = method === 'cash' ? 'block' : 'none';
}

async function checkoutOrder() {
    const entries = Object.values(cart);
    const message = document.getElementById('checkout-message');
    const method = document.getElementById('payment-method').value;
    const cashInput = document.getElementById('cash-received');

    if (entries.length === 0) {
        alert('Please add a product to your order first.');
        return;
    }

    const total = entries.reduce(
        (sum, item) => sum + item.price * item.quantity,
        0
    );

    const cashReceived = Number(cashInput.value);

    if (method === 'cash') {
        if (
            cashInput.value.trim() === '' ||
            !Number.isFinite(cashReceived) ||
            cashReceived < total
        ) {
            alert('Please enter enough cash to cover the total.');
            return;
        }
    }

    const confirmed = confirm(
        'Save this as a TEST ORDER for ' + peso(total) + '?'
    );

    if (!confirmed) return;

    const button = document.querySelector(
        'button[onclick="checkoutOrder()"]'
    );

    button.disabled = true;
    button.textContent = 'Saving Test Order...';
    message.textContent = '';

    try {
        const response = await fetch('checkout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                cart: entries.map(item => ({
                    id: item.id,
                    quantity: item.quantity
                })),
                payment_method: method,
                cash_received: method === 'cash' ? cashReceived : null
            })
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Checkout failed.');
        }

        let receipt = 'TEST ORDER SAVED\n';
        receipt += 'Order Number: ' + result.order_number + '\n';
        receipt += 'Payment: ' + result.payment_method.toUpperCase() + '\n';
        receipt += 'Total: ' + peso(result.total);

        if (result.payment_method === 'cash') {
            receipt += '\nCash Received: ' + peso(result.cash_received);
            receipt += '\nChange: ' + peso(result.change);
        }

        message.textContent =
            'Test order saved successfully: ' + result.order_number;

        alert(receipt);

        clearCart();
        cashInput.value = '';

    } catch (error) {
        message.textContent = error.message;
        alert('Checkout error: ' + error.message);

    } finally {
        button.disabled = false;
        button.textContent = 'Complete Test Order';
    }
}

updateCashField();

    </script>
</body>
</html>
