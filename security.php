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
define('RATE_LIMIT_MAX', 600);
define('RATE_LIMIT_WINDOW', 120); // seconds

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
 * Session-based rate limiting (no file I/O overhead)
 */
function checkRateLimit(): bool {
    $now = time();

    if (!isset($_SESSION['rate_limit']) || !is_array($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = ['count' => 0, 'window_start' => $now];
    }

    $rl = &$_SESSION['rate_limit'];

    // Reset window if expired
    if ($now - $rl['window_start'] > RATE_LIMIT_WINDOW) {
        $rl = ['count' => 0, 'window_start' => $now];
    }

    $rl['count']++;

    return $rl['count'] <= RATE_LIMIT_MAX;
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
