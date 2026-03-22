<?php
/**
 * Security middleware for API protection
 * - CSRF token validation
 * - Origin/Referer checking
 * - Rate limiting
 */

session_start();

// Allowed origins (add your domain here)
define('ALLOWED_ORIGINS', [
    'https://masschk.com',
    'https://www.masschk.com',
]);

// Rate limit: max requests per window
define('RATE_LIMIT_MAX', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

/**
 * Generate a CSRF token and store in session
 */
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request header
 */
function validateCSRFToken(): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Validate that the request originates from an allowed domain
 */
function validateOrigin(): bool {
    // In development, allow localhost
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    // Check Origin header first
    if (!empty($origin)) {
        foreach (ALLOWED_ORIGINS as $allowed) {
            if (strcasecmp($origin, $allowed) === 0) {
                return true;
            }
        }
    }

    // Fallback to Referer header
    if (!empty($referer)) {
        $parsedReferer = parse_url($referer);
        $refererOrigin = ($parsedReferer['scheme'] ?? '') . '://' . ($parsedReferer['host'] ?? '');
        foreach (ALLOWED_ORIGINS as $allowed) {
            if (strcasecmp($refererOrigin, $allowed) === 0) {
                return true;
            }
        }
    }

    // Allow same-origin requests (no Origin/Referer = same server form post)
    if (empty($origin) && empty($referer)) {
        return true;
    }

    return false;
}

/**
 * Simple file-based rate limiting by IP
 */
function checkRateLimit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $hash = md5($ip);
    $rateDir = sys_get_temp_dir() . '/masschk_rate';

    if (!is_dir($rateDir)) {
        mkdir($rateDir, 0700, true);
    }

    $rateFile = $rateDir . '/' . $hash;
    $now = time();

    $data = ['count' => 0, 'window_start' => $now];

    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $stored = json_decode($content, true);
        if (is_array($stored)) {
            $data = $stored;
        }
    }

    // Reset window if expired
    if ($now - $data['window_start'] > RATE_LIMIT_WINDOW) {
        $data = ['count' => 0, 'window_start' => $now];
    }

    $data['count']++;
    file_put_contents($rateFile, json_encode($data), LOCK_EX);

    return $data['count'] <= RATE_LIMIT_MAX;
}

/**
 * Run all security checks for API requests
 * Returns error message string if failed, or null if passed
 */
function runSecurityChecks(): ?string {
    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return 'Method not allowed';
    }

    // CSRF validation
    if (!validateCSRFToken()) {
        http_response_code(403);
        return 'Invalid or missing security token. Please refresh the page.';
    }

    // Origin validation
    if (!validateOrigin()) {
        http_response_code(403);
        return 'Unauthorized origin';
    }

    // Rate limiting
    if (!checkRateLimit()) {
        http_response_code(429);
        return 'Too many requests. Please wait and try again.';
    }

    return null;
}
