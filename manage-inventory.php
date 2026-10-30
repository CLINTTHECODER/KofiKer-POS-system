
<?php
require_once __DIR__ . '/config/db.php';

$message = '';
$error = '';
$editIngredient = null;

// Show success messages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $message = 'Ingredient added successfully!';
    } elseif ($_GET['success'] === 'updated') {
        $message = 'Ingredient updated successfully!';
    }
    elseif ($_GET['success'] === 'deleted') {
    $message = 'Ingredient deleted successfully!';
    }
}


 // Add, update, or delete an ingredient
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    // Handle deletion separately because it does not need form fields.
    if ($action === 'delete') {
        if (!$id || $id < 1) {
            $error = 'Invalid ingredient ID.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "DELETE FROM public.ingredients WHERE id = :id"
                );

                $stmt->execute(['id' => $id]);

                if ($stmt->rowCount() > 0) {
                    header('Location: manage-inventory.php?success=deleted');
                    exit;
                }

                $error = 'Ingredient not found.';
            } catch (PDOException $e) {
                error_log('Inventory delete error: ' . $e->getMessage());
                $error = 'Unable to delete this ingredient. It may be used in a product recipe.';
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? '');
        $stock = filter_var($_POST['stock_quantity'] ?? '', FILTER_VALIDATE_FLOAT);
        $reorder = filter_var($_POST['reorder_level'] ?? '', FILTER_VALIDATE_FLOAT);

        if (!in_array($action, ['add', 'update'], true)) {
            $error = 'Invalid action.';
        } elseif (
            $name === '' ||
            $unit === '' ||
            $stock === false ||
            $reorder === false ||
            $stock < 0 ||
            $reorder < 0
        ) {
            $error = 'Enter a name, unit, and valid non-negative quantities.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare(
                        "INSERT INTO public.ingredients
                         (name, unit, stock_quantity, reorder_level)
                         VALUES (:name, :unit, :stock, :reorder)"
                    );

                    $stmt->execute([
                        'name' => $name,
                        'unit' => $unit,
                        'stock' => $stock,
                        'reorder' => $reorder
                    ]);

                    header('Location: manage-inventory.php?success=added');
                    exit;
                }

                if (!$id || $id < 1) {
                    $error = 'Invalid ingredient ID.';
                } else {
                    $stmt = $pdo->prepare(
                        "UPDATE public.ingredients
                         SET name = :name,
                             unit = :unit,
                             stock_quantity = :stock,
                             reorder_level = :reorder,
                             updated_at = NOW()
                         WHERE id = :id"
                    );

                    $stmt->execute([
                        'name' => $name,
                        'unit' => $unit,
                        'stock' => $stock,
                        'reorder' => $reorder,
                        'id' => $id
                    ]);

                    header('Location: manage-inventory.php?success=updated');
                    exit;
                }
            } catch (PDOException $e) {
                error_log('Inventory save error: ' . $e->getMessage());
                $error = 'Unable to save the ingredient. Check the database error log.';
            }
        }
    }
}


// Load ingredient for editing
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);

    if ($editId && $editId > 0) {
        $stmt = $pdo->prepare(
            "SELECT * FROM public.ingredients WHERE id = :id"
        );
        $stmt->execute(['id' => $editId]);
        $editIngredient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Load all ingredients
try {
    $stmt = $pdo->query(
        "SELECT * FROM public.ingredients ORDER BY name ASC"
    );
    $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Inventory loading error: ' . $e->getMessage());
    $ingredients = [];
    $error = 'Unable to load inventory.';
}

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kofi Ker - Ingredient Inventory</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #222;
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }

        h1, h2 {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button, .button {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            background: #245b45;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            background: #e5f5e9;
            margin-bottom: 16px;
            border-radius: 6px;
        }

        .error {
            padding: 12px;
            background: #fde8e7;
            margin-bottom: 16px;
            border-radius: 6px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f5f6f8;
        }

        .low-stock {
            color: #b42318;
            font-weight: bold;
        }

        .normal-stock {
            color: #18794e;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="container">

    <h1>Kofi Ker Ingredient Inventory</h1>
    <p>Monitor ingredients and keep track of available stock.</p>

    <?php if ($message): ?>
        <div class="message"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="panel">
        <h2><?= $editIngredient ? 'Edit Ingredient' : 'Add Ingredient' ?></h2>

        <form method="POST">
            <input
                type="hidden"
                name="action"
                value="<?= $editIngredient ? 'update' : 'add' ?>"
            >

            <?php if ($editIngredient): ?>
                <input
                    type="hidden"
                    name="id"
                    value="<?= h($editIngredient['id']) ?>"
                >
            <?php endif; ?>

            <div class="form-grid">
                <div>
                    <label for="name">Ingredient Name</label>
                    <input
                        id="name"
                        name="name"
                        required
                        value="<?= h($editIngredient['name'] ?? '') ?>"
                        placeholder="e.g. Fresh Milk"
                    >
                </div>

                <div>
                    <label for="unit">Unit</label>
<select name="unit" id="unit" required>
    <option value="g">Grams (g)</option>
    <option value="ml">Milliliters (ml)</option>
    <option value="piece">Pieces (piece)</option>
</select>
                </div>

                <div>
                    <label for="stock_quantity">Current Stock</label>
                    <input
                        type="number"
                        id="stock_quantity"
                        name="stock_quantity"
                        min="0"
                        step="any"
                        required
                        value="<?= h($editIngredient['stock_quantity'] ?? '0') ?>"
                    >
                </div>

                <div>
                    <label for="reorder_level">Low-Stock Threshold</label>
                    <input
                        type="number"
                        id="reorder_level"
                        name="reorder_level"
                        min="0"
                        step="any"
                        required
                        value="<?= h($editIngredient['reorder_level'] ?? '5') ?>"
                    >
                </div>
            </div>

            <p>
                <button type="submit">
                    <?= $editIngredient ? 'Save Changes' : 'Add Ingredient' ?>
                </button>

                <?php if ($editIngredient): ?>
                    <a class="button" href="manage-inventory.php">Cancel</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <div class="panel">
        <h2>Current Inventory</h2>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Stock</th>
                    <th>Unit</th>
                    <th>Reorder Level</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>

                <?php if (empty($ingredients)): ?>
                    <tr>
                        <td colspan="6">No ingredients found. Add your first ingredient above.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($ingredients as $ingredient): ?>
                        <?php
                        $isLow = (float) $ingredient['stock_quantity']
                            <= (float) $ingredient['reorder_level'];
                        ?>
                        <tr>
                            <td><?= h($ingredient['name']) ?></td>
                            <td><?= h($ingredient['stock_quantity']) ?></td>
                            <td><?= h($ingredient['unit']) ?></td>
                            <td><?= h($ingredient['reorder_level']) ?></td>
                            <td>
                                <?php if ($isLow): ?>
                                    <span class="low-stock">Low Stock</span>
                                <?php else: ?>
                                    <span class="normal-stock">In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                
<a
    class="button"
    href="manage-inventory.php?edit=<?= h($ingredient['id']) ?>"
>Edit</a>

<form
    method="POST"
    style="display:inline;"
    onsubmit="return confirm('Are you sure you want to delete this ingredient? This action cannot be undone.');"
>
    <input type="hidden" name="action" value="delete">
    <input
        type="hidden"
        name="id"
        value="<?= h($ingredient['id']) ?>"
    >
    <button
        type="submit"
        style="background:#b42318;"
    >Delete</button>
</form>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                </tbody>
            </table>
        </div>
    </div>

    <p><a class="button" href="index.php">Back to POS</a></p>
</div>
</body>
</html>
