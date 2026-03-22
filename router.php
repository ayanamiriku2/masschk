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
<title>404 - Access Denied</title>
<link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Share Tech Mono',monospace;background:#020a02;color:#80c080;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;overflow:hidden;position:relative}
body::before{content:'';position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:1;background:repeating-linear-gradient(0deg,transparent,transparent 2px,rgba(0,255,65,.015) 2px,rgba(0,255,65,.015) 4px)}
.error-box{max-width:480px;padding:2rem;position:relative;z-index:2;background:rgba(0,20,0,.45);backdrop-filter:blur(20px);border:1px solid rgba(0,255,65,.15);border-radius:16px;box-shadow:0 0 40px rgba(0,255,65,.1)}
.error-code{font-size:5rem;font-weight:400;color:#00ff41;line-height:1;text-shadow:0 0 20px rgba(0,255,65,.6),0 0 60px rgba(0,255,65,.2);animation:glitch 4s infinite}
.error-title{font-size:1.2rem;color:#00ff41;margin:1rem 0 .5rem;letter-spacing:.1em;text-transform:uppercase}
.error-msg{color:#3a6b3a;margin-bottom:2rem;line-height:1.6;font-size:.85rem}
.back-btn{display:inline-block;padding:.7rem 2rem;border-radius:10px;background:rgba(0,255,65,.1);color:#00ff41;text-decoration:none;font-weight:400;font-size:.85rem;letter-spacing:.1em;text-transform:uppercase;border:1px solid rgba(0,255,65,.3);transition:all .3s;text-shadow:0 0 8px rgba(0,255,65,.4)}
.back-btn:hover{background:rgba(0,255,65,.2);box-shadow:0 0 25px rgba(0,255,65,.3);transform:translateY(-2px)}
@keyframes glitch{0%,93%,95%,97%,100%{opacity:1}94%{opacity:.8;transform:translateX(2px)}96%{opacity:.9;transform:translateX(-1px)}}
</style>
</head>
<body>
<div class="error-box">
  <div class="error-code">404</div>
  <div class="error-title">// path not found_</div>
  <p class="error-msg">target node unreachable. the requested resource does not exist in this matrix.</p>
  <a href="/" class="back-btn">&lt; return to mainframe</a>
</div>
</body>
</html>
<?php
