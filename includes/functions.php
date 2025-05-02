<?php
/**
 * Common functions for AmmooJobs platform
 * Contains utility functions used across the application
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Start measuring page load time if debug mode is enabled
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $GLOBALS['page_start_time'] = microtime(true);
}

/**
 * ===================================
 * Input Validation & Sanitization
 * ===================================
 */

/**
 * Sanitize input to prevent XSS attacks
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email address
 * 
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 * 
 * @param string $url URL to validate
 * @return bool True if valid, false otherwise
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Check if string contains only alphanumeric characters
 * 
 * @param string $string String to check
 * @param bool $allowSpaces Whether to allow spaces
 * @return bool True if valid, false otherwise
 */
function isAlphaNumeric($string, $allowSpaces = false) {
    if ($allowSpaces) {
        return preg_match('/^[a-zA-Z0-9 ]+$/', $string);
    }
    return ctype_alnum($string);
}

/**
 * Check if a password meets security requirements
 * 
 * @param string $password Password to check
 * @return array [valid, message] Valid is true if password meets requirements
 */
function validatePassword($password) {
    $minLength = 8;
    $errors = [];
    
    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least $minLength characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must include at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must include at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must include at least one number";
    }
    
    if (empty($errors)) {
        return [true, "Password meets requirements"];
    }
    
    return [false, implode(", ", $errors)];
}

/**
 * Filter profanity from text
 * 
 * @param string $text Text to filter
 * @return string Filtered text
 */
function filterProfanity($text) {
    if (!PROFANITY_FILTER) {
        return $text;
    }
    
    // Very basic profanity list - in production, use a comprehensive list or API
    $profanityList = ['badword1', 'badword2', 'offensive', 'expletive', 'inappropriate'];
    $replacement = '***';
    
    foreach ($profanityList as $word) {
        $text = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $replacement, $text);
    }
    
    return $text;
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * ===================================
 * CSRF Protection
 * ===================================
 */

/**
 * Generate CSRF token
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        time() - $_SESSION['csrf_token_time'] > CSRF_EXPIRY) {
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * 
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    if (time() - $_SESSION['csrf_token_time'] > CSRF_EXPIRY) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ===================================
 * Formatting Functions
 * ===================================
 */

/**
 * Format date
 * 
 * @param string $date Date string
 * @param string $format Format string (default: 'M j, Y')
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return '';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format date and time
 * 
 * @param string $datetime Date and time string
 * @param string $format Format string (default: 'M j, Y g:i A')
 * @return string Formatted date and time
 */
function formatDateTime($datetime, $format = 'M j, Y g:i A') {
    if (empty($datetime)) return '';
    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}

/**
 * Format salary
 * 
 * @param float|null $min Minimum salary
 * @param float|null $max Maximum salary
 * @param string $period Salary period (e.g., 'annually', 'monthly')
 * @return string Formatted salary
 */
function formatSalary($min, $max = null, $period = 'annually') {
    if (empty($min) && empty($max)) {
        return 'Salary not specified';
    }
    
    if (!empty($min) && !empty($max)) {
        if ($min == $max) {
            return CURRENCY_SYMBOL . number_format($min) . ' ' . $period;
        }
        return CURRENCY_SYMBOL . number_format($min) . ' - ' . CURRENCY_SYMBOL . number_format($max) . ' ' . $period;
    } elseif (!empty($min)) {
        return CURRENCY_SYMBOL . number_format($min) . '+ ' . $period;
    } elseif (!empty($max)) {
        return 'Up to ' . CURRENCY_SYMBOL . number_format($max) . ' ' . $period;
    }
}

/**
 * Calculate time ago from timestamp
 * 
 * @param string $timestamp Date/time string
 * @return string Time ago string (e.g., "5 minutes ago")
 */
function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $currentTime = time();
    $timeDiff = $currentTime - $time;
    
    $seconds = $timeDiff;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } elseif ($minutes <= 60) {
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } elseif ($hours <= 24) {
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } elseif ($days <= 7) {
        return $days == 1 ? "Yesterday" : "$days days ago";
    } elseif ($weeks <= 4) {
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}

/**
 * Format file size
 * 
 * @param int $bytes Size in bytes
 * @return string Formatted file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }
    return $bytes;
}

/**
 * Format a phone number
 * 
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhone($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format based on length
    $length = strlen($phone);
    if ($length == 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
    } elseif ($length > 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})(\d*)/', '($1) $2-$3 x$4', $phone);
    }
    
    return $phone;
}

/**
 * Extract domain from URL
 * 
 * @param string $url Full URL
 * @return string Domain name
 */
function getDomain($url) {
    $pieces = parse_url($url);
    $domain = isset($pieces['host']) ? $pieces['host'] : '';
    
    if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $matches)) {
        return $matches['domain'];
    }
    
    return $domain;
}

/**
 * Truncate text to a specific length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append Text to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    
    return $text . $append;
}

/**
 * ===================================
 * Job Related Functions
 * ===================================
 */

/**
 * Get job categories
 * 
 * @return array Job categories
 */
function getJobCategories() {
    return [
        'technology' => 'Technology',
        'healthcare' => 'Healthcare',
        'education' => 'Education',
        'finance' => 'Finance',
        'marketing' => 'Marketing',
        'sales' => 'Sales',
        'engineering' => 'Engineering',
        'hospitality' => 'Hospitality',
        'retail' => 'Retail',
        'customer-service' => 'Customer Service',
        'administrative' => 'Administrative',
        'legal' => 'Legal',
        'human-resources' => 'Human Resources',
        'manufacturing' => 'Manufacturing',
        'construction' => 'Construction',
        'design' => 'Design',
        'writing' => 'Writing & Content',
        'transportation' => 'Transportation',
        'government' => 'Government',
        'nonprofit' => 'Nonprofit',
        'other' => 'Other'
    ];
}

/**
 * Get job types
 * 
 * @return array Job types
 */
function getJobTypes() {
    return [
        'full-time' => 'Full Time',
        'part-time' => 'Part Time',
        'contract' => 'Contract',
        'temporary' => 'Temporary',
        'remote' => 'Remote',
        'internship' => 'Internship',
        'freelance' => 'Freelance'
    ];
}

/**
 * Get job experience levels
 * 
 * @return array Experience levels
 */
function getExperienceLevels() {
    return [
        'entry' => 'Entry Level',
        'mid' => 'Mid Level',
        'senior' => 'Senior Level',
        'executive' => 'Executive Level'
    ];
}

/**
 * Get education levels
 * 
 * @return array Education levels
 */
function getEducationLevels() {
    return [
        'high-school' => 'High School',
        'associate' => 'Associate Degree',
        'bachelor' => 'Bachelor\'s Degree',
        'master' => 'Master\'s Degree',
        'doctorate' => 'Doctorate Degree',
        'certification' => 'Professional Certification',
        'other' => 'Other'
    ];
}

/**
 * Calculate days left until deadline
 * 
 * @param string $deadline Deadline date
 * @return int|string Days left or 'Expired' if passed
 */
function calculateDaysLeft($deadline) {
    if (empty($deadline)) {
        return 'No deadline';
    }
    
    $deadlineTime = strtotime($deadline);
    $currentTime = time();
    
    if ($deadlineTime < $currentTime) {
        return 'Expired';
    }
    
    $daysLeft = floor(($deadlineTime - $currentTime) / (60 * 60 * 24));
    
    if ($daysLeft == 0) {
        return 'Today';
    } else if ($daysLeft == 1) {
        return '1 day';
    } else {
        return $daysLeft . ' days';
    }
}

/**
 * ===================================
 * Notification Functions
 * ===================================
 */

/**
 * Add a notification for a user
 * 
 * @param int $userId User ID to notify
 * @param string $type Notification type
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool Success status
 */
function addNotification($userId, $type, $message, $link = null) {
    global $db;
    
    return $db->executeNonQuery(
        "INSERT INTO notifications (user_id, type, message, link, created_at, is_read) 
         VALUES (?, ?, ?, ?, NOW(), 0)",
        [$userId, $type, $message, $link]
    );
}

/**
 * Get unread notification count for user
 * 
 * @param int $userId User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($userId) {
    global $db;
    
    $result = $db->fetchSingle("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$userId]);
    return $result ? (int)$result['count'] : 0;
}

/**
 * Mark notification as read
 * 
 * @param int $notificationId Notification ID
 * @param int $userId User ID for verification
 * @return bool Success status
 */
function markNotificationRead($notificationId, $userId) {
    global $db;
    
    return $db->executeNonQuery(
        "UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?",
        [$notificationId, $userId]
    );
}

/**
 * ===================================
 * File & Image Functions
 * ===================================
 */

/**
 * Generate a unique filename
 * 
 * @param string $originalName Original filename
 * @param int $userId User ID
 * @param string $prefix Optional prefix
 * @return string Unique filename
 */
function generateUniqueFilename($originalName, $userId, $prefix = '') {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $timestamp = time();
    $randomString = bin2hex(random_bytes(5));
    
    return $prefix . $timestamp . '_' . $userId . '_' . $randomString . '.' . $extension;
}

/**
 * Check if file type is allowed
 * 
 * @param string $fileType MIME type
 * @param array $allowedTypes Allowed MIME types
 * @return bool True if allowed, false otherwise
 */
function isAllowedFileType($fileType, $allowedTypes) {
    return in_array($fileType, $allowedTypes);
}

/**
 * ===================================
 * Debugging Functions
 * ===================================
 */

/**
 * Log debug info to the error log
 * 
 * @param mixed $data Data to log
 * @param string $label Optional label
 * @return void
 */
function logDebug($data, $label = '') {
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        return;
    }
    
    $currentDate = date('Y-m-d H:i:s'); // Current server time: 2025-05-01 17:08:44
    $currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // Current user: HasinduNimesh
    
    $output = "[$currentDate] [$currentUser]";
    
    if ($label) {
        $output .= " [$label]";
    }
    
    if (is_array($data) || is_object($data)) {
        $output .= " " . print_r($data, true);
    } else {
        $output .= " " . $data;
    }
    
    error_log($output);
}

/**
 * Get memory usage
 * 
 * @param bool $formatted Whether to return formatted string
 * @return int|string Memory usage in bytes or formatted string
 */
function getMemoryUsage($formatted = true) {
    $memory = memory_get_usage();
    
    if ($formatted) {
        return formatFileSize($memory);
    }
    
    return $memory;
}

/**
 * ===================================
 * Other Utility Functions
 * ===================================
 */

/**
 * Generate a random password
 * 
 * @param int $length Password length
 * @return string Random password
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * Build a URL with query parameters
 * 
 * @param string $url Base URL
 * @param array $params Query parameters
 * @return string Full URL with query string
 */
function buildUrl($url, $params = []) {
    if (empty($params)) {
        return $url;
    }
    
    $queryString = http_build_query($params);
    $separator = strpos($url, '?') !== false ? '&' : '?';
    
    return $url . $separator . $queryString;
}

/**
 * Get current page URL
 * 
 * @return string Current URL
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return "$protocol://$host$uri";
}

/**
 * Check if a string starts with a specific substring
 * 
 * @param string $haystack String to search in
 * @param string $needle String to search for
 * @return bool True if haystack starts with needle
 */
function startsWith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if a string ends with a specific substring
 * 
 * @param string $haystack String to search in
 * @param string $needle String to search for
 * @return bool True if haystack ends with needle
 */
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    
    return substr($haystack, -$length) === $needle;
}

/**
 * Convert a string to slug format
 * 
 * @param string $string Input string
 * @return string Slugified string
 */
function slugify($string) {
    // Replace non-alphanumeric characters with hyphens
    $string = preg_replace('/[^a-z0-9]+/i', '-', $string);
    // Convert to lowercase
    $string = strtolower($string);
    // Trim hyphens from beginning and end
    $string = trim($string, '-');
    
    return $string;
}

/**
 * Log system activity for administrative monitoring
 * 
 * @param string $action Action performed
 * @param string $details Action details
 * @param int|null $userId User who performed action (null for system)
 * @return bool Success status
 */
function logSystemActivity($action, $details, $userId = null) {
    global $db;
    
    $currentDate = date('Y-m-d H:i:s'); // Current server time: 2025-05-01 17:08:44
    $ipAddress = getClientIp();
    
    return $db->executeNonQuery(
        "INSERT INTO system_activity_log (action, details, user_id, ip_address, timestamp) 
         VALUES (?, ?, ?, ?, ?)",
        [$action, $details, $userId, $ipAddress, $currentDate]
    );
}

// Log script execution in debug mode
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $currentDate = date('Y-m-d H:i:s'); // Current server time: 2025-05-01 17:08:44
    $currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // Current user: HasinduNimesh
    error_log("[$currentDate] [$currentUser] functions.php loaded");
}


function columnExists($table, $column) {
    global $db;
    $result = $db->fetchAll("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return !empty($result);
}
?>