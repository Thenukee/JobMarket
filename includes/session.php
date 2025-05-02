<?php
/**
 * Session management for AmmooJobs platform
 * 
 * Handles session configuration, security, and related functionality
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    // Set secure session parameters before starting
    $session_name = 'AMMOOJOBS_SESSION';
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $httponly = true;
    
    // Set the session name
    session_name($session_name);
    
    // Set session cookie parameters
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => 'Lax'
    ]);
    
    // Start the session
    session_start();
}

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created_time'])) {
    $_SESSION['created_time'] = time();
} else if (time() - $_SESSION['created_time'] > 1800) { // 30 minutes
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created_time'] = time();
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("[" . date('Y-m-d H:i:s') . "] Session ID regenerated for user: " . 
                 (isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'));
    }
}

// Check for remember me cookie and try to login
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['remember_token'])) {
    if (isset($auth)) {
        $rememberLogin = $auth->attemptRememberMeLogin();
        
        if ($rememberLogin && defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("[" . date('Y-m-d H:i:s') . "] Auto-login successful for user: " . $_SESSION['name']);
        }
    }
}

/**
 * Set remember me cookie
 * 
 * @param int $userId User ID
 * @param int $days Number of days to remember
 * @return bool Success status
 */
function setRememberMeCookie($userId, $days = 30) {
    global $db;
    
    // Generate a random token
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    
    // Hash the validator for storage
    $hashedValidator = password_hash($validator, PASSWORD_DEFAULT);
    
    // Set expiry date
    $expires = date('Y-m-d H:i:s', time() + (86400 * $days));
    
    // Store token in database
    $result = $db->executeNonQuery(
        "INSERT INTO user_tokens (user_id, selector, token, expires, created_at) VALUES (?, ?, ?, ?, NOW())",
        [$userId, $selector, $hashedValidator, $expires]
    );
    
    if (!$result) {
        return false;
    }
    
    // Format token for cookie (user_id:validator)
    $cookieToken = "$userId:$validator";
    
    // Set the cookie
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    $httponly = true;
    
    return setcookie(
        'remember_token',
        $cookieToken,
        time() + (86400 * $days),
        '/',
        '',
        $secure,
        $httponly
    );
}

/**
 * Clear remember me cookie
 * 
 * @return bool Success status
 */
function clearRememberMeCookie() {
    // Delete cookie by setting expiration time in the past
    return setcookie('remember_token', '', time() - 3600, '/', '', false, true);
}

/**
 * Generate CSRF token if not exists
 * Store in session for form validation
 */
if (!isset($_SESSION['csrf_token'])) {
    generateCSRFToken();
}

/**
 * Set a flash message to be displayed on the next page load
 * 
 * @param string $type Message type (success, error, info, warning)
 * @param string $message Message content
 * @return void
 */
function setFlashMessage($type, $message) {
    $_SESSION[$type] = $message;
}

/**
 * Check if user session has expired
 * 
 * @return bool True if expired, False otherwise
 */
function isSessionExpired() {
    if (!isset($_SESSION['login_time'])) {
        return true;
    }
    
    // Check if session has exceeded the maximum lifetime
    return (time() - $_SESSION['login_time']) > SESSION_LIFETIME;
}

/**
 * Track user's last activity time
 */
function updateLastActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
        
        // Update in database every 5 minutes to avoid too many DB writes
        if (!isset($_SESSION['last_db_activity']) || (time() - $_SESSION['last_db_activity']) > 300) {
            global $db;
            $db->executeNonQuery(
                "UPDATE users SET last_active = NOW() WHERE user_id = ?",
                [$_SESSION['user_id']]
            );
            $_SESSION['last_db_activity'] = time();
        }
    }
}

// Update last activity time
updateLastActivity();

/**
 * Set user theme preference
 * 
 * @param string $theme Theme name (light, dark)
 * @param bool $remember Whether to remember in cookie
 * @return void
 */
function setUserTheme($theme, $remember = true) {
    $_SESSION['theme'] = $theme;
    
    if ($remember) {
        setcookie('theme', $theme, time() + (86400 * 365), '/', '', false, false);
    }
}

/**
 * Get current user theme
 * 
 * @return string Current theme (light, dark)
 */
function getUserTheme() {
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    } elseif (isset($_COOKIE['theme'])) {
        return $_COOKIE['theme'];
    }
    
    return DEFAULT_THEME;
}

/**
 * Log important session events
 * 
 * @param string $event Event description
 * @return void
 */
function logSessionEvent($event) {
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return;
    }
    
    $currentDateTime = date('Y-m-d H:i:s'); // 2025-05-01 17:23:04
    $currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // HasinduNimesh
    $sessionId = session_id();
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    
    error_log("[$currentDateTime] Session Event: $event | User: $currentUser | IP: $ipAddress | SID: $sessionId");
}

// Set timezone based on user preference if available
if (isset($_SESSION['timezone']) && in_array($_SESSION['timezone'], timezone_identifiers_list())) {
    date_default_timezone_set($_SESSION['timezone']);
} else {
    // Default to UTC
    date_default_timezone_set('UTC');
}

// Session security check - prevent session hijacking
if (isset($_SESSION['user_agent']) && isset($_SESSION['ip_address'])) {
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'];
    $current_ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if user agent has changed
    if ($_SESSION['user_agent'] !== md5($current_user_agent)) {
        // Potential session hijacking, log and destroy session
        logSessionEvent("Potential session hijacking - User agent changed");
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['error'] = "Your session has expired. Please log in again.";
        header("Location: login.php");
        exit;
    }
    
    // IP check with tolerance for dynamic IPs
    if (ENVIRONMENT === 'production' && !isIPAddressSimilar($_SESSION['ip_address'], $current_ip)) {
        // Log suspicious activity but don't immediately terminate (to account for mobile networks, etc)
        logSessionEvent("IP address changed significantly: {$_SESSION['ip_address']} -> {$current_ip}");
        
        // For highly sensitive operations, we might require re-authentication
        $_SESSION['require_verification'] = true;
    }
} else if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    // First login, set the security values
    $_SESSION['user_agent'] = md5($_SERVER['HTTP_USER_AGENT']);
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
}

/**
 * Compare two IP addresses to check if they are reasonably similar
 * (Useful for users on mobile networks where IP might change but within same range)
 * 
 * @param string $ip1 First IP address
 * @param string $ip2 Second IP address
 * @return bool True if IPs are similar, False otherwise
 */
function isIPAddressSimilar($ip1, $ip2) {
    // For IPv4
    if (filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
        filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        
        $parts1 = explode('.', $ip1);
        $parts2 = explode('.', $ip2);
        
        // Compare first 3 segments (network portion usually remains same)
        return ($parts1[0] == $parts2[0] && $parts1[1] == $parts2[1]);
    }
    
    // For IPv6 - more complex comparison would be needed
    // This is a simplified version
    if (filter_var($ip1, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
        filter_var($ip2, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        
        // Get first 32 bytes (network prefix typically)
        $prefix1 = substr($ip1, 0, 32);
        $prefix2 = substr($ip2, 0, 32);
        
        return $prefix1 === $prefix2;
    }
    
    // Different IP versions or invalid IPs
    return false;
}

// If in debug mode, include current datetime and user information in page
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $sessionDebugInfo = [
        'datetime' => date('Y-m-d H:i:s'), // 2025-05-01 17:23:04
        'user' => isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest', // HasinduNimesh
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ];
    
    // Store in a global variable for access in templates
    $GLOBALS['session_debug_info'] = $sessionDebugInfo;
}
?>