<?php
$pageTitle = 'Login';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: login.php");
        exit;
    }
    
    // Sanitize input
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $rememberMe = isset($_POST['remember_me']);
    
    // Validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email and password are required.";
    } else {
        // Attempt login
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            // Set remember me cookie if checked
            if ($rememberMe) {
                $token = bin2hex(random_bytes(32));
                $userId = $result['user']['user_id'];
                $expires = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store token in database
                $hashedToken = password_hash($token, PASSWORD_DEFAULT);
                $db->executeNonQuery(
                    "INSERT INTO user_tokens (user_id, token, expires) VALUES (?, ?, ?)",
                    [$userId, $hashedToken, date('Y-m-d H:i:s', $expires)]
                );
                
                // Set cookie
                setcookie('remember_token', $userId . ':' . $token, $expires, '/', '', false, true);
            }
            
            // Redirect to intended page or dashboard
            if (isAdmin()) {
                $redirect = 'admin/index.php';
            } else {
                $redirect = 'dashboard.php';
            }
            header("Location: $redirect");
            exit;
        } else {
            $_SESSION['error'] = $result['message'];
        }
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="card-title text-center mb-4">Login to Your Account</h2>
                
                <form id="loginForm" method="POST" action="login.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">Remember Me</label>
                        </div>
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                    
                    <div class="text-center">
                        Don't have an account? <a href="register.php">Register here</a>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="text-center">
                    <div class="mb-3">Demo Accounts for Testing</div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary w-100" 
                                    onclick="fillCredentials('employer@test.com', 'password123')">
                                Demo Employer
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary w-100" 
                                    onclick="fillCredentials('seeker@test.com', 'password123')">
                                Demo Job Seeker
                            </button>
                        </div>
                    </div>
                    <small class="text-muted">Click to fill login credentials</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="alertContainer" class="fixed-bottom p-3"></div>

<script>
    function fillCredentials(email, password) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = password;
    }
</script>

<?php require_once 'includes/footer.php'; ?>