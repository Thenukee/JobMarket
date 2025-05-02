<?php
$pageTitle = 'My Profile';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Require login to access profile page
requireLogin();

// Get user data
$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$userType = $_SESSION['user_type'];

// Handle profile update submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: profile.php");
        exit;
    }
    
    // Sanitize and validate input
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $location = sanitizeInput($_POST['location']);
    $bio = sanitizeInput($_POST['bio']);
    $websiteUrl = sanitizeInput($_POST['website']);
    $companyName = isEmployer() ? sanitizeInput($_POST['company_name']) : null;
    
    // Additional fields for job seekers
    $skills = isSeeker() ? sanitizeInput($_POST['skills']) : null;
    $education = isSeeker() ? sanitizeInput($_POST['education']) : null;
    $experience = isSeeker() ? sanitizeInput($_POST['experience']) : null;
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    } elseif ($email !== $user['email']) {
        // Check if email already exists
        $existingUser = $db->fetchSingle("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $userId]);
        if ($existingUser) {
            $errors[] = "Email is already in use by another account.";
        }
    }
    
    if (isEmployer() && empty($companyName)) {
        $errors[] = "Company name is required.";
    }
    
    if (!empty($websiteUrl) && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {
        $errors[] = "Please enter a valid website URL.";
    }
    
    // Handle profile image upload
    $profileImagePath = $user['profile_image']; // Default to existing image
    
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading profile image. Error code: " . $_FILES['profile_image']['error'];
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $fileType = $_FILES['profile_image']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Only JPG, PNG, GIF and WEBP images are allowed.";
            }
            
            if ($_FILES['profile_image']['size'] > 2000000) { // 2MB limit
                $errors[] = "Image file size must be less than 2MB.";
            }
            
            if (empty($errors)) {
                // Delete old profile image if exists
                if (!empty($profileImagePath) && file_exists(PROFILE_PATH . $profileImagePath)) {
                    unlink(PROFILE_PATH . $profileImagePath);
                }
                
                // Generate unique filename
                $fileName = time() . '_' . $userId . '_' . basename($_FILES['profile_image']['name']);
                $targetFilePath = PROFILE_PATH . $fileName;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetFilePath)) {
                    $profileImagePath = $fileName;
                } else {
                    $errors[] = "Failed to upload image. Please try again.";
                }
            }
        }
    } elseif (isset($_POST['remove_image']) && $_POST['remove_image'] == '1') {
        // Remove profile image if requested
        if (!empty($profileImagePath) && file_exists(PROFILE_PATH . $profileImagePath)) {
            unlink(PROFILE_PATH . $profileImagePath);
        }
        $profileImagePath = null;
    }
    
    // If no errors, update the profile
    if (empty($errors)) {
        // Build update data
        $updateData = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'location' => $location,
            'bio' => $bio,
            'profile_image' => $profileImagePath,
            'website' => $websiteUrl,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if (isEmployer()) {
            $updateData['company_name'] = $companyName;
        }
        
        if (isSeeker()) {
            $updateData['skills'] = $skills;
            $updateData['education'] = $education;
            $updateData['experience'] = $experience;
        }
        
        // Build update query
        $setClause = "";
        $params = [];
        
        foreach ($updateData as $key => $value) {
            $setClause .= "$key = ?, ";
            $params[] = $value;
        }
        
        $setClause = rtrim($setClause, ", ");
        $params[] = $userId;
        
        $sql = "UPDATE users SET $setClause WHERE user_id = ?";
        $result = $db->executeNonQuery($sql, $params);
        
        if ($result) {
            $_SESSION['success'] = "Profile updated successfully.";
            header("Location: profile.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to update profile. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: profile.php");
        exit;
    }
    
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    $errors = [];
    
    if (empty($currentPassword)) {
        $errors[] = "Current password is required.";
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    if (empty($newPassword)) {
        $errors[] = "New password is required.";
    } elseif (strlen($newPassword) < 8) {
        $errors[] = "New password must be at least 8 characters.";
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match.";
    }
    
    // If no errors, update the password
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = $db->executeNonQuery("UPDATE users SET password = ? WHERE user_id = ?", [$hashedPassword, $userId]);
        
        if ($result) {
            $_SESSION['success'] = "Password changed successfully.";
            header("Location: profile.php");
            exit;
        } else {
            $_SESSION['error'] = "Failed to change password. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-3 mb-4">
        <!-- Profile Sidebar -->
        <div class="card">
            <div class="card-body text-center">
                <div class="profile-image-container mb-3">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile Picture" class="profile-image rounded-circle img-thumbnail">
                    <?php else: ?>
                        <div class="profile-image-placeholder">
                            <i class="fas fa-user fa-3x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h5 class="card-title"><?= htmlspecialchars($user['name']) ?></h5>
                <p class="text-muted">
                    <?php if (isEmployer() && !empty($user['company_name'])): ?>
                        <?= htmlspecialchars($user['company_name']) ?><br>
                    <?php endif; ?>
                    <span class="badge bg-<?= $userType == 'employer' ? 'primary' : 'success' ?>"><?= ucfirst($userType) ?></span>
                </p>
                
                <div class="profile-stats mb-3">
                    <div class="row text-center">
                        <?php if (isSeeker()): ?>
                            <?php 
                            $applicationCount = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ?", [$userId])['count'];
                            $savedJobsCount = $db->fetchSingle("SELECT COUNT(*) as count FROM saved_jobs WHERE user_id = ?", [$userId])['count'];
                            ?>
                            <div class="col-6">
                                <h6><?= $applicationCount ?></h6>
                                <small class="text-muted">Applications</small>
                            </div>
                            <div class="col-6">
                                <h6><?= $savedJobsCount ?></h6>
                                <small class="text-muted">Saved Jobs</small>
                            </div>
                        <?php elseif (isEmployer()): ?>
                            <?php 
                            $jobsCount = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE employer_id = ?", [$userId])['count'];
                            $applicantsCount = $db->fetchSingle("SELECT COUNT(*) as count FROM applications a JOIN job_listings j ON a.job_id = j.job_id WHERE j.employer_id = ?", [$userId])['count'];
                            ?>
                            <div class="col-6">
                                <h6><?= $jobsCount ?></h6>
                                <small class="text-muted">Posted Jobs</small>
                            </div>
                            <div class="col-6">
                                <h6><?= $applicantsCount ?></h6>
                                <small class="text-muted">Applicants</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <ul class="list-group list-group-flush text-start mb-3">
                    <?php if (!empty($user['email'])): ?>
                    <li class="list-group-item px-0">
                        <i class="fas fa-envelope me-2 text-primary"></i> <?= htmlspecialchars($user['email']) ?>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['phone'])): ?>
                    <li class="list-group-item px-0">
                        <i class="fas fa-phone me-2 text-primary"></i> <?= htmlspecialchars($user['phone']) ?>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['location'])): ?>
                    <li class="list-group-item px-0">
                        <i class="fas fa-map-marker-alt me-2 text-primary"></i> <?= htmlspecialchars($user['location']) ?>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (isEmployer() && !empty($user['website'])): ?>
                    <li class="list-group-item px-0">
                        <i class="fas fa-globe me-2 text-primary"></i> 
                        <a href="<?= htmlspecialchars($user['website']) ?>" target="_blank"><?= htmlspecialchars(getDomain($user['website'])) ?></a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="list-group-item px-0">
                        <i class="fas fa-calendar me-2 text-primary"></i> Joined <?= date('M Y', strtotime($user['created_at'])) ?>
                    </li>
                </ul>
                
                <div class="d-grid">
                    <?php if (isSeeker()): ?>
                        <a href="resume.php" class="btn btn-outline-primary">
                            <i class="fas fa-file me-2"></i>Manage Resume
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Profile Actions -->
        <div class="list-group mt-3">
            <a href="#profile-settings" class="list-group-item list-group-item-action active">
                <i class="fas fa-user-edit me-2"></i> Edit Profile
            </a>
            <a href="#change-password" class="list-group-item list-group-item-action">
                <i class="fas fa-key me-2"></i> Change Password
            </a>
            <?php if (isSeeker()): ?>
                <a href="my_applications.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-clipboard-list me-2"></i> My Applications
                </a>
                <a href="saved_jobs.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-bookmark me-2"></i> Saved Jobs
                </a>
            <?php elseif (isEmployer()): ?>
                <a href="manage_jobs.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-briefcase me-2"></i> Manage Jobs
                </a>
                <a href="applicants.php" class="list-group-item list-group-item-action">
                    <i class="fas fa-users me-2"></i> Review Applicants
                </a>
            <?php endif; ?>
            <a href="account_settings.php" class="list-group-item list-group-item-action">
                <i class="fas fa-cog me-2"></i> Account Settings
            </a>
        </div>
    </div>
    
    <div class="col-lg-9">
        <!-- Edit Profile Form -->
        <div class="card mb-4" id="profile-settings">
            <div class="card-header bg-white">
                <h5 class="mb-0">Edit Profile</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <!-- Profile Image Upload -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Profile Picture</label>
                            <div class="text-center">
                                <div class="profile-image-preview mb-2">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" id="profile-preview" class="img-thumbnail">
                                    <?php else: ?>
                                        <img src="<?= SITE_URL ?>/assets/img/default-profile.png" id="profile-preview" class="img-thumbnail">
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp">
                                <div class="form-text">Max file size: 2MB. Allowed formats: JPG, PNG, GIF, WEBP</div>
                            </div>
                            
                            <?php if (!empty($user['profile_image'])): ?>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                <label class="form-check-label" for="remove_image">
                                    Remove current profile picture
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h6>Basic Information</h6>
                    <hr>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" placeholder="City, State, Country">
                        </div>
                    </div>
                    
                    <?php if (isEmployer()): ?>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($user['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="website" class="form-label">Company Website</label>
                            <input type="url" class="form-control" id="website" name="website" value="<?= htmlspecialchars($user['website'] ?? '') ?>" placeholder="https://example.com">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="bio" class="form-label">
                            <?= isEmployer() ? 'Company Description' : 'About Me' ?>
                        </label>
                        <textarea class="form-control" id="bio" name="bio" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    </div>
                    
                    <?php if (isSeeker()): ?>
                        <h6 class="mt-4">Professional Information</h6>
                        <hr>
                        
                        <div class="mb-3">
                            <label for="skills" class="form-label">Skills</label>
                            <textarea class="form-control" id="skills" name="skills" rows="3" placeholder="List your key skills separated by commas (e.g., PHP, JavaScript, Project Management)"><?= htmlspecialchars($user['skills'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="education" class="form-label">Education</label>
                            <textarea class="form-control" id="education" name="education" rows="3" placeholder="Your educational background"><?= htmlspecialchars($user['education'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="experience" class="form-label">Work Experience</label>
                            <textarea class="form-control" id="experience" name="experience" rows="3" placeholder="Your professional experience"><?= htmlspecialchars($user['experience'] ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Change Password Form -->
        <div class="card mb-4" id="change-password">
            <div class="card-header bg-white">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <form action="profile.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                            <button type="button" class="password-toggle btn btn-outline-secondary">
                                <i class="far fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Account Activity -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Account Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Activity</th>
                                <th>Date & Time</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch user activity log
                            $activities = $db->fetchAll("SELECT * FROM user_activity_log 
                                                      WHERE user_id = ? 
                                                      ORDER BY timestamp DESC 
                                                      LIMIT 5", 
                                                      [$userId]);
                                                      
                            if ($activities && count($activities) > 0):
                                foreach ($activities as $activity):
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($activity['activity_type']) ?></td>
                                    <td><?= date('M j, Y H:i:s', strtotime($activity['timestamp'])) ?></td>
                                    <td><?= htmlspecialchars($activity['ip_address']) ?></td>
                                </tr>
                            <?php 
                                endforeach;
                            else:
                            ?>
                                <tr>
                                    <td colspan="3" class="text-center">No recent activity found.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <!-- Add current login info -->
                            <tr>
                                <td>Current Login</td>
                                <td><?= date('M j, Y H:i:s') ?></td>
                                <td><?= $_SERVER['REMOTE_ADDR'] ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    <small class="text-muted">
                        Current Date and Time (UTC): <?= date('Y-m-d H:i:s') ?><br>
                        Current User's Login: <?= htmlspecialchars($user['name']) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Preview uploaded image
    document.addEventListener('DOMContentLoaded', function() {
        const profileImage = document.getElementById('profile_image');
        const profilePreview = document.getElementById('profile-preview');
        const removeImage = document.getElementById('remove_image');
        
        if (profileImage && profilePreview) {
            profileImage.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePreview.setAttribute('src', e.target.result);
                    }
                    reader.readAsDataURL(this.files[0]);
                    
                    if (removeImage) {
                        removeImage.checked = false;
                    }
                }
            });
        }
        
        if (removeImage && profilePreview) {
            removeImage.addEventListener('change', function() {
                if (this.checked) {
                    profilePreview.setAttribute('src', '<?= SITE_URL ?>/assets/img/default-profile.png');
                    if (profileImage) {
                        profileImage.value = '';
                    }
                }
            });
        }
        
        // Password toggle visibility
        const passwordToggles = document.querySelectorAll('.password-toggle');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
        
        // Scroll to section if hash in URL
        if (window.location.hash) {
            const targetElement = document.querySelector(window.location.hash);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
                
                // Update active tab
                document.querySelectorAll('.list-group-item').forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === window.location.hash) {
                        item.classList.add('active');
                    }
                });
            }
        }
        
        // Navigation click handling
        document.querySelectorAll('.list-group-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.list-group-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                if (this.getAttribute('href').startsWith('#')) {
                    const targetElement = document.querySelector(this.getAttribute('href'));
                    if (targetElement) {
                        window.scrollTo({
                            top: targetElement.offsetTop - 100,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>