<?php
/**
 * Authentication class for AmmooJobs platform
 * Handles user authentication, registration, and related functions
 */

class Auth {
    private $db;
    
    /**
     * Constructor
     * 
     * @param Database $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Register a new user
     * 
     * @param string $name User's full name
     * @param string $email User's email address
     * @param string $password User's password
     * @param string $userType User type (seeker, employer, admin)
     * @param string|null $companyName Company name (for employers)
     * @return array Success status and message
     */
    public function register($name, $email, $password, $userType = 'seeker', $companyName = null) {
        // Check if email already exists
        $existingUser = $this->db->fetchSingle("SELECT user_id FROM users WHERE email = ?", [$email]);
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'Email address is already registered.'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Create user
        $result = $this->db->executeNonQuery(
            "INSERT INTO users (name, email, password, user_type, company_name, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$name, $email, $hashedPassword, $userType, $companyName]
        );
        
        if ($result) {
            $userId = $this->db->getLastId();
            
            // Log registration activity
            $this->logActivity($userId, 'registration', "New $userType account created");
            
            return [
                'success' => true,
                'message' => 'Registration successful!',
                'user_id' => $userId
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }
    
    /**
     * Authenticate user login
     * 
     * @param string $email User's email address
     * @param string $password User's password
     * @return array Success status, message, and user data if successful
     */
    public function login($email, $password) {
        // Get user by email
        $user = $this->db->fetchSingle("SELECT * FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'No account found with this email address.'
            ];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            // Log failed login attempt
            $this->logActivity($user['user_id'], 'failed_login', "Failed login attempt", true);
            
            return [
                'success' => false,
                'message' => 'Invalid password. Please try again.'
            ];
        }
        
        // Check if account is active
        if (isset($user['status']) && $user['status'] != 'active') {
            return [
                'success' => false,
                'message' => 'Your account is not active. Please contact support.'
            ];
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Log successful login
        $this->logActivity($user['user_id'], 'login', "User logged in");
        
        return [
            'success' => true,
            'message' => 'Login successful!',
            'user' => $user
        ];
    }
    
    /**
     * Attempt to login user using remember-me token
     * 
     * @return bool True if login successful, false otherwise
     */
    public function attemptRememberMeLogin() {
        if (!isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        // Parse the token
        list($userId, $token) = explode(':', $_COOKIE['remember_token']);
        if (!$userId || !$token) {
            return false;
        }
        
        // Get token from database
        $tokenData = $this->db->fetchSingle(
            "SELECT * FROM user_tokens WHERE user_id = ? AND expires > NOW()",
            [(int)$userId]
        );
        
        if (!$tokenData) {
            // Token doesn't exist or expired
            setcookie('remember_token', '', time() - 3600, '/', '', false, true); // Clear the cookie
            return false;
        }
        
        // Verify token
        if (!password_verify($token, $tokenData['token'])) {
            return false;
        }
        
        // Get user data
        $user = $this->db->fetchSingle("SELECT * FROM users WHERE user_id = ?", [(int)$userId]);
        if (!$user) {
            return false;
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Log auto login
        $this->logActivity($user['user_id'], 'auto_login', "Auto login via remember-me token");
        
        return true;
    }
    
    /**
     * Check if current password is valid for a user
     * 
     * @param int $userId User ID
     * @param string $password Password to check
     * @return bool True if password is valid, false otherwise
     */
    public function verifyCurrentPassword($userId, $password) {
        $user = $this->db->fetchSingle("SELECT password FROM users WHERE user_id = ?", [$userId]);
        if (!$user) {
            return false;
        }
        
        return password_verify($password, $user['password']);
    }
    
    /**
     * Update user password
     * 
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool True if successful, false otherwise
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = $this->db->executeNonQuery(
            "UPDATE users SET password = ? WHERE user_id = ?",
            [$hashedPassword, $userId]
        );
        
        if ($result) {
            // Log password change
            $this->logActivity($userId, 'password_change', "Password updated");
            return true;
        }
        
        return false;
    }
    
    /**
     * Generate and store password reset token
     * 
     * @param string $email User's email address
     * @return array Result with token if successful
     */
    public function generatePasswordResetToken($email) {
        $user = $this->db->fetchSingle("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'No account found with this email address.'
            ];
        }
        
        // Generate unique token
        $token = bin2hex(random_bytes(32));
        $hashedToken = password_hash($token, PASSWORD_DEFAULT);
        $expires = date('Y-m-d H:i:s', time() + 3600); // Token valid for 1 hour
        
        // Remove any existing reset tokens for this user
        $this->db->executeNonQuery(
            "DELETE FROM password_resets WHERE user_id = ?",
            [$user['user_id']]
        );
        
        // Store new token
        $result = $this->db->executeNonQuery(
            "INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)",
            [$user['user_id'], $hashedToken, $expires]
        );
        
        if ($result) {
            // Log password reset request
            $this->logActivity($user['user_id'], 'password_reset_request', "Password reset requested");
            
            return [
                'success' => true,
                'user_id' => $user['user_id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'token' => $token,
                'expires' => $expires
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to generate reset token. Please try again.'
        ];
    }
    
    /**
     * Verify password reset token
     * 
     * @param int $userId User ID
     * @param string $token Reset token
     * @return array Success status and user data if successful
     */
    public function verifyResetToken($userId, $token) {
        $resetData = $this->db->fetchSingle(
            "SELECT * FROM password_resets WHERE user_id = ? AND expires > NOW()",
            [$userId]
        );
        
        if (!$resetData) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ];
        }
        
        // Verify token
        if (!password_verify($token, $resetData['token'])) {
            return [
                'success' => false,
                'message' => 'Invalid reset token.'
            ];
        }
        
        // Get user data
        $user = $this->db->fetchSingle("SELECT * FROM users WHERE user_id = ?", [$userId]);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found.'
            ];
        }
        
        return [
            'success' => true,
            'user' => $user
        ];
    }
    
    /**
     * Reset user password using token
     * 
     * @param int $userId User ID
     * @param string $token Reset token
     * @param string $newPassword New password
     * @return array Success status and message
     */
    public function resetPassword($userId, $token, $newPassword) {
        // Verify token first
        $verifyResult = $this->verifyResetToken($userId, $token);
        if (!$verifyResult['success']) {
            return $verifyResult;
        }
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password
        $result = $this->db->executeNonQuery(
            "UPDATE users SET password = ? WHERE user_id = ?",
            [$hashedPassword, $userId]
        );
        
        if ($result) {
            // Delete used reset token
            $this->db->executeNonQuery(
                "DELETE FROM password_resets WHERE user_id = ?",
                [$userId]
            );
            
            // Log password reset
            $this->logActivity($userId, 'password_reset', "Password reset completed");
            
            return [
                'success' => true,
                'message' => 'Password has been reset successfully!'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Failed to reset password. Please try again.'
        ];
    }
    
    /**
     * Log user activity
     * 
     * @param int $userId User ID
     * @param string $activityType Type of activity
     * @param string $details Activity details
     * @param bool $isSecurityEvent Whether this is a security event
     * @return bool Success status
     */
    private function logActivity($userId, $activityType, $details = '', $isSecurityEvent = false) {
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        return $this->db->executeNonQuery(
            "INSERT INTO user_activity_log (user_id, activity_type, ip_address, user_agent, details, is_security_event, timestamp) 
             VALUES (?, ?, ?, ?, ?, ?, NOW())",
            [$userId, $activityType, $ipAddress, $userAgent, $details, $isSecurityEvent ? 1 : 0]
        );
    }
    
    /**
     * Check for suspicious login activities
     * 
     * @param int $userId User ID
     * @return array Any detected security issues
     */
    public function checkSecurityIssues($userId) {
        $issues = [];
        
        // Check for failed login attempts
        $failedLogins = $this->db->fetchSingle(
            "SELECT COUNT(*) as count FROM user_activity_log 
             WHERE user_id = ? AND activity_type = 'failed_login' AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            [$userId]
        );
        
        if ($failedLogins && $failedLogins['count'] >= 5) {
            $issues[] = [
                'type' => 'failed_logins',
                'message' => "Multiple failed login attempts detected in the last 24 hours ({$failedLogins['count']} attempts).",
                'severity' => 'warning'
            ];
        }
        
        // Check for logins from new locations
        $uniqueIPs = $this->db->fetchAll(
            "SELECT DISTINCT ip_address FROM user_activity_log 
             WHERE user_id = ? AND activity_type = 'login' AND timestamp > DATE_SUB(NOW(), INTERVAL 30 DAY)",
            [$userId]
        );
        
        $currentIP = $_SERVER['REMOTE_ADDR'];
        $newLocation = true;
        
        foreach ($uniqueIPs as $ip) {
            if ($ip['ip_address'] == $currentIP) {
                $newLocation = false;
                break;
            }
        }
        
        if ($newLocation && count($uniqueIPs) > 0) {
            $issues[] = [
                'type' => 'new_location',
                'message' => "Login from a new location detected. Your current IP address: $currentIP",
                'severity' => 'info'
            ];
        }
        
        return $issues;
    }
}

// Initialize authentication class with database connection
$auth = new Auth($db);

/**
 * Check if user is logged in
 * 
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if current user is a job seeker
 * 
 * @return bool True if user is a job seeker, false otherwise
 */
function isSeeker() {
    return isLoggedIn() && $_SESSION['user_type'] === 'seeker';
}

/**
 * Check if current user is an employer
 * 
 * @return bool True if user is an employer, false otherwise
 */
function isEmployer() {
    return isLoggedIn() && $_SESSION['user_type'] === 'employer';
}

/**
 * Check if current user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin() {
    return isLoggedIn() && $_SESSION['user_type'] === 'admin';
}

/**
 * Require user to be logged in, redirect to login if not
 * 
 * @return void
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['error'] = "Please login to access that page.";
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
    
    // Check for session timeout
    $timeout = 3600; // 1 hour
    if (isset($_SESSION['login_time']) && time() - $_SESSION['login_time'] > $timeout) {
        // Session expired, log the user out
        session_unset();
        session_destroy();
        session_start();
        
        $_SESSION['error'] = "Your session has expired. Please login again.";
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit;
    }
    
    // Update last activity time
    $_SESSION['login_time'] = time();
}

/**
 * Require user to be a job seeker, redirect if not
 * 
 * @return void
 */
function requireSeeker() {
    requireLogin();
    
    if (!isSeeker()) {
        $_SESSION['error'] = "Access denied. Only job seekers can access that page.";
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Require user to be an employer, redirect if not
 * 
 * @return void
 */
function requireEmployer() {
    requireLogin();
    
    if (!isEmployer()) {
        $_SESSION['error'] = "Access denied. Only employers can access that page.";
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Require user to be an admin, redirect if not
 * 
 * @return void
 */
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        $_SESSION['error'] = "Access denied. Administrator privileges required.";
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Check if an account is a demo/test account
 * 
 * @param int $userId User ID to check
 * @return bool True if demo account, false otherwise
 */
function isDemoAccount($userId) {
    global $db;
    
    $user = $db->fetchSingle("SELECT email FROM users WHERE user_id = ?", [$userId]);
    
    if (!$user) {
        return false;
    }
    
    // Check if email is from our demo domains
    $demoEmails = ['seeker@test.com', 'employer@test.com', 'admin@test.com'];
    $testDomains = ['test.com', 'example.com', 'ammoojobs.test'];
    
    if (in_array($user['email'], $demoEmails)) {
        return true;
    }
    
    foreach ($testDomains as $domain) {
        if (strpos($user['email'], '@' . $domain) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user has applied for a job
 * 
 * @param int $seekerId Job seeker ID
 * @param int $jobId Job ID
 * @return bool True if applied, false otherwise
 */
function hasApplied($seekerId, $jobId) {
    global $db;
    
    $application = $db->fetchSingle(
        "SELECT application_id FROM applications WHERE seeker_id = ? AND job_id = ?",
        [$seekerId, $jobId]
    );
    
    return $application ? true : false;
}

/**
 * Get user by ID
 * 
 * @param int $userId User ID
 * @return array|null User data or null if not found
 */
function getUserById($userId) {
    global $db;
    
    return $db->fetchSingle("SELECT * FROM users WHERE user_id = ?", [$userId]);
}

/**
 * Get job by ID
 * 
 * @param int $jobId Job ID
 * @return array|null Job data or null if not found
 */
function getJobById($jobId) {
    global $db;
    
    $job = $db->fetchSingle(
        "SELECT j.*, u.name as employer_name, u.company_name 
         FROM job_listings j
         JOIN users u ON j.employer_id = u.user_id
         WHERE j.job_id = ?", 
        [$jobId]
    );
    
    return $job;
}

/**
 * Get employer rating
 * 
 * @param int $employerId Employer ID
 * @return array Average rating and count
 */
function getEmployerRating($employerId) {
    global $db;
    
    $rating = $db->fetchSingle(
        "SELECT AVG(rating) as average, COUNT(*) as count 
         FROM employer_reviews 
         WHERE employer_id = ? AND status = 'approved'", 
        [$employerId]
    );
    
    return [
        'average' => $rating['average'] ? round($rating['average'], 1) : 0,
        'count' => (int)$rating['count']
    ];
}

/**
 * Get application status label with HTML formatting
 * 
 * @param string $status Application status
 * @return string HTML formatted status label
 */
function getStatusLabel($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning text-dark">Pending Review</span>';
        case 'reviewed':
            return '<span class="badge bg-info">Under Review</span>';
        case 'shortlisted':
            return '<span class="badge bg-primary">Shortlisted</span>';
        case 'interviewed':
            return '<span class="badge bg-primary">Interviewed</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Not Selected</span>';
        case 'offered':
            return '<span class="badge bg-success">Job Offered</span>';
        case 'accepted':
            return '<span class="badge bg-success">Hired</span>';
        case 'withdrawn':
            return '<span class="badge bg-secondary">Withdrawn</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>