<?php
/**
 * Main configuration file for AmmooJobs
 * 
 * Contains all configuration settings, constants, and global settings
 * Last updated: 2025-05-01
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site information
define('SITE_NAME', 'AmmooJobs');
define('SITE_TAGLINE', 'Find Your Dream Job');
define('SITE_URL', 'https://www.ammoojobs.com');
define('ADMIN_EMAIL', 'admin@ammoojobs.com');
define('SUPPORT_EMAIL', 'support@ammoojobs.com');
define('NOREPLY_EMAIL', 'noreply@ammoojobs.com');

// Environment settings (development, testing, production)
define('ENVIRONMENT', 'production');

// Database configuration
// Database configuration
define('DB_HOST', 'fdb1030.awardspace.net');     // Your MySQL hostname
define('DB_NAME', '4627962_ammoo');      // Your database name
define('DB_USER', '4627962_ammoo');                // Your MySQL username
define('DB_PASS', 'StrongPassword123');                // Your MySQL password
define('DB_PORT', 3306);                          // Your MySQL port (optional)          // Your database password/ Note: In a real app, use environment variables

// Security settings
define('HASH_SALT', 'am#m00J0bs!S@ltV@lu3'); // For additional hashing if needed
define('CSRF_EXPIRY', 3600); // Token expiry in seconds (1 hour)
define('SESSION_LIFETIME', 7200); // Session expiry in seconds (2 hours)
define('PASSWORD_RESET_EXPIRY', 3600); // Password reset token expiry (1 hour)
define('LOGIN_ATTEMPTS_LIMIT', 5); // Max failed login attempts before temporary lockout
define('LOCKOUT_TIME', 900); // Account lockout time in seconds (15 minutes)

// File upload settings
define('UPLOAD_PATH', dirname(dirname(__FILE__)) . '/uploads/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');
define('RESUME_PATH', UPLOAD_PATH . 'resumes/');
define('COMPANY_LOGO_PATH', UPLOAD_PATH . 'logos/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_RESUME_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Pagination settings
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGINATION_LINKS', 5);

// Job application settings
define('MAX_APPLICATIONS_PER_DAY', 20); // Limit applications per day for seekers
define('MAX_ACTIVE_JOBS', 10); // Default limit for active jobs per employer
define('EXPIRE_JOBS_AFTER_DAYS', 30); // Auto-close jobs after this many days

// Content moderation settings
define('REQUIRE_REVIEW_APPROVAL', true); // Whether employer reviews require approval
define('AUTO_APPROVE_JOBS', false); // Whether job postings require approval
define('PROFANITY_FILTER', true); // Enable automatic profanity filtering

// Payment and subscription settings (if applicable)
define('PREMIUM_ENABLED', true);
define('CURRENCY', 'USD');
// Around line 65 in includes/config.php
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '$');
}
define('VAT_RATE', 0.20); // 20% VAT/tax rate

// System maintenance
define('MAINTENANCE_MODE', false); // Set to true to enable maintenance mode
define('MAINTENANCE_MESSAGE', 'We are currently performing scheduled maintenance. Please check back soon!');
define('DEBUG_MODE', false); // Enable/disable debugging

// Timezone settings
date_default_timezone_set('UTC');

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(dirname(__FILE__)) . '/logs/error.log');
    define('DEBUG_MODE', true);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(dirname(__FILE__)) . '/logs/error.log');
    define('DEBUG_MODE', false);
}

// Create required directories if they don't exist
$directories = [UPLOAD_PATH, PROFILE_PATH, RESUME_PATH, COMPANY_LOGO_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize system information for debugging
if (DEBUG_MODE) {
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'database' => 'MySQL',
        'current_time_utc' => date('Y-m-d H:i:s'), // Current UTC time: 2025-05-01 17:04:14
        'current_user' => isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest' // Current user: HasinduNimesh
    ];
    
    // Log system startup information
    $logMessage = "System initialized: " . json_encode($systemInfo);
    error_log($logMessage);
}

/**
 * Helper function for pretty print debugging 
 * Only outputs if DEBUG_MODE is true
 * 
 * @param mixed $data Data to debug
 * @param bool $die Whether to die after output
 * @return void
 */
function debug($data, $die = false) {
    if (DEBUG_MODE) {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}

// Set system-wide default constants
define('DEFAULT_LANGUAGE', 'en');
define('DEFAULT_THEME', 'light');
define('DEFAULT_PAGE_TITLE', SITE_NAME . ' - ' . SITE_TAGLINE);
define('COPYRIGHT_YEAR', '2025');
define('VERSION', '1.2.0');
?>