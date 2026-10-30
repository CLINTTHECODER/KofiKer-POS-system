<?php
$routes = require __DIR__ . '/includes/routes.php';

$path = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?: '/';

$path = rtrim($path, '/') ?: '/';

// Keep direct requests to existing files working,
// including checkout.php.
if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

if (!isset($routes[$path])) {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
    echo '<p><a href="/">Return to POS Home</a></p>';
    exit;
}

$file = __DIR__ . '/' . $routes[$path]['file'];

if (!is_file($file)) {
    http_response_code(500);
    echo 'The configured page does not exist: '
        . htmlspecialchars($routes[$path]['label']);
    exit;
}

require $file;
exit;
