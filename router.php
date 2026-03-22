<?php
/**
 * Router for PHP built-in server
 * Handles routing for static files and PHP scripts
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// Serve existing files directly (CSS, JS, images, etc.)
if ($uri !== '/' && file_exists($filePath) && is_file($filePath)) {
    // Set proper content types for static files
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'xml'  => 'application/xml',
        'txt'  => 'text/plain',
    ];

    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        readfile($filePath);
        return true;
    }

    // Let PHP handle .php files
    if ($ext === 'php') {
        return false;
    }

    // Serve other files as-is
    return false;
}

// Default: serve index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
