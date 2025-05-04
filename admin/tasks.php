<?php
// Start session at the very beginning - before ANY output
session_start();

/**
 * Admin Panel - Tasks Management
 * Central hub for managing pending tasks and items requiring attention
 * 
 * @version 1.0.0
 * @last_updated 2025-05-05
 */

// Start page timer for performance tracking
$pageStartTime = microtime(true);

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access this page
requireAdmin();

// Page title
$pageTitle = 'Tasks Management';

// Get active tab from URL or default to 'users'
$activeTab = isset($_GET['tab']) ? sanitizeInput($_GET['tab']) : 'users';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: tasks.php');
        exit;
    }
    
    // Handle different task actions
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_users':
                if (isset($_POST['user_ids']) && is_array($_POST['user_ids']) && !empty($_POST['user_ids'])) {
                    $userIds = array_map('intval', $_POST['user_ids']);
                    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "UPDATE users SET status = 'active', updated_at = NOW() WHERE user_id IN ($placeholders)",
                        $userIds
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk approved " . count($userIds) . " users", $_SESSION['user_id']);
                        
                        // Create notifications for all approved users
                        foreach ($userIds as $id) {
                            addNotification($id, 'account', "Your account has been approved. Welcome to " . SITE_NAME . "!");
                        }
                        
                        setFlashMessage('success', count($userIds) . ' users approved successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to approve users.');
                    }
                } else {
                    setFlashMessage('error', 'No users selected.');
                }
                break;
                
            case 'approve_jobs':
                if (isset($_POST['job_ids']) && is_array($_POST['job_ids']) && !empty($_POST['job_ids'])) {
                    $jobIds = array_map('intval', $_POST['job_ids']);
                    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "UPDATE job_listings SET status = 'active', updated_at = NOW() WHERE job_id IN ($placeholders)",
                        $jobIds
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk approved " . count($jobIds) . " jobs", $_SESSION['user_id']);
                        
                        // Get employer IDs for notifications
                        $jobDetails = $db->fetchAll("SELECT job_id, employer_id, title FROM job_listings WHERE job_id IN ($placeholders)", $jobIds);
                        
                        // Create notifications for employers
                        foreach ($jobDetails as $job) {
                            addNotification($job['employer_id'], 'job', "Your job listing '{$job['title']}' has been approved and is now live.");
                        }
                        
                        setFlashMessage('success', count($jobIds) . ' jobs approved successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to approve jobs.');
                    }
                } else {
                    setFlashMessage('error', 'No jobs selected.');
                }
                break;
                
            case 'approve_reviews':
                if (isset($_POST['review_ids']) && is_array($_POST['review_ids']) && !empty($_POST['review_ids'])) {
                    $reviewIds = array_map('intval', $_POST['review_ids']);
                    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
                    
                    // First, get the data for notifications
                    $reviewData = $db->fetchAll(
                        "SELECT r.review_id, r.employer_id, r.author_id, e.name as employer_name
                         FROM employer_reviews r
                         JOIN users e ON r.employer_id = e.user_id
                         WHERE r.review_id IN ($placeholders)", 
                        $reviewIds
                    );
                    
                    $result = $db->executeNonQuery(
                        "UPDATE employer_reviews SET 
                         status = 'approved', moderated_by = ?, moderated_at = NOW(), updated_at = NOW()
                         WHERE review_id IN ($placeholders)",
                        array_merge([$_SESSION['user_id']], $reviewIds)
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk approved " . count($reviewIds) . " employer reviews", $_SESSION['user_id']);
                        
                        // Create notifications
                        foreach ($reviewData as $review) {
                            // Notify employer
                            addNotification($review['employer_id'], 'review', "A new review for your company has been published.");
                            // Notify reviewer
                            addNotification($review['author_id'], 'review', "Your review for {$review['employer_name']} has been approved.");
                        }
                        
                        setFlashMessage('success', count($reviewIds) . ' reviews approved successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to approve reviews.');
                    }
                } else {
                    setFlashMessage('error', 'No reviews selected.');
                }
                break;
                
            case 'resolve_reports':
                if (isset($_POST['report_ids']) && is_array($_POST['report_ids']) && !empty($_POST['report_ids'])) {
                    $reportIds = array_map('intval', $_POST['report_ids']);
                    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "UPDATE reported_content SET 
                         status = 'resolved', resolved_by = ?, resolved_at = NOW(), updated_at = NOW()
                         WHERE report_id IN ($placeholders)",
                        array_merge([$_SESSION['user_id']], $reportIds)
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk resolved " . count($reportIds) . " reported content items", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($reportIds) . ' reports marked as resolved.');
                    } else {
                        setFlashMessage('error', 'Failed to resolve reports.');
                    }
                } else {
                    setFlashMessage('error', 'No reports selected.');
                }
                break;
        }
    }
    
    // Redirect back to the appropriate tab
    header('Location: tasks.php?tab=' . $activeTab);
    exit;
}

// Fetch counts for all tabs
$pendingUserCount = $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE status = 'pending'", [])['count'] ?? 0;
$pendingJobCount = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE status = 'pending'", [])['count'] ?? 0;
$pendingReviewCount = $db->fetchSingle("SELECT COUNT(*) as count FROM employer_reviews WHERE status = 'pending'", [])['count'] ?? 0;
$reportedContentCount = $db->fetchSingle("SELECT COUNT(*) as count FROM reported_content WHERE status = 'pending'", [])['count'] ?? 0;

// Fetch task data based on active tab
switch ($activeTab) {
    case 'users':
        // Get pending users
        $whereClause = ["status = 'pending'"];
        $params = [];
        
        // Add search filter if provided
        if (!empty($search)) {
            $whereClause[] = "(name LIKE ? OR email LIKE ? OR company_name LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }
        
        $whereSQL = implode(' AND ', $whereClause);
        $totalItems = $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE $whereSQL", $params)['count'] ?? 0;
        $totalPages = ceil($totalItems / $limit);
        
        $tasks = $db->fetchAll(
            "SELECT * FROM users 
             WHERE $whereSQL
             ORDER BY created_at DESC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        break;
        
    case 'jobs':
        // Get pending jobs
        $whereClause = ["j.status = 'pending'"];
        $params = [];
        
        // Add search filter if provided
        if (!empty($search)) {
            $whereClause[] = "(j.title LIKE ? OR j.location LIKE ? OR u.company_name LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
        }
        
        $whereSQL = implode(' AND ', $whereClause);
        $totalItems = $db->fetchSingle(
            "SELECT COUNT(*) as count 
             FROM job_listings j 
             LEFT JOIN users u ON j.employer_id = u.user_id 
             WHERE $whereSQL", 
            $params
        )['count'] ?? 0;
        $totalPages = ceil($totalItems / $limit);
        
        $tasks = $db->fetchAll(
            "SELECT j.*, u.name as employer_name, u.company_name, u.email as employer_email 
             FROM job_listings j 
             LEFT JOIN users u ON j.employer_id = u.user_id 
             WHERE $whereSQL
             ORDER BY j.created_at DESC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        break;
        
    case 'reviews':
        // Get pending reviews
        $whereClause = ["r.status = 'pending'"];
        $params = [];
        
        // Add search filter if provided
        if (!empty($search)) {
            $whereClause[] = "(r.review_title LIKE ? OR r.review_content LIKE ? OR e.company_name LIKE ? OR a.name LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        
        $whereSQL = implode(' AND ', $whereClause);
        $totalItems = $db->fetchSingle(
            "SELECT COUNT(*) as count 
             FROM employer_reviews r
             LEFT JOIN users e ON r.employer_id = e.user_id
             LEFT JOIN users a ON r.author_id = a.user_id
             WHERE $whereSQL", 
            $params
        )['count'] ?? 0;
        $totalPages = ceil($totalItems / $limit);
        
        $tasks = $db->fetchAll(
            "SELECT r.*, e.name as employer_name, e.company_name, a.name as author_name, a.email as author_email
             FROM employer_reviews r
             LEFT JOIN users e ON r.employer_id = e.user_id
             LEFT JOIN users a ON r.author_id = a.user_id
             WHERE $whereSQL
             ORDER BY r.created_at DESC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        break;
        
    case 'reports':
        // Get reported content
        $whereClause = ["r.status = 'pending'"];
        $params = [];
        
        // Add search filter if provided
        if (!empty($search)) {
            $whereClause[] = "(r.reason LIKE ? OR r.details LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
            $params = array_merge($params, ["%$search%", "%$search%", "%$search%", "%$search%"]);
        }
        
        $whereSQL = implode(' AND ', $whereClause);
        $totalItems = $db->fetchSingle(
            "SELECT COUNT(*) as count 
             FROM reported_content r
             LEFT JOIN users u ON r.reporter_id = u.user_id
             WHERE $whereSQL", 
            $params
        )['count'] ?? 0;
        $totalPages = ceil($totalItems / $limit);
        
        $tasks = $db->fetchAll(
            "SELECT r.*, u.name as reporter_name, u.email as reporter_email
             FROM reported_content r
             LEFT JOIN users u ON r.reporter_id = u.user_id
             WHERE $whereSQL
             ORDER BY r.created_at DESC 
             LIMIT $limit OFFSET $offset",
            $params
        );
        break;
        
    default:
        // Default to users tab if invalid tab specified
        header('Location: tasks.php?tab=users');
        exit;
}

// Include header
//include_once '../includes/admin_header.php';
?>
    <?php include_once '../includes/admin_header.php'; ?>



<div class="container-fluid px-4">
    <h1 class="mt-4">Tasks Management</h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <li class="breadcrumb-item active">Tasks</li>
    </ol>
    
    <!-- Log Navigation Tabs for Tasks -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" href="tasks.php?tab=users">
                <i class="fas fa-users me-1"></i> Pending Users 
                <?php if ($pendingUserCount > 0): ?>
                    <span class="badge bg-primary rounded-pill"><?= $pendingUserCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'jobs' ? 'active' : '' ?>" href="tasks.php?tab=jobs">
                <i class="fas fa-briefcase me-1"></i> Pending Jobs
                <?php if ($pendingJobCount > 0): ?>
                    <span class="badge bg-success rounded-pill"><?= $pendingJobCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'reviews' ? 'active' : '' ?>" href="tasks.php?tab=reviews">
                <i class="fas fa-star me-1"></i> Pending Reviews
                <?php if ($pendingReviewCount > 0): ?>
                    <span class="badge bg-info rounded-pill"><?= $pendingReviewCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'reports' ? 'active' : '' ?>" href="tasks.php?tab=reports">
                <i class="fas fa-flag me-1"></i> Reported Content
                <?php if ($reportedContentCount > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $reportedContentCount ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>
    
    <!-- Tasks Card -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <?php if ($activeTab === 'users'): ?>
                    <i class="fas fa-users me-1"></i> Pending User Approvals
                <?php elseif ($activeTab === 'jobs'): ?>
                    <i class="fas fa-briefcase me-1"></i> Pending Job Approvals
                <?php elseif ($activeTab === 'reviews'): ?>
                    <i class="fas fa-star me-1"></i> Pending Review Approvals
                <?php elseif ($activeTab === 'reports'): ?>
                    <i class="fas fa-flag me-1"></i> Reported Content
                <?php endif; ?>
            </div>
            <div>
                <?php if ($activeTab === 'users' && $pendingUserCount > 0): ?>
                    <a href="users.php?filter=pending" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-cog me-1"></i> Advanced Management
                    </a>
                <?php elseif ($activeTab === 'jobs' && $pendingJobCount > 0): ?>
                    <a href="jobs.php?filter=pending" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-cog me-1"></i> Advanced Management
                    </a>
                <?php elseif ($activeTab === 'reviews' && $pendingReviewCount > 0): ?>
                    <a href="reviews.php?filter=pending" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-cog me-1"></i> Advanced Management
                    </a>
                <?php elseif ($activeTab === 'reports' && $reportedContentCount > 0): ?>
                    <a href="reports.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-cog me-1"></i> Advanced Management
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body">
            <!-- Search and Filter Form -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <form method="get" class="d-flex">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" id="search" name="search" 
                                placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 d-flex justify-content-end align-items-center">
                    <div class="me-3">
                        <form method="get" class="d-inline">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <select class="form-select form-select-sm d-inline-block" style="width: auto;" id="limit" name="limit" onchange="this.form.submit()">
                                <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10 items</option>
                                <option value="20" <?= $limit === 20 ? 'selected' : '' ?>>20 items</option>
                                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50 items</option>
                                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100 items</option>
                            </select>
                        </form>
                    </div>
                    <?php if (!empty($search)): ?>
                        <a href="?tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-times me-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($tasks)): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-info-circle me-2 fa-lg"></i>
                    <div>
                        No pending <?= $activeTab === 'users' ? 'user registrations' : ($activeTab === 'jobs' ? 'job listings' : ($activeTab === 'reviews' ? 'reviews' : 'reports')) ?> to display.
                    </div>
                </div>
            <?php else: ?>
                <!-- Task Tables for different tabs -->
                <?php if ($activeTab === 'users'): ?>
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="approve_users">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input select-all" type="checkbox" id="selectAllUsers">
                                            </div>
                                        </th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Type</th>
                                        <th>Registered</th>
                                        <th width="180">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input task-checkbox" type="checkbox" name="user_ids[]" value="<?= $user['user_id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($user['name']) ?>
                                                <?php if (!empty($user['company_name'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($user['company_name']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <?php if ($user['user_type'] === 'employer'): ?>
                                                    <span class="badge bg-primary">Employer</span>
                                                <?php elseif ($user['user_type'] === 'seeker'): ?>
                                                    <span class="badge bg-success">Job Seeker</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?= ucfirst($user['user_type']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatDateTime($user['created_at'], 'M j, Y') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="users.php?action=view&id=<?= $user['user_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="users.php?action=edit&id=<?= $user['user_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-success user-approve-btn" 
                                                       data-id="<?= $user['user_id'] ?>" 
                                                       data-name="<?= htmlspecialchars($user['name']) ?>"
                                                       title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="users.php?action=status&id=<?= $user['user_id'] ?>&status=rejected" 
                                                       class="btn btn-danger" 
                                                       onclick="return confirm('Are you sure you want to reject this user?')"
                                                       title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success bulk-action-btn" disabled>
                                <i class="fas fa-check me-1"></i> Approve Selected
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($activeTab === 'jobs'): ?>
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="approve_jobs">
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input select-all" type="checkbox" id="selectAllJobs">
                                            </div>
                                        </th>
                                        <th>Job Title</th>
                                        <th>Employer</th>
                                        <th>Location</th>
                                        <th>Posted</th>
                                        <th width="180">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $job): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input task-checkbox" type="checkbox" name="job_ids[]" value="<?= $job['job_id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($job['title']) ?>
                                                <div class="small text-muted">
                                                    <?php 
                                                    $jobTypes = [
                                                        'full_time' => 'Full Time',
                                                        'part_time' => 'Part Time',
                                                        'contract' => 'Contract',
                                                        'remote' => 'Remote'
                                                    ];
                                                    echo $jobTypes[$job['job_type']] ?? ucfirst($job['job_type']);
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                                <div class="small text-muted"><?= htmlspecialchars($job['employer_email']) ?></div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($job['location']) ?>
                                                <?php if ($job['is_remote']): ?>
                                                    <span class="badge bg-info ms-1">Remote</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= formatDateTime($job['created_at'], 'M j, Y') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="jobs.php?action=view&id=<?= $job['job_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="jobs.php?action=edit&id=<?= $job['job_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-success job-approve-btn" 
                                                       data-id="<?= $job['job_id'] ?>" 
                                                       data-title="<?= htmlspecialchars($job['title']) ?>"
                                                       title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="jobs.php?action=status&id=<?= $job['job_id'] ?>&status=rejected" 
                                                       class="btn btn-danger" 
                                                       onclick="return confirm('Are you sure you want to reject this job?')"
                                                       title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success bulk-action-btn" disabled>
                                <i class="fas fa-check me-1"></i> Approve Selected
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($activeTab === 'reviews'): ?>
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="approve_reviews">
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input select-all" type="checkbox" id="selectAllReviews">
                                            </div>
                                        </th>
                                        <th>Company</th>
                                        <th>Rating</th>
                                        <th>Review Content</th>
                                        <th>Author</th>
                                        <th width="180">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $review): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input task-checkbox" type="checkbox" name="review_ids[]" value="<?= $review['review_id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?>
                                                <div class="small text-muted"><?= formatDateTime($review['created_at'], 'M j, Y') ?></div>
                                            </td>
                                            <td>
                                                <div class="text-warning">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['rating']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php elseif ($i - 0.5 <= $review['rating']): ?>
                                                            <i class="fas fa-star-half-alt"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="small text-muted"><?= $review['rating'] ?>/5</div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($review['review_title']) ?></strong>
                                                <p class="mb-0 text-muted text-truncate" style="max-width: 250px;">
                                                    <?= htmlspecialchars($review['review_content']) ?>
                                                </p>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($review['author_name']) ?>
                                                <div class="small text-muted"><?= htmlspecialchars($review['author_email']) ?></div>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="reviews.php?action=view&id=<?= $review['review_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="reviews.php?action=edit&id=<?= $review['review_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-success review-approve-btn" 
                                                       data-id="<?= $review['review_id'] ?>" 
                                                       data-company="<?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?>"
                                                       title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="reviews.php?action=status&id=<?= $review['review_id'] ?>&status=rejected" 
                                                       class="btn btn-danger" 
                                                       onclick="return confirm('Are you sure you want to reject this review?')"
                                                       title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success bulk-action-btn" disabled>
                                <i class="fas fa-check me-1"></i> Approve Selected
                            </button>
                        </div>
                    </form>
                    
                <?php elseif ($activeTab === 'reports'): ?>
                    <form action="tasks.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="resolve_reports">
                        
                        <div class="table-responsive">
                            <table class="table table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40">
                                            <div class="form-check">
                                                <input class="form-check-input select-all" type="checkbox" id="selectAllReports">
                                            </div>
                                        </th>
                                        <th>Content Type</th>
                                        <th>Reason</th>
                                        <th>Details</th>
                                        <th>Reported By</th>
                                        <th width="180">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $report): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input task-checkbox" type="checkbox" name="report_ids[]" value="<?= $report['report_id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $contentTypes = [
                                                    'job' => '<i class="fas fa-briefcase text-primary"></i> Job Listing',
                                                    'review' => '<i class="fas fa-star text-warning"></i> Review',
                                                    'user' => '<i class="fas fa-user text-info"></i> User Profile',
                                                    'message' => '<i class="fas fa-envelope text-success"></i> Message',
                                                    'comment' => '<i class="fas fa-comment text-secondary"></i> Comment'
                                                ];
                                                echo $contentTypes[$report['content_type']] ?? '<i class="fas fa-file text-muted"></i> ' . ucfirst($report['content_type']);
                                                ?>
                                                <div class="small text-muted">ID: <?= $report['content_id'] ?></div>
                                            </td>
                                            <td>
                                                <?php
                                                $reasons = [
                                                    'inappropriate' => '<span class="badge bg-danger">Inappropriate Content</span>',
                                                    'spam' => '<span class="badge bg-warning text-dark">Spam</span>',
                                                    'harassment' => '<span class="badge bg-danger">Harassment</span>',
                                                    'fake' => '<span class="badge bg-secondary">Fake/Misleading</span>'
                                                ];
                                                echo $reasons[$report['reason']] ?? '<span class="badge bg-info">' . ucfirst($report['reason']) . '</span>';
                                                ?>
                                                <div class="small text-muted"><?= formatDateTime($report['created_at'], 'M j, Y') ?></div>
                                            </td>
                                            <td>
                                                <p class="mb-0 text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($report['details']) ?>">
                                                    <?= htmlspecialchars($report['details']) ?>
                                                </p>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($report['reporter_name'] ?: 'Anonymous') ?>
                                                <?php if ($report['reporter_name']): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($report['reporter_email']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-info report-view-btn" 
                                                       data-id="<?= $report['report_id'] ?>"
                                                       data-details="<?= htmlspecialchars($report['details']) ?>"
                                                       data-reason="<?= htmlspecialchars($report['reason']) ?>"
                                                       data-type="<?= htmlspecialchars($report['content_type']) ?>"
                                                       data-content-id="<?= $report['content_id'] ?>"
                                                       title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-primary report-investigate-btn" 
                                                       data-content-type="<?= $report['content_type'] ?>"
                                                       data-content-id="<?= $report['content_id'] ?>"
                                                       title="Investigate Content">
                                                        <i class="fas fa-search"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-success report-resolve-btn" 
                                                       data-id="<?= $report['report_id'] ?>"
                                                       title="Mark as Resolved">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-danger report-delete-btn" 
                                                       data-id="<?= $report['report_id'] ?>"
                                                       title="Delete Report">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" class="btn btn-success bulk-action-btn" disabled>
                                <i class="fas fa-check me-1"></i> Mark Selected as Resolved
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=<?= $activeTab ?>&page=1&search=<?= urlencode($search) ?>&limit=<?= $limit ?>" tabindex="<?= ($page <= 1) ? '-1' : '' ?>" <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>" tabindex="<?= ($page <= 1) ? '-1' : '' ?>" <?= ($page <= 1) ? 'aria-disabled="true"' : '' ?>>
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            // Calculate pagination range
                            $startPage = max(1, $page - 2);
                            $endPage = min($startPage + 4, $totalPages);
                            if ($endPage - $startPage < 4 && $startPage > 1) {
                                $startPage = max(1, $endPage - 4);
                            }
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>" tabindex="<?= ($page >= $totalPages) ? '-1' : '' ?>" <?= ($page >= $totalPages) ? 'aria-disabled="true"' : '' ?>>
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                            <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
                                <a class="page-link" href="?tab=<?= $activeTab ?>&page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&limit=<?= $limit ?>" tabindex="<?= ($page >= $totalPages) ? '-1' : '' ?>" <?= ($page >= $totalPages) ? 'aria-disabled="true"' : '' ?>>
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($tasks)): ?>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">
                        Showing <?= count($tasks) ?> of <?= number_format($totalItems) ?> total items
                    </small>
                </div>
                <div>
                    <?php if ($activeTab === 'users'): ?>
                        <a href="users.php?filter=pending" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-cog me-1"></i> Advanced Management
                        </a>
                    <?php elseif ($activeTab === 'jobs'): ?>
                        <a href="jobs.php?filter=pending" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-cog me-1"></i> Advanced Management
                        </a>
                    <?php elseif ($activeTab === 'reviews'): ?>
                        <a href="reviews.php?filter=pending" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-cog me-1"></i> Advanced Management
                        </a>
                    <?php elseif ($activeTab === 'reports'): ?>
                        <a href="reports.php" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-cog me-1"></i> Advanced Management
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modals for Content Details -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1" aria-labelledby="reportDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="reportDetailsModalLabel">
                    <i class="fas fa-flag me-2 text-danger"></i>
                    Report Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <h6 class="fw-bold">Report Type</h6>
                    <p id="reportContentType" class="mb-0 p-2 bg-light rounded"></p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">Reason</h6>
                    <p id="reportReason" class="mb-0 p-2 bg-light rounded"></p>
                </div>
                <div class="mb-4">
                    <h6 class="fw-bold">Details</h6>
                    <div class="p-3 bg-light rounded" id="reportDetails"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Close
                </button>
                <a href="#" id="investigateLink" class="btn btn-primary">
                    <i class="fas fa-search me-1"></i> View Content
                </a>
                <a href="#" id="resolveLink" class="btn btn-success"></a>
                    <i class="fas fa-check me-1"></i> Mark as Resolved
                </a>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Task Management -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all functionality
    document.querySelectorAll('.select-all').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const checkboxes = this.closest('table').querySelectorAll('.task-checkbox');
            checkboxes.forEach(box => box.checked = this.checked);
            updateBulkActionButtons();
        });
    });

    // Task checkbox change
    document.querySelectorAll('.task-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButtons);
    });

    // Update bulk action buttons state
    function updateBulkActionButtons() {
        document.querySelectorAll('.bulk-action-btn').forEach(btn => {
            const form = btn.closest('form');
            const checkedBoxes = form.querySelectorAll('.task-checkbox:checked');
            btn.disabled = checkedBoxes.length === 0;
        });
    }
    
    // User approve button
    document.querySelectorAll('.user-approve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const userId = this.dataset.id;
            const userName = this.dataset.name;
            
            if (confirm(`Are you sure you want to approve user "${userName}"?`)) {
                window.location.href = `users.php?action=status&id=${userId}&status=active`;
            }
        });
    });
    
    // Job approve button
    document.querySelectorAll('.job-approve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const jobId = this.dataset.id;
            const jobTitle = this.dataset.title;
            
            if (confirm(`Are you sure you want to approve job "${jobTitle}"?`)) {
                window.location.href = `jobs.php?action=status&id=${jobId}&status=active`;
            }
        });
    });
    
    // Review approve button
    document.querySelectorAll('.review-approve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const reviewId = this.dataset.id;
            const company = this.dataset.company;
            
            if (confirm(`Are you sure you want to approve this review for "${company}"?`)) {
                window.location.href = `reviews.php?action=status&id=${reviewId}&status=approved`;
            }
        });
    });
    
    // Report view button
    document.querySelectorAll('.report-view-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const reportId = this.dataset.id;
            const contentType = this.dataset.type;
            const contentId = this.dataset.contentId;
            const reason = this.dataset.reason;
            const details = this.dataset.details;
            
            document.getElementById('reportContentType').textContent = `${contentType.charAt(0).toUpperCase() + contentType.slice(1)} (ID: ${contentId})`;
            document.getElementById('reportReason').textContent = reason.charAt(0).toUpperCase() + reason.slice(1);
            document.getElementById('reportDetails').textContent = details;
            
            // Update action links
            document.getElementById('investigateLink').href = getContentUrl(contentType, contentId);
            document.getElementById('resolveLink').href = `reports.php?action=resolve&id=${reportId}`;
            
            const modal = new bootstrap.Modal(document.getElementById('reportDetailsModal'));
            modal.show();
        });
    });
    
    // Report investigate button
    document.querySelectorAll('.report-investigate-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const contentType = this.dataset.contentType;
            const contentId = this.dataset.contentId;
            
            window.location.href = getContentUrl(contentType, contentId);
        });
    });
    
    // Report resolve button
    document.querySelectorAll('.report-resolve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const reportId = this.dataset.id;
            
            if (confirm('Are you sure you want to mark this report as resolved?')) {
                window.location.href = `reports.php?action=resolve&id=${reportId}`;
            }
        });
    });
    
    // Report delete button
    document.querySelectorAll('.report-delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const reportId = this.dataset.id;
            
            if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                window.location.href = `reports.php?action=delete&id=${reportId}`;
            }
        });
    });
    
    // Helper function to get content URL based on type and ID
    function getContentUrl(contentType, contentId) {
        switch (contentType) {
            case 'job':
                return `jobs.php?action=view&id=${contentId}`;
            case 'review':
                return `reviews.php?action=view&id=${contentId}`;
            case 'user':
                return `users.php?action=view&id=${contentId}`;
            default:
                return '#';
        }
    }
});
</script>

<style>
/* Additional styling for Tasks page */
.card-value {
    font-size: 2rem;
    font-weight: 700;
    line-height: 1;
}

.card-name {
    font-size: 0.9rem;
    opacity: 0.8;
}

.card-icon {
    opacity: 0.8;
}

.table th {
    font-weight: 600;
}

.badge {
    font-weight: 500;
}

/* Border start utility classes */
.border-start-4 {
    border-left-width: 4px !important;
}

.border-start-primary {
    border-left-color: #0d6efd !important;
}

.border-start-success {
    border-left-color: #198754 !important;
}

.border-start-info {
    border-left-color: #0dcaf0 !important;
}

.border-start-danger {
    border-left-color: #dc3545 !important;
}
</style>

<?php
// Calculate page load time
$pageLoadTime = round(microtime(true) - $pageStartTime, 4);

// Include footer
include_once '../includes/admin_footer.php';

// Debug comment
echo "<!-- Page generated at " . date('Y-m-d H:i:s') . " by " . htmlspecialchars($_SESSION['name'] ?? 'Admin') . " in " . $pageLoadTime . " seconds -->";
?>
