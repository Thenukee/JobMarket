<?php
/**
 * Admin Panel - User Management
 * Manage, edit, and control user accounts across the AmmooJobs platform
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/session.php';


// Ensure only admins can access this page
requireAdmin();

// Page title
$pageTitle = 'Manage Users';

// Initialize variables
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$userType = isset($_GET['user_type']) ? sanitizeInput($_GET['user_type']) : 'all';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at';
$sortOrder = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: users.php');
        exit;
    }
    
    // Handle different form actions
    switch ($action) {
        case 'add':
            // Add new user
            $name = sanitizeInput($_POST['name']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            $userType = sanitizeInput($_POST['user_type']);
            $status = sanitizeInput($_POST['status']);
            $companyName = sanitizeInput($_POST['company_name'] ?? '');
            
            // Validate input
            if (empty($name) || empty($email)) {
                setFlashMessage('error', 'Name and email are required.');
                header('Location: users.php?action=add');
                exit;
            }
            
            if (!isValidEmail($email)) {
                setFlashMessage('error', 'Please enter a valid email address.');
                header('Location: users.php?action=add');
                exit;
            }
            
            // Check if email is already registered
            $existingUser = $db->fetchSingle("SELECT user_id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                setFlashMessage('error', 'Email address is already registered.');
                header('Location: users.php?action=add');
                exit;
            }
            
            // Validate password
            if (empty($password) || $password !== $confirmPassword) {
                setFlashMessage('error', 'Passwords do not match or are empty.');
                header('Location: users.php?action=add');
                exit;
            }
            
            // Validate password strength
            list($isValid, $passwordMessage) = validatePassword($password);
            if (!$isValid) {
                setFlashMessage('error', $passwordMessage);
                header('Location: users.php?action=add');
                exit;
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $result = $db->executeNonQuery(
                "INSERT INTO users (name, email, password, user_type, company_name, status, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [$name, $email, $hashedPassword, $userType, $companyName, $status]
            );
            
            if ($result) {
                $newUserId = $db->getLastId();
                
                // Log activity
                logSystemActivity('create', "Created new user: $name ($email)", $_SESSION['user_id']);
                
                setFlashMessage('success', 'User created successfully.');
                header('Location: users.php?action=view&id=' . $newUserId);
                exit;
            } else {
                setFlashMessage('error', 'Failed to create user.');
                header('Location: users.php?action=add');
                exit;
            }
            
        case 'edit':
            // Edit user
            $name = sanitizeInput($_POST['name']);
            $email = sanitizeInput($_POST['email']);
            $userType = sanitizeInput($_POST['user_type']);
            $status = sanitizeInput($_POST['status']);
            $companyName = sanitizeInput($_POST['company_name'] ?? '');
            $changePassword = isset($_POST['change_password']) ? (bool)$_POST['change_password'] : false;
            
            // Validate input
            if (empty($name) || empty($email)) {
                setFlashMessage('error', 'Name and email are required.');
                header("Location: users.php?action=edit&id=$userId");
                exit;
            }
            
            if (!isValidEmail($email)) {
                setFlashMessage('error', 'Please enter a valid email address.');
                header("Location: users.php?action=edit&id=$userId");
                exit;
            }
            
            // Check if email is already registered to another user
            $existingUser = $db->fetchSingle("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $userId]);
            if ($existingUser) {
                setFlashMessage('error', 'Email address is already registered to another user.');
                header("Location: users.php?action=edit&id=$userId");
                exit;
            }
            
            // Check if changing password
            if ($changePassword) {
                $password = $_POST['password'];
                $confirmPassword = $_POST['confirm_password'];
                
                if (empty($password) || $password !== $confirmPassword) {
                    setFlashMessage('error', 'Passwords do not match or are empty.');
                    header("Location: users.php?action=edit&id=$userId");
                    exit;
                }
                
                // Validate password strength
                list($isValid, $passwordMessage) = validatePassword($password);
                if (!$isValid) {
                    setFlashMessage('error', $passwordMessage);
                    header("Location: users.php?action=edit&id=$userId");
                    exit;
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Update user with password
                $result = $db->executeNonQuery(
                    "UPDATE users SET name = ?, email = ?, password = ?, user_type = ?, company_name = ?, status = ?, updated_at = NOW() WHERE user_id = ?",
                    [$name, $email, $hashedPassword, $userType, $companyName, $status, $userId]
                );
            } else {
                // Update user without changing password
                $result = $db->executeNonQuery(
                    "UPDATE users SET name = ?, email = ?, user_type = ?, company_name = ?, status = ?, updated_at = NOW() WHERE user_id = ?",
                    [$name, $email, $userType, $companyName, $status, $userId]
                );
            }
            
            if ($result) {
                // Log activity
                logSystemActivity('update', "Updated user ID #$userId: $name ($email)", $_SESSION['user_id']);
                
                setFlashMessage('success', 'User updated successfully.');
                header('Location: users.php?action=view&id=' . $userId);
                exit;
            } else {
                setFlashMessage('error', 'Failed to update user.');
                header("Location: users.php?action=edit&id=$userId");
                exit;
            }
            
        case 'delete':
            // Delete user
            if ($userId > 0) {
                // Check if trying to delete self
                if ($userId == $_SESSION['user_id']) {
                    setFlashMessage('error', 'You cannot delete your own account.');
                    header('Location: users.php');
                    exit;
                }
                
                // Get user info for logging
                $userInfo = $db->fetchSingle("SELECT name, email FROM users WHERE user_id = ?", [$userId]);
                
                $result = $db->executeNonQuery(
                    "DELETE FROM users WHERE user_id = ?",
                    [$userId]
                );
                
                if ($result) {
                    // Delete related data
                    // Applications
                    $db->executeNonQuery("DELETE FROM applications WHERE seeker_id = ?", [$userId]);
                    
                    // Job listings if employer
                    $db->executeNonQuery("DELETE FROM job_listings WHERE employer_id = ?", [$userId]);
                    
                    // Reviews
                    $db->executeNonQuery("DELETE FROM employer_reviews WHERE author_id = ?", [$userId]);
                    
                    // Log activity
                    logSystemActivity('delete', "Deleted user ID #$userId: " . ($userInfo ? $userInfo['name'] . ' (' . $userInfo['email'] . ')' : 'Unknown'), $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'User deleted successfully.');
                } else {
                    setFlashMessage('error', 'Failed to delete user.');
                }
            } else {
                setFlashMessage('error', 'Invalid user ID.');
            }
            header('Location: users.php');
            exit;
            
        case 'status':
            // Update user status
            $status = sanitizeInput($_POST['status']);
            
            if (!in_array($status, ['active', 'pending', 'suspended', 'inactive'])) {
                setFlashMessage('error', 'Invalid status.');
                header('Location: users.php');
                exit;
            }
            
            // Get user info for validation
            $userInfo = $db->fetchSingle("SELECT user_id FROM users WHERE user_id = ?", [$userId]);
            
            if (!$userInfo) {
                setFlashMessage('error', 'User not found.');
                header('Location: users.php');
                exit;
            }
            
            // Check if trying to suspend self
            if ($userId == $_SESSION['user_id'] && in_array($status, ['suspended', 'inactive'])) {
                setFlashMessage('error', 'You cannot suspend or deactivate your own account.');
                header('Location: users.php');
                exit;
            }
            
            $result = $db->executeNonQuery(
                "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ?",
                [$status, $userId]
            );
            
            if ($result) {
                // Log activity
                logSystemActivity('update', "Updated user ID #$userId status to $status", $_SESSION['user_id']);
                
                // Create notification for user
                $message = "Your account status has been updated to: " . ucfirst($status);
                addNotification($userId, 'account', $message);
                
                setFlashMessage('success', "User status updated to " . ucfirst($status) . " successfully.");
                
                // Redirect to appropriate page
                $redirectUrl = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'users.php';
                header("Location: $redirectUrl");
            } else {
                setFlashMessage('error', 'Failed to update user status.');
                header('Location: users.php');
            }
            exit;
  
        case 'bulk':
            // Bulk operations
            if (isset($_POST['user_ids']) && is_array($_POST['user_ids']) && !empty($_POST['user_ids'])) {
                $userIds = array_map('intval', $_POST['user_ids']);
                $operation = sanitizeInput($_POST['bulk_operation']);
                
                // Remove current admin from the list if present
                $userIds = array_filter($userIds, function($id) {
                    return $id != $_SESSION['user_id'];
                });
                
                if (empty($userIds)) {
                    setFlashMessage('error', 'No valid users selected for bulk operation.');
                    header('Location: users.php');
                    exit;
                }
                
                if ($operation === 'delete') {
                    // Delete selected users
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "DELETE FROM users WHERE user_id IN ($placeholders)",
                        $userIds
                    );
                    
                    if ($result) {
                        // Delete associated data
                        $db->executeNonQuery(
                            "DELETE FROM applications WHERE seeker_id IN ($placeholders)",
                            $userIds
                        );
                        
                        $db->executeNonQuery(
                            "DELETE FROM job_listings WHERE employer_id IN ($placeholders)",
                            $userIds
                        );
                        
                        $db->executeNonQuery(
                            "DELETE FROM employer_reviews WHERE author_id IN ($placeholders)",
                            $userIds
                        );
                        
                        // Log activity
                        logSystemActivity('delete', "Bulk deleted " . count($userIds) . " users", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($userIds) . ' users deleted successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to delete users.');
                    }
                } elseif (in_array($operation, ['activate', 'suspend'])) {
                    // Update status of selected users
                    $status = $operation === 'activate' ? 'active' : 'suspended';
                    
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    
                    $params = array_merge(
                        [$status],
                        $userIds
                    );
                    
                    $result = $db->executeNonQuery(
                        "UPDATE users SET status = ?, updated_at = NOW() WHERE user_id IN ($placeholders)",
                        $params
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk updated status to '$status' for " . count($userIds) . " users", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($userIds) . ' users updated successfully.');
                        
                        // Create notifications for all affected users
                        $message = "Your account status has been updated to: " . ucfirst($status);
                        foreach ($userIds as $id) {
                            addNotification($id, 'account', $message);
                        }
                    } else {
                        setFlashMessage('error', 'Failed to update users.');
                    }
                }
            } else {
                setFlashMessage('error', 'No users selected for bulk operation.');
            }
            header('Location: users.php?' . http_build_query(['filter' => $filter, 'user_type' => $userType, 'sort' => $sortBy, 'order' => $sortOrder, 'page' => $page]));
            exit;
    }
}

// Build SQL query based on filters
$params = [];
$whereClause = [];

// Search filter
if (!empty($search)) {
    $whereClause[] = "(name LIKE ? OR email LIKE ? OR company_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Status filter
if ($filter !== 'all') {
    $whereClause[] = "status = ?";
    $params[] = $filter;
}

// User type filter
if ($userType !== 'all') {
    $whereClause[] = "user_type = ?";
    $params[] = $userType;
}

// Complete where clause
$whereSQL = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM users $whereSQL";
$totalUsers = $db->fetchSingle($countQuery, $params)['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users with pagination, sorting
$usersQuery = "SELECT * FROM users $whereSQL ORDER BY $sortBy $sortOrder LIMIT $limit OFFSET $offset";
$users = $db->fetchAll($usersQuery, $params);

// Handle specific actions
switch ($action) {
    case 'view':
    case 'edit':
        if ($userId > 0) {
            // Get user details
            $user = $db->fetchSingle("SELECT * FROM users WHERE user_id = ?", [$userId]);
            
            if (!$user) {
                setFlashMessage('error', 'User not found.');
                header('Location: users.php');
                exit;
            }
            
            // Get additional data based on user type
            if ($user['user_type'] === 'employer') {
                // Get jobs posted
                $jobsPosted = $db->fetchAll(
                    "SELECT job_id, title, status, created_at, 
                    (SELECT COUNT(*) FROM applications WHERE job_id = j.job_id) as application_count
                    FROM job_listings j 
                    WHERE employer_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5", 
                    [$userId]
                );
                
                // Get company reviews
                $companyReviews = $db->fetchAll(
                    "SELECT r.*, u.name as author_name 
                    FROM employer_reviews r 
                    JOIN users u ON r.author_id = u.user_id 
                    WHERE r.employer_id = ? 
                    ORDER BY r.created_at DESC 
                    LIMIT 5", 
                    [$userId]
                );
                
                // Calculate average rating
                $avgRating = $db->fetchSingle(
                    "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
                    FROM employer_reviews 
                    WHERE employer_id = ? AND status = 'approved'", 
                    [$userId]
                );
            }
            
            if ($user['user_type'] === 'seeker') {
                // Get applications
                $applications = $db->fetchAll(
                    "SELECT a.*, j.title as job_title, u.company_name, u.name as employer_name 
                    FROM applications a 
                    JOIN job_listings j ON a.job_id = j.job_id 
                    JOIN users u ON j.employer_id = u.user_id 
                    WHERE a.seeker_id = ? 
                    ORDER BY a.applied_at DESC 
                    LIMIT 5", 
                    [$userId]
                );
                
                // Get resume info if available
                $resume = $db->fetchSingle(
                    "SELECT * FROM resumes WHERE user_id = ?", 
                    [$userId]
                );
            }
            
            // Get activity logs
            $activityLogs = $db->fetchAll(
                "SELECT * FROM system_activity_log 
                WHERE user_id = ? 
                ORDER BY timestamp DESC 
                LIMIT 10", 
                [$userId]
            );
        } else {
            setFlashMessage('error', 'Invalid user ID.');
            header('Location: users.php');
            exit;
        }
        break;
}

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <?php if ($action === 'list'): ?>
            Manage Users
        <?php elseif ($action === 'add'): ?>
            Add New User
        <?php elseif ($action === 'edit'): ?>
            Edit User
        <?php elseif ($action === 'view'): ?>
            User Details
        <?php endif; ?>
    </h1>

    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <?php if ($action === 'list'): ?>
            <li class="breadcrumb-item active">Users</li>
        <?php else: ?>
            <li class="breadcrumb-item"><a href="users.php">Users</a></li>
            <li class="breadcrumb-item active">
                <?php if ($action === 'add'): ?>Add New
                <?php elseif ($action === 'edit'): ?>Edit
                <?php elseif ($action === 'view'): ?>View
                <?php endif; ?>
            </li>
        <?php endif; ?>
    </ol>

    <?php if ($action === 'list'): ?>
        <!-- Users Table View -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-users me-1"></i>
                    Users
                </div>
                <div>
                    <a href="users.php?action=add" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Search and Filter Controls -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <form action="users.php" method="get" class="d-flex">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="user_type" value="<?= htmlspecialchars($userType) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                            <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-8 text-end">
                        <div class="btn-group me-2">
                            <a href="users.php?user_type=all&filter=<?= $filter ?>" class="btn btn-outline-secondary <?= $userType === 'all' ? 'active' : '' ?>">All Types</a>
                            <a href="users.php?user_type=seeker&filter=<?= $filter ?>" class="btn btn-outline-primary <?= $userType === 'seeker' ? 'active' : '' ?>">Job Seekers</a>
                            <a href="users.php?user_type=employer&filter=<?= $filter ?>" class="btn btn-outline-success <?= $userType === 'employer' ? 'active' : '' ?>">Employers</a>
                            <a href="users.php?user_type=admin&filter=<?= $filter ?>" class="btn btn-outline-danger <?= $userType === 'admin' ? 'active' : '' ?>">Admins</a>
                        </div>
                        <div class="btn-group">
                            <a href="users.php?filter=all&user_type=<?= $userType ?>" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All Status</a>
                            <a href="users.php?filter=active&user_type=<?= $userType ?>" class="btn btn-outline-success <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
                            <a href="users.php?filter=pending&user_type=<?= $userType ?>" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                            <a href="users.php?filter=suspended&user_type=<?= $userType ?>" class="btn btn-outline-danger <?= $filter === 'suspended' ? 'active' : '' ?>">Suspended</a>
                        </div>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No users found matching your criteria.
                    </div>
                <?php else: ?>
                    <!-- Users Table -->
                    <form action="users.php?action=bulk" method="post" id="usersForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="30">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=name&order=<?= $sortBy === 'name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Name
                                                <?php if ($sortBy === 'name'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=email&order=<?= $sortBy === 'email' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Email
                                                <?php if ($sortBy === 'email'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=company_name&order=<?= $sortBy === 'company_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Company
                                                <?php if ($sortBy === 'company_name'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=user_type&order=<?= $sortBy === 'user_type' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Type
                                                <?php if ($sortBy === 'user_type'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=created_at&order=<?= $sortBy === 'created_at' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Registered
                                                <?php if ($sortBy === 'created_at'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="users.php?filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=last_active&order=<?= $sortBy === 'last_active' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Last Active
                                                <?php if ($sortBy === 'last_active'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Status</th>
                                        <th width="160">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr <?= $user['user_id'] == $_SESSION['user_id'] ? 'class="table-active"' : '' ?>>
                                            <td>
                                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input user-checkbox" type="checkbox" name="user_ids[]" value="<?= $user['user_id'] ?>">
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php if (!empty($user['profile_image'])): ?>
                                                            <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="rounded-circle" width="36" height="36">
                                                        <?php else: ?>
                                                            <div class="avatar-placeholder rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                                <i class="fas fa-user"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <a href="users.php?action=view&id=<?= $user['user_id'] ?>" class="fw-bold">
                                                            <?= htmlspecialchars($user['name']) ?>
                                                        </a>
                                                        <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                                            <span class="badge bg-info ms-1">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php if (!empty($user['company_name'])): ?>
                                                    <?= htmlspecialchars($user['company_name']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['user_type'] === 'admin'): ?>
                                                    <span class="badge bg-danger">Administrator</span>
                                                <?php elseif ($user['user_type'] === 'employer'): ?>
                                                    <span class="badge bg-success">Employer</span>
                                                <?php elseif ($user['user_type'] === 'seeker'): ?>
                                                    <span class="badge bg-primary">Job Seeker</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatDate($user['created_at']) ?></td>
                                            <td>
                                                <?php if ($user['last_active']): ?>
                                                    <?= timeAgo($user['last_active']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Never</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($user['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($user['status'] === 'suspended'): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php elseif ($user['status'] === 'inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="users.php?action=view&id=<?= $user['user_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="users.php?action=edit&id=<?= $user['user_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <button type="button" class="btn btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $user['user_id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?= $user['user_id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $user['user_id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?= $user['user_id'] ?>">Delete User</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the user: <strong><?= htmlspecialchars($user['name']) ?></strong>?</p>
                                                                <p class="text-danger"><strong>Warning:</strong> This will also delete all content associated with this user and cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="users.php?action=delete" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                                    <input type="hidden" name="id" value="<?= $user['user_id'] ?>">
                                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <select name="bulk_operation" class="form-select" required>
                                        <option value="">-- Bulk Action --</option>
                                        <option value="activate">Activate Selected</option>
                                        <option value="suspend">Suspend Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary" id="bulkActionBtn" disabled onclick="return confirm('Are you sure you want to perform this action on the selected users?')">
                                        Apply
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted">Total: <?= number_format($totalUsers) ?> users</span>
                            </div>
                        </div>
                    </form>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?page=1&filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            First
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?page=<?= $page - 1 ?>&filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            &laquo;
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, min($page - 2, $totalPages - 4));
                                $endPage = min($startPage + 4, $totalPages);

                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="users.php?page=<?= $i ?>&filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?page=<?= $page + 1 ?>&filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            &raquo;
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="users.php?page=<?= $totalPages ?>&filter=<?= $filter ?>&user_type=<?= $userType ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            Last
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($action === 'view'): ?>
        <!-- User Details View -->
        <div class="row">
            <div class="col-lg-8">
                <!-- User Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-user-circle me-1"></i>
                            User Information
                        </div>
                        <div>
                            <div class="btn-group">
                                <a href="users.php?action=edit&id=<?= $userId ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i> Edit User
                                </a>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                <?php endif; ?>
                                <a href="../profile.php?id=<?= $userId ?>" class="btn btn-info btn-sm" target="_blank">
                                    <i class="fas fa-external-link-alt me-1"></i> View Public
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-3 text-center">
                                <div class="user-avatar mb-3">
                                    <?php if (!empty($user['profile_image'])): ?>
                                        <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="avatar-placeholder rounded-circle bg-light text-secondary d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;">
                                            <i class="fas fa-user fa-4x"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-9">
                                <h2 class="mb-1"><?= htmlspecialchars($user['name']) ?></h2>
                                <div class="mb-3">
                                    <?php if ($user['user_type'] === 'admin'): ?>
                                        <span class="badge bg-danger">Administrator</span>
                                    <?php elseif ($user['user_type'] === 'employer'): ?>
                                        <span class="badge bg-success">Employer</span>
                                    <?php elseif ($user['user_type'] === 'seeker'): ?>
                                        <span class="badge bg-primary">Job Seeker</span>
                                    <?php endif; ?>

                                    <?php if ($user['user_id'] == $_SESSION['user_id']): ?>
                                        <span class="badge bg-info">Current User</span>
                                    <?php endif; ?>
                                </div>

                                <!-- Status Information -->
                                <div class="alert <?= $user['status'] === 'active' ? 'alert-success' : ($user['status'] === 'pending' ? 'alert-warning' : ($user['status'] === 'suspended' ? 'alert-danger' : 'alert-secondary')) ?> d-flex align-items-center mb-3">
                                    <i class="fas <?= $user['status'] === 'active' ? 'fa-check-circle' : ($user['status'] === 'pending' ? 'fa-clock' : ($user['status'] === 'suspended' ? 'fa-ban' : 'fa-times-circle')) ?> me-2"></i>
                                    <div>
                                        <strong>Status: <?= ucfirst($user['status']) ?></strong>
                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                            <div class="mt-2">
                                                <?php if ($user['status'] !== 'active'): ?>
                                                    <form action="users.php?action=status" method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="id" value="<?= $userId ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <input type="hidden" name="redirect_url" value="users.php?action=view&id=<?= $userId ?>">
                                                        <button type="submit" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check me-1"></i> Activate Account
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['status'] !== 'suspended'): ?>
                                                    <form action="users.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to suspend this user?')">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="id" value="<?= $userId ?>">
                                                        <input type="hidden" name="status" value="suspended">
                                                        <input type="hidden" name="redirect_url" value="users.php?action=view&id=<?= $userId ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fas fa-ban me-1"></i> Suspend Account
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['status'] !== 'inactive'): ?>
                                                    <form action="users.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to deactivate this user?')">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <input type="hidden" name="id" value="<?= $userId ?>">
                                                        <input type="hidden" name="status" value="inactive">
                                                        <input type="hidden" name="redirect_url" value="users.php?action=view&id=<?= $userId ?>">
                                                        <button type="submit" class="btn btn-secondary btn-sm">
                                                            <i class="fas fa-times-circle me-1"></i> Deactivate Account
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Contact Info -->
                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Email:</strong> <a href="mailto:<?= htmlspecialchars($user['email']) ?>"><?= htmlspecialchars($user['email']) ?></a></p>
                                            <p class="mb-1"><strong>Phone:</strong> <?= !empty($user['phone']) ? htmlspecialchars($user['phone']) : '<span class="text-muted">Not provided</span>' ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p class="mb-1"><strong>Registered:</strong> <?= formatDateTime($user['created_at']) ?></p>
                                            <p class="mb-1"><strong>Last Active:</strong> <?= $user['last_active'] ? formatDateTime($user['last_active']) : '<span class="text-muted">Never</span>' ?></p>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($user['user_type'] === 'employer'): ?>
                                <!-- Company Information -->
                                <div class="mb-3">
                                    <h5>Company Information</h5>
                                    <p class="mb-1"><strong>Company:</strong> <?= !empty($user['company_name']) ? htmlspecialchars($user['company_name']) : '<span class="text-muted">Not provided</span>' ?></p>
                                    <p class="mb-1"><strong>Website:</strong> <?= !empty($user['website']) ? '<a href="' . htmlspecialchars($user['website']) . '" target="_blank">' . htmlspecialchars($user['website']) . '</a>' : '<span class="text-muted">Not provided</span>' ?></p>
                                    <p class="mb-1"><strong>Rating:</strong>
                                        <?php if (isset($avgRating) && $avgRating['review_count'] > 0): ?>
                                            <span class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= round($avgRating['avg_rating']) ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </span>
                                            <?= number_format($avgRating['avg_rating'], 1) ?>/5 
                                            (<?= $avgRating['review_count'] ?> <?= $avgRating['review_count'] === 1 ? 'review' : 'reviews' ?>)
                                        <?php else: ?>
                                            <span class="text-muted">No reviews yet</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($user['user_type'] === 'seeker'): ?>
                                <!-- Job Seeker Information -->
                                <div>
                                    <h5>Job Seeker Information</h5>
                                    <p class="mb-1"><strong>Resume:</strong>
                                        <?php if (isset($resume) && !empty($resume['file'])): ?>
                                            <a href="../uploads/resumes/<?= htmlspecialchars($resume['file']) ?>" target="_blank">
                                                <i class="far fa-file-pdf me-1"></i> View Resume
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">No resume uploaded</span>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-1"><strong>Job Applications:</strong>
                                        <?php 
                                        $applicationCount = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ?", [$userId])['count'];
                                        echo $applicationCount > 0 ? '<a href="applications.php?seeker_id=' . $userId . '">' . $applicationCount . ' application(s)</a>' : '<span class="text-muted">0 applications</span>';
                                        ?>
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($user['bio']): ?>
                        <div class="mb-4">
                            <h5>Bio</h5>
                            <div class="card-text">
                                <?= nl2br(htmlspecialchars($user['bio'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($user['user_type'] === 'employer' && isset($jobsPosted) && !empty($jobsPosted)): ?>
                        <!-- Recent Job Listings -->
                        <div class="mb-4">
                            <h5>Recent Job Listings</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Applications</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($jobsPosted as $job): ?>
                                            <tr>
                                                <td>
                                                    <a href="jobs.php?action=view&id=<?= $job['job_id'] ?>">
                                                        <?= htmlspecialchars($job['title']) ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <?php if ($job['status'] === 'active'): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php elseif ($job['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary"><?= ucfirst($job['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="applications.php?job_id=<?= $job['job_id'] ?>" class="badge bg-primary fw-normal">
                                                        <?= number_format($job['application_count']) ?>
                                                    </a>
                                                </td>
                                                <td><?= formatDate($job['created_at']) ?></td>
                                                <td>
                                                    <a href="jobs.php?action=view&id=<?= $job['job_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end">
                                <a href="jobs.php?search=<?= urlencode($user['company_name']) ?>" class="btn btn-sm btn-primary">
                                    View All Jobs
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user['user_type'] === 'seeker' && isset($applications) && !empty($applications)): ?>
                        <!-- Recent Applications -->
                        <div class="mb-4">
                            <h5>Recent Applications</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Job</th>
                                            <th>Company</th>
                                            <th>Applied</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $application): ?>
                                            <tr>
                                                <td>
                                                    <a href="jobs.php?action=view&id=<?= $application['job_id'] ?>">
                                                        <?= htmlspecialchars($application['job_title']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($application['company_name'] ?: $application['employer_name']) ?></td>
                                                <td><?= formatDate($application['applied_at']) ?></td>
                                                <td>
                                                    <?php if ($application['status'] === 'pending'): ?>
                                                        <span class="badge bg-warning text-dark">Pending</span>
                                                    <?php elseif ($application['status'] === 'reviewed'): ?>
                                                        <span class="badge bg-info">Reviewed</span>
                                                    <?php elseif ($application['status'] === 'shortlisted'): ?>
                                                        <span class="badge bg-primary">Shortlisted</span>
                                                    <?php elseif ($application['status'] === 'rejected'): ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php elseif ($application['status'] === 'offered'): ?>
                                                        <span class="badge bg-success">Offered</span>
                                                    <?php elseif ($application['status'] === 'accepted'): ?>
                                                        <span class="badge bg-success">Accepted</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="applications.php?action=view&id=<?= $application['application_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end">
                                <a href="applications.php?seeker_id=<?= $userId ?>" class="btn btn-sm btn-primary">
                                    View All Applications
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($user['user_type'] === 'employer' && isset($companyReviews) && !empty($companyReviews)): ?>
                        <!-- Recent Reviews -->
                        <div class="mb-4">
                            <h5>Recent Company Reviews</h5>
                            <div class="list-group">
                                <?php foreach ($companyReviews as $review): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                                <span class="ms-1"><?= $review['rating'] ?>/5</span>
                                            </div>
                                            <small class="text-muted"><?= formatDate($review['created_at']) ?></small>
                                        </div>
                                        <h6 class="mb-1"><?= htmlspecialchars($review['title']) ?></h6>
                                        <p class="mb-1 small text-truncate"><?= htmlspecialchars(truncateText($review['content'], 100)) ?></p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <small class="text-muted">By <?= htmlspecialchars($review['author_name']) ?></small>
                                            <a href="reviews.php?action=view&id=<?= $review['review_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                View Review
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-end mt-3">
                                <a href="reviews.php?search=<?= urlencode($user['company_name']) ?>" class="btn btn-sm btn-primary">
                                    View All Reviews
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- User Activity Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-history me-1"></i>
                        Recent Activity
                    </div>
                    <div class="card-body">
                        <?php if (!empty($activityLogs)): ?>
                            <div class="timeline">
                                <?php foreach ($activityLogs as $log): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker 
                                            <?php
                                                switch ($log['action']) {
                                                    case 'login': echo 'bg-success'; break;
                                                    case 'create': echo 'bg-primary'; break;
                                                    case 'update': echo 'bg-info'; break;
                                                    case 'delete': echo 'bg-danger'; break;
                                                    default: echo 'bg-secondary';
                                                }
                                            ?>">
                                            <i class="fas 
                                                <?php
                                                    switch ($log['action']) {
                                                        case 'login': echo 'fa-sign-in-alt'; break;
                                                        case 'create': echo 'fa-plus'; break;
                                                        case 'update': echo 'fa-edit'; break;
                                                        case 'delete': echo 'fa-trash'; break;
                                                        default: echo 'fa-cog';
                                                    }
                                                ?>">
                                            </i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="d-flex justify-content-between">
                                                <span class="timeline-title">
                                                    <?= ucfirst($log['action']) ?>
                                                </span>
                                                <span class="timeline-date text-muted">
                                                    <?= timeAgo($log['timestamp']) ?>
                                                </span>
                                            </div>
                                            <p class="mb-2"><?= htmlspecialchars($log['details']) ?></p>
                                            <div class="timeline-meta text-muted small">
                                                IP: <?= htmlspecialchars($log['ip_address']) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No recent activity recorded for this user.</p>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-end">
                        <a href="logs.php?user_id=<?= $userId ?>" class="btn btn-sm btn-outline-secondary">View Full Activity Log</a>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Account Actions Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-cog me-1"></i>
                        Account Actions
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="users.php?action=edit&id=<?= $userId ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-edit me-2 text-primary"></i> Edit User
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                            <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-envelope me-2 text-info"></i> Send Email
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                            <a href="impersonate.php?id=<?= $userId ?>" class="list-group-item list-group-item-action" onclick="return confirm('Are you sure you want to impersonate this user? You will be logged in as them until you end the impersonation.');">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="fas fa-user-secret me-2 text-warning"></i> Impersonate User
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </a>
                            <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                <a href="#" class="list-group-item list-group-item-action" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-key me-2 text-warning"></i> Reset Password
                                        </div>
                                        <i class="fas fa-chevron-right text-muted"></i>
                                    </div>
                                </a>
                                <?php if ($user['status'] !== 'suspended'): ?>
                                    <a href="#" class="list-group-item list-group-item-action text-danger" data-bs-toggle="modal" data-bs-target="#suspendUserModal">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-ban me-2"></i> Suspend Account
                                            </div>
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <form action="users.php?action=status" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <input type="hidden" name="id" value="<?= $userId ?>">
                                        <input type="hidden" name="status" value="active">
                                        <input type="hidden" name="redirect_url" value="users.php?action=view&id=<?= $userId ?>">
                                        <button type="submit" class="list-group-item list-group-item-action text-success border-0 w-100 text-start">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-check-circle me-2"></i> Reactivate Account
                                                </div>
                                                <i class="fas fa-chevron-right"></i>
                                            </div>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="#" class="list-group-item list-group-item-action text-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-trash-alt me-2"></i> Delete Account
                                            </div>
                                            <i class="fas fa-chevron-right"></i>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Account Info Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-1"></i>
                        Account Details
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <th width="40%">User ID:</th>
                                <td><?= $userId ?></td>
                            </tr>
                            <tr>
                                <th>Account Type:</th>
                                <td><?= ucfirst($user['user_type']) ?></td>
                            </tr>
                            <tr>
                                <th>Registration:</th>
                                <td><?= formatDateTime($user['created_at']) ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated:</th>
                                <td><?= formatDateTime($user['updated_at']) ?></td>
                            </tr>
                            <tr>
                                <th>Last Login:</th>
                                <td><?= $user['last_login'] ? formatDateTime($user['last_login']) : 'Never' ?></td>
                            </tr>
                            <tr>
                                <th>Last Active:</th>
                                <td><?= $user['last_active'] ? timeAgo($user['last_active']) : 'Never' ?></td>
                            </tr>
                            <tr>
                                <th>Status:</th>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($user['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($user['status'] === 'suspended'): ?>
                                        <span class="badge bg-danger">Suspended</span>
                                    <?php elseif ($user['status'] === 'inactive'): ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($user['user_type'] === 'employer'): ?>
                                <tr>
                                    <th>Job Listings:</th>
                                    <td>
                                        <?php 
                                        $jobCount = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE employer_id = ?", [$userId])['count'];
                                        echo $jobCount > 0 ? '<a href="jobs.php?search=' . urlencode($user['company_name']) . '">' . $jobCount . ' job(s)</a>' : '0 jobs';
                                        ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete User Modal -->
        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteUserModalLabel">Delete User Account</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the user account for <strong><?= htmlspecialchars($user['name']) ?></strong>?</p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This action will permanently delete this user and all associated data including:
                            <ul class="mb-0 mt-2">
                                <li>User profile and personal information</li>
                                <?php if ($user['user_type'] === 'employer'): ?>
                                    <li>All job listings posted by this employer</li>
                                    <li>Applications to this employer's jobs</li>
                                <?php endif; ?>
                                <?php if ($user['user_type'] === 'seeker'): ?>
                                    <li>All job applications submitted by this user</li>
                                    <li>Resume and other uploaded documents</li>
                                <?php endif; ?>
                                <li>Reviews and comments</li>
                            </ul>
                            <p class="mt-2 mb-0">This action cannot be undone.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="users.php?action=delete" method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $userId ?>">
                            <button type="submit" class="btn btn-danger">Delete User</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Suspend User Modal -->
        <div class="modal fade" id="suspendUserModal" tabindex="-1" aria-labelledby="suspendUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title" id="suspendUserModalLabel">Suspend User Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="users.php?action=status" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $userId ?>">
                            <input type="hidden" name="status" value="suspended">
                            <input type="hidden" name="redirect_url" value="users.php?action=view&id=<?= $userId ?>">
                            
                            <p>Are you sure you want to suspend <strong><?= htmlspecialchars($user['name']) ?></strong>'s account?</p>
                            <p>This will prevent the user from logging in and using the platform until reactivated.</p>
                            
                            <div class="mb-3">
                                <label for="suspend_reason" class="form-label">Suspension Reason (Optional):</label>
                                <textarea class="form-control" id="suspend_reason" name="suspend_reason" rows="3" placeholder="Provide a reason for suspension..."></textarea>
                                <div class="form-text">This information will be included in the notification to the user.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">Suspend User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Send Email Modal -->
        <div class="modal fade" id="sendEmailModal" tabindex="-1" aria-labelledby="sendEmailModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sendEmailModalLabel">Send Email to User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="email_user.php" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            
                            <div class="mb-3">
                                <label for="email_subject" class="form-label">Subject:</label>
                                <input type="text" class="form-control" id="email_subject" name="subject" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_template" class="form-label">Email Template:</label>
                                <select class="form-select" id="email_template" name="template">
                                    <option value="">-- No Template --</option>
                                    <option value="welcome">Welcome Message</option>
                                    <option value="verification">Account Verification</option>
                                    <option value="warning">Account Warning</option>
                                    <option value="password_reset">Password Reset</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email_message" class="form-label">Message:</label>
                                <textarea class="form-control" id="email_message" name="message" rows="6" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Send Email</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="resetPasswordModalLabel">Reset User Password</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="reset_password.php" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="user_id" value="<?= $userId ?>">
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="new_password" name="password" required>
                                    <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password:</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="notify_user" name="notify_user" value="1" checked>
                                    <label class="form-check-label" for="notify_user">
                                        Send email notification to user
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Add/Edit User Form -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-<?= $action === 'add' ? 'plus' : 'edit' ?> me-1"></i>
                <?= $action === 'add' ? 'Add New User' : 'Edit User' ?>
            </div>
            <div class="card-body">
                <form action="users.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $userId : '' ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required 
                                       value="<?= $action === 'edit' ? htmlspecialchars($user['name']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?= $action === 'edit' ? htmlspecialchars($user['email']) : '' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="user_type" name="user_type" required>
                                    <option value="seeker" <?= ($action === 'edit' && $user['user_type'] === 'seeker') ? 'selected' : '' ?>>
                                        Job Seeker
                                    </option>
                                    <option value="employer" <?= ($action === 'edit' && $user['user_type'] === 'employer') ? 'selected' : '' ?>>
                                        Employer
                                    </option>
                                    <option value="admin" <?= ($action === 'edit' && $user['user_type'] === 'admin') ? 'selected' : '' ?>>
                                        Administrator
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="status" class="form-label">Account Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?= ($action === 'edit' && $user['status'] === 'active') ? 'selected' : '' ?>>
                                        Active
                                    </option>
                                    <option value="pending" <?= ($action === 'edit' && $user['status'] === 'pending') ? 'selected' : '' ?>>
                                        Pending
                                    </option>
                                    <option value="suspended" <?= ($action === 'edit' && $user['status'] === 'suspended') ? 'selected' : '' ?>>
                                        Suspended
                                    </option>
                                    <option value="inactive" <?= ($action === 'edit' && $user['status'] === 'inactive') ? 'selected' : '' ?>>
                                        Inactive
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3 company-field">
                        <label for="company_name" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="company_name" name="company_name" 
                               value="<?= $action === 'edit' ? htmlspecialchars($user['company_name']) : '' ?>">
                        <div class="form-text">Required only for employer accounts.</div>
                    </div>
                    
                    <?php if ($action === 'add'): ?>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="change_password" name="change_password" value="1">
                            <label class="form-check-label" for="change_password">Change User Password</label>
                        </div>
                        
                        <div id="password_fields" class="row mb-3" style="display: none;">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="password" name="password">
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        <button class="btn btn-outline-secondary password-toggle-btn" type="button" title="Show password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between">
                        <a href="users.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> <?= $action === 'add' ? 'Create User' : 'Update User' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -1px;
    top: 20px;
    height: 100%;
    border-left: 2px solid #e9ecef;
}
.timeline-item:last-child::before {
    display: none;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    text-align: center;
    line-height: 20px;
    color: white;
    font-size: 0.7rem;
}
.timeline-title {
    font-weight: 600;
}
.timeline-date {
    font-size: 0.8rem;
}
.timeline-meta {
    margin-top: 5px;
    font-size: 0.8rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.user-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButton();
        });
    }
    
    // Individual checkbox change
    document.querySelectorAll('.user-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    // Enable/disable bulk action button
    function updateBulkActionButton() {
        const checkedBoxes = document.querySelectorAll('.user-checkbox:checked');
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        if (bulkActionBtn) {
            bulkActionBtn.disabled = checkedBoxes.length === 0;
        }
    }
    
    // Company field visibility based on user type
    const userTypeSelect = document.getElementById('user_type');
    if (userTypeSelect) {
        function updateCompanyField() {
            const companyField = document.querySelector('.company-field');
            if (companyField) {
                companyField.style.display = userTypeSelect.value === 'employer' ? 'block' : 'none';
            }
        }
        
        userTypeSelect.addEventListener('change', updateCompanyField);
        updateCompanyField(); // Run on page load
    }
    
    // Password fields toggle
    const changePasswordCheckbox = document.getElementById('change_password');
    const passwordFields = document.getElementById('password_fields');
    
    if (changePasswordCheckbox && passwordFields) {
        changePasswordCheckbox.addEventListener('change', function() {
            passwordFields.style.display = this.checked ? 'flex' : 'none';
        });
    }
    
    // Password visibility toggle
    document.querySelectorAll('.password-toggle-btn').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.closest('.input-group').querySelector('input');
            
            if (input.type === 'password') {
                input.type = 'text';
                this.innerHTML = '<i class="fas fa-eye-slash"></i>';
                this.setAttribute('title', 'Hide password');
            } else {
                input.type = 'password';
                this.innerHTML = '<i class="fas fa-eye"></i>';
                this.setAttribute('title', 'Show password');
            }
        });
    });
});
</script>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';

// Debug comment with current time and user
echo "<!-- Page generated at 2025-05-01 18:15:12 by HasinduNimesh -->";
?>