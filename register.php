<?php
$pageTitle = 'Register';
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

// Set default user type
$userType = isset($_GET['type']) && in_array($_GET['type'], ['seeker', 'employer']) ? $_GET['type'] : 'seeker';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: register.php" . ($userType !== 'seeker' ? "?type=$userType" : ""));
        exit;
    }
    
    // Sanitize and validate input
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $userType = sanitizeInput($_POST['user_type']);
    $companyName = isset($_POST['company_name']) ? sanitizeInput($_POST['company_name']) : null;
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    if ($userType === 'employer' && empty($companyName)) {
        $errors[] = "Company name is required for employer accounts.";
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        $result = $auth->register($name, $email, $password, $userType, $companyName);
        
        if ($result['success']) {
            // Set success message and redirect to login
            $_SESSION['success'] = "Registration successful! Please log in.";
            header("Location: login.php");
            exit;
        } else {
            $_SESSION['error'] = $result['message'];
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4 p-md-5">
                <h2 class="card-title text-center mb-4">Create an Account</h2>
                
                <!-- Account Type Selector -->
                <div class="mb-4">
                    <ul class="nav nav-pills nav-justified" id="accountTypeTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $userType === 'seeker' ? 'active' : '' ?>" 
                               href="register.php?type=seeker" 
                               role="tab">
                                <i class="fas fa-user me-2"></i>Job Seeker
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $userType === 'employer' ? 'active' : '' ?>" 
                               href="register.php?type=employer" 
                               role="tab">
                                <i class="fas fa-building me-2"></i>Employer
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Registration Form -->
                <form id="registrationForm" method="POST" action="register.php" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="user_type" value="<?= $userType ?>">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                        </div>
                    </div>
                    
                    <?php if ($userType === 'employer'): ?>
                    <div class="mb-3">
                        <label for="company_name" class="form-label">Company Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <input type="text" class="form-control" id="company_name" name="company_name" required
                                   value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>">
                        </div>
                    </div>
                    <?php endif; ?>
                    
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
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="termsCheck" required>
                        <label class="form-check-label" for="termsCheck">
                            I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                        </label>
                    </div>
                    
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                    
                    <div class="text-center">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Terms Modal -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="termsModalLabel">Terms of Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Acceptance of Terms</h6>
                <p>By accessing or using the AmmooJobs platform, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>
                
                <h6>2. User Accounts</h6>
                <p>Users are responsible for maintaining the confidentiality of their account information, including password, and for restricting access to their computer.</p>
                
                <h6>3. User Content</h6>
                <p>Users are solely responsible for the content they post on AmmooJobs, including job listings, applications, and communications.</p>
                
                <h6>4. Prohibited Activities</h6>
                <p>Users must not engage in any activity that interferes with or disrupts the services or servers connected to AmmooJobs.</p>
                
                <h6>5. Termination</h6>
                <p>AmmooJobs reserves the right to terminate or suspend accounts at our sole discretion, without notice, for conduct that we believe violates these Terms of Service.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>1. Information Collection</h6>
                <p>We collect information you provide directly to us when registering, creating or modifying your account, applying for jobs, or communicating with us.</p>
                
                <h6>2. Use of Information</h6>
                <p>We use the information we collect to provide, maintain, and improve our services, and to communicate with you.</p>
                
                <h6>3. Information Sharing</h6>
                <p>We may share information as described in this policy—for example, to enforce our Terms of Service, to protect the rights, privacy, safety, or property of AmmooJobs or others.</p>
                
                <h6>4. Data Security</h6>
                <p>We take reasonable measures to help protect information about you from loss, theft, misuse, unauthorized access, disclosure, alteration, and destruction.</p>
                
                <h6>5. Your Choices</h6>
                <p>You may update, correct, or delete your account information at any time by logging into your account.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
            </div>
        </div>
    </div>
</div>

<div id="alertContainer" class="fixed-bottom p-3"></div>

<?php require_once 'includes/footer.php'; ?>