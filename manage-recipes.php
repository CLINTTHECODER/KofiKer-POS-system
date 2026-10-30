
<?php
require __DIR__ . '/config/db.php';

$message = '';
$error = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

try {
    // Load products and ingredients from the existing database.
    $products = $pdo->query(
        "SELECT id, name
         FROM public.products
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $ingredients = $pdo->query(
        "SELECT id, name, unit, stock_quantity
         FROM public.ingredients
         ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);

    $selectedProductId = filter_input(
        INPUT_GET,
        'product_id',
        FILTER_VALIDATE_INT
    );

    // Save the recipe for one product.
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && ($_POST['action'] ?? '') === 'save_recipe') {

        $selectedProductId = filter_var(
            $_POST['product_id'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if (!$selectedProductId) {
            throw new InvalidArgumentException(
                'Please select a valid product.'
            );
        }

        $checkProduct = $pdo->prepare(
            "SELECT id FROM public.products WHERE id = :id"
        );
        $checkProduct->execute(['id' => $selectedProductId]);

        if (!$checkProduct->fetch()) {
            throw new InvalidArgumentException(
                'The selected product does not exist.'
            );
        }

        $submittedIngredients = $_POST['ingredients'] ?? [];

        if (!is_array($submittedIngredients)
            || empty($submittedIngredients)) {
            throw new InvalidArgumentException(
                'Select at least one ingredient and enter its quantity.'
            );
        }

        $validIngredientIds = array_map(
            'intval',
            array_column($ingredients, 'id')
        );

        $recipe = [];

        foreach ($submittedIngredients as $ingredientId => $quantity) {
            $ingredientId = filter_var(
                $ingredientId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if (!$ingredientId
                || !in_array($ingredientId, $validIngredientIds, true)) {
                throw new InvalidArgumentException(
                    'An invalid ingredient was submitted.'
                );
            }

            if (!is_scalar($quantity) || !is_numeric($quantity)) {
                throw new InvalidArgumentException(
                    'Enter a valid quantity for every selected ingredient.'
                );
            }

            $quantity = (float)$quantity;

            if (!is_finite($quantity) || $quantity <= 0) {
                throw new InvalidArgumentException(
                    'Ingredient quantities must be greater than zero.'
                );
            }

            $recipe[$ingredientId] = $quantity;
        }

        $pdo->beginTransaction();

        // Replace only this product's recipe.
        $deleteRecipe = $pdo->prepare(
            "DELETE FROM public.product_ingredients
             WHERE product_id = :product_id"
        );
        $deleteRecipe->execute([
            'product_id' => $selectedProductId
        ]);

        $insertRecipe = $pdo->prepare(
            "INSERT INTO public.product_ingredients
                (product_id, ingredient_id, quantity_required)
             VALUES
                (:product_id, :ingredient_id, :quantity_required)"
        );

        foreach ($recipe as $ingredientId => $quantity) {
            $insertRecipe->execute([
                'product_id' => $selectedProductId,
                'ingredient_id' => $ingredientId,
                'quantity_required' => $quantity
            ]);
        }

        $pdo->commit();

        header(
            'Location: manage-recipes.php?product_id=' .
            $selectedProductId . '&saved=1'
        );
        exit;
    }

    if (isset($_GET['saved'])) {
        $message = 'Recipe saved successfully.';
    }

    // Load the saved recipe for the selected product.
    $savedRecipe = [];

    if ($selectedProductId) {
        $checkProduct = $pdo->prepare(
            "SELECT id FROM public.products WHERE id = :id"
        );
        $checkProduct->execute(['id' => $selectedProductId]);

        if (!$checkProduct->fetch()) {
            $error = 'Product not found.';
            $selectedProductId = null;
        } else {
            $recipeQuery = $pdo->prepare(
                "SELECT ingredient_id, quantity_required
                 FROM public.product_ingredients
                 WHERE product_id = :product_id"
            );

            $recipeQuery->execute([
                'product_id' => $selectedProductId
            ]);

            foreach ($recipeQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $savedRecipe[(int)$row['ingredient_id']] =
                    $row['quantity_required'];
            }
        }
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Manage recipes error: ' . $e->getMessage());

    if ($e instanceof InvalidArgumentException) {
        $error = $e->getMessage();
    } else {
        $error = 'Unable to load or save recipes. Check the PHP error log.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kofi Ker - Manage Recipes</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #222;
        }

        .container { max-width: 1000px; margin: auto; }

        .panel {
            background: white;
            padding: 22px;
            margin-bottom: 20px;
            border-radius: 12px;
        }

        label { display: block; margin-bottom: 6px; }

        select, input[type="number"] {
            width: 100%;
            padding: 10px;
            margin: 6px 0 14px;
        }

        button, .button {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            background: #5b3825;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        table { width: 100%; border-collapse: collapse; }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table-wrap { overflow-x: auto; }
        .message { color: #176b35; }
        .error { color: #b42318; }

        .ingredient-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ingredient-check input { width: auto; }

        .note { color: #666; font-size: 14px; }

        @media (max-width: 600px) {
            body { padding: 12px; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Kofi Ker POS</h1>
    <h2>Manage Product Recipes</h2>

    <p>
        Configure the ingredients and quantities used to make one drink.
        Saving a recipe updates only the selected product.
    </p>

    <p>
        <a class="button" href="index.php">Back to POS</a>
        <a class="button" href="manage-inventory.php">Manage Inventory</a>
    </p>

    <?php if ($message): ?>
        <p class="message"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <div class="panel">
        <form method="GET">
            <label for="product_id"><strong>Select a product</strong></label>

            <select id="product_id" name="product_id" required>
                <option value="">Choose a drink</option>

                <?php foreach ($products as $product): ?>
                    <option
                        value="<?= h($product['id']) ?>"
                        <?= (string)$selectedProductId ===
                            (string)$product['id'] ? 'selected' : '' ?>
                    >
                        <?= h($product['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Load Recipe</button>
        </form>
    </div>

    <?php if ($selectedProductId): ?>
        <div class="panel">
            <h3>Ingredients for this product</h3>

            <p class="note">
                Check each ingredient used in one serving and enter its
                quantity in the unit shown. These quantities will be used
                for stock deduction during checkout.
            </p>

            <?php if (empty($ingredients)): ?>
                <p>No ingredients found. Add ingredients in Manage Inventory first.</p>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="save_recipe">
                    <input type="hidden" name="product_id"
                           value="<?= h($selectedProductId) ?>">

                    <div class="table-wrap">
                        <table>
                            <thead>
                            <tr>
                                <th>Use</th>
                                <th>Ingredient</th>
                                <th>Current Stock</th>
                                <th>Quantity per Drink</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($ingredients as $ingredient): ?>
                                <?php
                                $ingredientId = (int)$ingredient['id'];
                                $isUsed = array_key_exists(
                                    $ingredientId,
                                    $savedRecipe
                                );
                                ?>
                                <tr>
                                    <td>
                                        <label class="ingredient-check">
                                            <input
                                                type="checkbox"
                                                name="ingredients[<?= $ingredientId ?>]"
                                                value="<?= $isUsed
                                                    ? h($savedRecipe[$ingredientId])
                                                    : '1' ?>"
                                                <?= $isUsed ? 'checked' : '' ?>
                                                onchange="
                                                    const q = this.closest('tr')
                                                        .querySelector('.quantity');
                                                    q.disabled = !this.checked;
                                                    if (this.checked && !q.value) {
                                                        q.value = '1';
                                                    }
                                                "
                                            >
                                            Use
                                        </label>
                                    </td>
                                    <td>
                                        <?= h($ingredient['name']) ?>
                                        <span class="note">
                                            (<?= h($ingredient['unit']) ?>)
                                        </span>
                                    </td>
                                    <td>
                                        <?= h($ingredient['stock_quantity']) ?>
                                        <?= h($ingredient['unit']) ?>
                                    </td>
                                    <td>
                                        <input
                                            class="quantity"
                                            type="number"
                                            name="ingredients[<?= $ingredientId ?>]"
                                            min="0.001"
                                            step="any"
                                            value="<?= $isUsed
                                                ? h($savedRecipe[$ingredientId])
                                                : '' ?>"
                                            <?= $isUsed ? '' : 'disabled' ?>
                                            placeholder="e.g. 10"
                                        >
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="note">
                        Important: verify all quantities before using this
                        recipe for real inventory deduction.
                    </p>

                    <button type="submit">Save Recipe</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
