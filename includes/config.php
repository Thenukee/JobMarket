<?php
/**
 * Main configuration file for AmmooJobs
 * 
 * Contains all configuration settings, constants, and global settings
 * Last updated: 2025-05-02
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Site information
define('SITE_NAME', 'AmmooJobs');
define('SITE_TAGLINE', 'Find Your Dream Job');
define('SITE_URL', 'http://ammoojobs.onlinewebshop.net/');
define('ADMIN_EMAIL', 'admin@ammoojobs.com');
define('SUPPORT_EMAIL', 'support@ammoojobs.com');
define('NOREPLY_EMAIL', 'noreply@ammoojobs.com');

// Environment settings (development, testing, production)
define('ENVIRONMENT', 'production');

// Database configuration
define('DB_HOST', 'fdb1030.awardspace.net');
define('DB_NAME', '4627962_ammoo');
define('DB_USER', '4627962_ammoo');
define('DB_PASS', 'StrongPassword123');
define('DB_PORT', 3306);

define('DEBUG_MODE', true);

// Security settings
define('HASH_SALT', 'am#m00J0bs!S@ltV@lu3');
define('CSRF_EXPIRY', 3600);
define('SESSION_LIFETIME', 7200);
define('PASSWORD_RESET_EXPIRY', 3600);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOCKOUT_TIME', 900);

// File upload settings
define('UPLOAD_PATH', dirname(dirname(__FILE__)) . '/uploads/');
define('PROFILE_PATH', UPLOAD_PATH . 'profiles/');
define('RESUME_PATH', UPLOAD_PATH . 'resumes/');
define('COMPANY_LOGO_PATH', UPLOAD_PATH . 'logos/');
define('MAX_UPLOAD_SIZE', 5242880);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_RESUME_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Pagination settings
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGINATION_LINKS', 5);

// Job application settings
define('MAX_APPLICATIONS_PER_DAY', 20);
define('MAX_ACTIVE_JOBS', 10);
define('EXPIRE_JOBS_AFTER_DAYS', 30);

// Content moderation settings
define('REQUIRE_REVIEW_APPROVAL', true);
define('AUTO_APPROVE_JOBS', false);
define('PROFANITY_FILTER', true);

// Payment and subscription settings
define('PREMIUM_ENABLED', true);
define('CURRENCY', 'USD');
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', '$');
}
define('VAT_RATE', 0.20);

// System maintenance
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'We are currently performing scheduled maintenance. Please check back soon!');

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
}

// Create required directories if they don't exist
$directories = [UPLOAD_PATH, PROFILE_PATH, RESUME_PATH, COMPANY_LOGO_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Initialize system information for debugging
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'],
        'database' => 'MySQL',
        'current_time_utc' => date('Y-m-d H:i:s'),
        'current_user' => isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'
    ];
    
    // Log system startup information
    $logMessage = "System initialized: " . json_encode($systemInfo);
    error_log($logMessage);
}

/**
 * Helper function for pretty print debugging 
 */
function debug($data, $die = false) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
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