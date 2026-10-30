
<?php
require __DIR__ . '/config/db.php';

$message = '';
$error = '';
$editProduct = null;

 // Delete a product
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete') {

    $id = filter_var(
        $_POST['id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$id) {
        $error = 'Invalid product ID.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "DELETE FROM public.products WHERE id = :id"
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() > 0) {
                header('Location: manage-products.php?success=deleted');
                exit;
            }

            $error = 'Product not found.';
        } catch (PDOException $e) {
            error_log('Product deletion error: ' . $e->getMessage());
            $error = 'This product cannot be deleted because it may be linked to existing orders or recipes. Mark it unavailable instead.';
        }
    }
}

// Add a new product or update an existing one
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($_POST['action'] ?? '', ['add', 'update'], true)
) {
    $action = $_POST['action'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $available = isset($_POST['is_available']) ? true : false;

    if (
        $name === '' ||
        !is_numeric($price) ||
        (float)$price < 0 ||
        !ctype_digit((string)$categoryId) ||
        (int)$categoryId < 1
    ) {
        $error = 'Please enter a product name, valid price, and category.';
    } else {
        try {
            if ($action === 'add') {
                $sql = "INSERT INTO public.products
                        (name, description, category_id, price, is_available)
                        VALUES (:name, :description, :category_id, :price, :available)";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'category_id' => (int)$categoryId,
                    'price' => $price,
                    'available' => $available
                ]);

                header('Location: manage-products.php?success=added');
                exit;
            }

            if ($action === 'update') {
                $id = filter_var(
                    $_POST['id'] ?? '',
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if (!$id) {
                    throw new InvalidArgumentException('Invalid product ID.');
                }

                $sql = "UPDATE public.products
                        SET name = :name,
                            description = :description,
                            category_id = :category_id,
                            price = :price,
                            is_available = :available
                        WHERE id = :id";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'category_id' => (int)$categoryId,
                    'price' => $price,
                    'available' => $available,
                    'id' => $id
                ]);

                header('Location: manage-products.php?success=updated');
                exit;
            }
        } catch (Throwable $e) {
            error_log('Product management error: ' . $e->getMessage());
            $error = 'Could not save the product. Please check your entries and try again.';
        }
    }
}

// Load categories for the dropdown
$categories = $pdo->query(
    "SELECT id, name FROM public.categories ORDER BY name"
)->fetchAll();

// Load a product when Edit is clicked
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

if ($editId) {
    $stmt = $pdo->prepare(
        "SELECT id, name, description, category_id, price, is_available
         FROM public.products WHERE id = :id"
    );
    $stmt->execute(['id' => $editId]);
    $editProduct = $stmt->fetch();

    if (!$editProduct) {
        $error = 'Product not found.';
    }
}

// Load all products
$products = $pdo->query(
    "SELECT p.id, p.name, p.description, p.price, p.is_available,
            c.name AS category_name
     FROM public.products p
     LEFT JOIN public.categories c ON p.category_id = c.id
     ORDER BY p.id"
)->fetchAll();

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $message = 'Product added successfully!';
    } elseif ($_GET['success'] === 'updated') {
        $message = 'Product updated successfully!';
    } elseif ($_GET['success'] === 'deleted') {
        $message = 'Product deleted successfully!';
    }
}



$formProduct = $editProduct ?: [
    'id' => '',
    'name' => '',
    'description' => '',
    'category_id' => '',
    'price' => '',
    'is_available' => true
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kofi Ker - Product Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 25px;
            color: #292929;
        }

        .container {
            max-width: 1100px;
            margin: auto;
        }

        .panel {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border-radius: 12px;
        }

        input, textarea, select {
            box-sizing: border-box;
            width: 100%;
            padding: 10px;
            margin: 7px 0 15px;
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

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #ddd;
        }

        .success { color: #176b35; }
        .error { color: #b42318; }
        .available { color: #176b35; }
        .unavailable { color: #b42318; }
        .top-links { margin-bottom: 20px; }
        .top-links a { margin-right: 10px; }

        .table-wrap { overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">

    <h1>Kofi Ker POS</h1>
    <h2>Product Management</h2>

    <div class="top-links">
        <a class="button" href="index.php">Back to POS</a>
    </div>

    <?php if ($message): ?>
        <p class="success"><?= h($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <div class="panel">
        <h3><?= $editProduct ? 'Update Product' : 'Add New Product' ?></h3>

        <form method="POST">
            <input type="hidden" name="action"
                   value="<?= $editProduct ? 'update' : 'add' ?>">

            <?php if ($editProduct): ?>
                <input type="hidden" name="id"
                       value="<?= h($formProduct['id']) ?>">
            <?php endif; ?>

            <label for="name">Product Name</label>
            <input id="name" name="name" required maxlength="150"
                   value="<?= h($formProduct['name']) ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description"
                      rows="3"><?= h($formProduct['description']) ?></textarea>

            <label for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= h($category['id']) ?>"
                        <?= (string)$formProduct['category_id'] ===
                            (string)$category['id'] ? 'selected' : '' ?>>
                        <?= h($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="price">Selling Price (₱)</label>
            <input id="price" type="number" name="price"
                   min="0" step="0.01" required
                   value="<?= h($formProduct['price']) ?>">

            <label>
                <input type="checkbox" name="is_available"
                       style="width:auto"
                       <?= $formProduct['is_available'] ? 'checked' : '' ?>>
                Available for sale
            </label>

               <button type="submit">
                <?= $editProduct ? 'Save Changes' : 'Add Product' ?>
            </button>

            <?php if ($editProduct): ?>
                <a class="button" href="manage-products.php">Cancel</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="panel">
        <h3>Existing Products</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Availability</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= h($product['name']) ?></td>
                        <td><?= h($product['category_name'] ?? 'Uncategorized') ?></td>
                        <td>₱<?= number_format((float)$product['price'], 2) ?></td>
                        <td class="<?= $product['is_available']
                            ? 'available' : 'unavailable' ?>">
                            <?= $product['is_available']
                                ? 'Available' : 'Unavailable' ?>
                        </td>
                        <td>
                            <a class="button"
                               href="manage-products.php?edit=<?= h($product['id']) ?>">
                                Edit
                            </a>

                            <form method="POST"
                                  onsubmit="return confirm('Are you sure you want to delete this product?');"
                                  style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id"
                                       value="<?= h($product['id']) ?>">
                                <button type="submit" style="background:#b42318;">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$products): ?>
                    <tr>
                        <td colspan="5">No products found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>