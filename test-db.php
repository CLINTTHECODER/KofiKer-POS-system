
<?php

require __DIR__ . '/config/db.php';

try {
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM public.products'
    );

    $count = $stmt->fetchColumn();

    echo 'Connected to Supabase successfully!';
    echo '<br>';
    echo 'Products found: ' . (int) $count;

} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo 'The database query failed. Check your PHP error log.';
}
