<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include necessary files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userName = isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $currentTime = date('Y-m-d H:i:s'); // Current server time
    
    // Log the logout activity
    $db->executeNonQuery(
        "INSERT INTO user_activity_log (user_id, activity_type, ip_address, timestamp, details) 
         VALUES (?, ?, ?, ?, ?)",
        [$userId, 'logout', $ipAddress, $currentTime, "User $userName logged out"]
    );

    // Clear any remember-me tokens from database
    if (isset($_COOKIE['remember_token'])) {
        list($cookieUserId, $token) = explode(':', $_COOKIE['remember_token']);
        if ($cookieUserId == $userId) {
            $db->executeNonQuery(
                "DELETE FROM user_tokens WHERE user_id = ?",
                [$userId]
            );
        }
        
        // Delete the remember-me cookie
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a success message for the next page
session_start();
$_SESSION['success'] = "You have been successfully logged out.";

// Redirect to login page
header("Location: login.php");
exit();
?>