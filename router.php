<?php
/**
 * Router for PHP built-in server
 * Handles routing for static files, directories, and PHP scripts
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

// Handle directory requests (e.g. /adminer → /adminer/index.php)
if ($uri !== '/' && is_dir($filePath)) {
    $indexFile = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexFile)) {
        require $indexFile;
        return true;
    }
}

// Known routes — serve index.php
$knownRoutes = ['/', '/api.php'];
if (in_array($uri, $knownRoutes) || $uri === '/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__ . '/index.php';
    return true;
}

// 404 — Not Found
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>404 - Page Not Found</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:#1a1b1e;color:#a0a3b1;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center}
.error-box{max-width:480px;padding:2rem}
.error-code{font-size:6rem;font-weight:800;color:#ef4444;line-height:1}
.error-title{font-size:1.5rem;color:#fff;margin:1rem 0 .5rem}
.error-msg{color:#6b7280;margin-bottom:2rem;line-height:1.6}
.back-btn{display:inline-block;padding:.7rem 2rem;border-radius:8px;background:#3b82f6;color:#fff;text-decoration:none;font-weight:600;font-size:.9rem;transition:background .2s}
.back-btn:hover{background:#2563eb}
</style>
</head>
<body>
<div class="error-box">
  <div class="error-code">404</div>
  <div class="error-title">Page Not Found</div>
  <p class="error-msg">The page you're looking for doesn't exist or has been moved.</p>
  <a href="/" class="back-btn">Back to Home</a>
</div>
</body>
</html>
<?php
