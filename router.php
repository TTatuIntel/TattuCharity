<?php
/**
 * Router for PHP's built-in dev server.
 *   php -S localhost:8000 router.php
 *
 * Apache uses the .htaccess files instead. This script enforces the same
 * deny rules so behavior matches in development.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Block private directories no matter what's inside them
foreach (['/backend/data/', '/backend/lib/'] as $blocked) {
    if (strpos($path, $blocked) === 0) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "403 Forbidden";
        return true;
    }
}
// Block error log + dotfiles
if (preg_match('#(^|/)(\.[^/]+|error\.log)$#', $path)) {
    http_response_code(403);
    echo "403 Forbidden";
    return true;
}

// Otherwise let the dev server handle it normally
return false;
