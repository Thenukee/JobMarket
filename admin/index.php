<?php
// Start session at the very beginning - before ANY output
session_start();

/**
 * Admin Dashboard - Main Index Page
 * Central hub for AmmooJobs platform administration
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start page timer for performance tracking
$pageStartTime = microtime(true);

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Ensure only admins can access this page
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php?redirect=admin');
    exit;
}

// Page title
$pageTitle = 'Admin Dashboard';

// Wrap database queries in try-catch to prevent 500 errors if tables don't exist
try {
    // Get statistics for dashboard
    $stats = [
        'users' => $db->fetchSingle("SELECT COUNT(*) as count FROM users", [])['count'] ?? 0,
        'jobs' => $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings", [])['count'] ?? 0,
        'activeJobs' => $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE status = 'active'", [])['count'] ?? 0,
        'applications' => $db->fetchSingle("SELECT COUNT(*) as count FROM applications", [])['count'] ?? 0,
        'employers' => $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE user_type = 'employer'", [])['count'] ?? 0,
        'seekers' => $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE user_type = 'seeker'", [])['count'] ?? 0,
        'todayUsers' => $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()", [])['count'] ?? 0,
        'todayJobs' => $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE DATE(created_at) = CURDATE()", [])['count'] ?? 0
    ];

    // Get recent activity for activity feed
    $recentActivity = $db->fetchAll(
        "SELECT sal.*, u.name as user_name, u.user_type 
         FROM system_activity_log sal
         LEFT JOIN users u ON sal.user_id = u.user_id
         ORDER BY sal.timestamp DESC
         LIMIT 10"
    ) ?? [];

    // Get pending items requiring attention
    $pendingUsers = $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE status = 'pending'", [])['count'] ?? 0;
    $pendingJobs = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE status = 'pending'", [])['count'] ?? 0;
    $pendingReviews = $db->fetchSingle("SELECT COUNT(*) as count FROM employer_reviews WHERE status = 'pending'", [])['count'] ?? 0;
    $reportedContent = $db->fetchSingle("SELECT COUNT(*) as count FROM reported_content WHERE status = 'pending'", [])['count'] ?? 0;

    // Get recent system errors
    $systemErrors = $db->fetchAll(
        "SELECT * FROM system_errors ORDER BY timestamp DESC LIMIT 5"
    ) ?? [];
} catch (Exception $e) {
    // Log error but don't display to admin
    error_log("Database error in admin panel: " . $e->getMessage());
    
    // Set default values so page doesn't crash
    $stats = [
        'users' => 0, 'jobs' => 0, 'activeJobs' => 0, 'applications' => 0,
        'employers' => 0, 'seekers' => 0, 'todayUsers' => 0, 'todayJobs' => 0
    ];
    $recentActivity = [];
    $pendingUsers = $pendingJobs = $pendingReviews = $reportedContent = 0;
    $systemErrors = [];
}

// Make sure timeAgo function exists
if (!function_exists('timeAgo')) {
    function timeAgo($timestamp) {
        $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff/60) . ' minutes ago';
        if ($diff < 86400) return floor($diff/3600) . ' hours ago';
        if ($diff < 604800) return floor($diff/86400) . ' days ago';
        
        return date('M j, Y', $time);
    }
}

// Make sure formatDateTime function exists
if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
        return date($format, strtotime($datetime));
    }
}

// Make sure isAdmin function exists
if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'admin';
    }
}

// Include header
include_once '../includes/admin_header.php';
?>

<!-- Admin Dashboard Content -->
<div class="container-fluid px-4">
    <h1 class="mt-4">Admin Dashboard</h1>
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item active">Dashboard</li>
    </ol>
    
    <!-- Dashboard Welcome Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="mb-1">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>!</h4>
                            <p class="mb-0">Current system time: <?= date('Y-m-d H:i:s') ?> (UTC)</p>
                        </div>
                        <div>
                            <a href="reports.php" class="btn btn-light">View Reports</a>
                            <a href="settings.php" class="btn btn-outline-light ms-2">System Settings</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white mb-4 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['users']) ?></div>
                        <div class="card-name">Total Users</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white"><i class="fas fa-plus me-1"></i> <?= $stats['todayUsers'] ?> today</div>
                    <a href="users.php" class="small text-white">View Details</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white mb-4 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['jobs']) ?></div>
                        <div class="card-name">Job Listings</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-briefcase fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white"><i class="fas fa-plus me-1"></i> <?= $stats['todayJobs'] ?> today</div>
                    <a href="jobs.php" class="small text-white">View Details</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-white mb-4 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['applications']) ?></div>
                        <div class="card-name">Applications</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-file-alt fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">
                        <i class="fas fa-chart-line me-1"></i> 
                        <?= $stats['activeJobs'] ?> active jobs
                    </div>
                    <a href="applications.php" class="small text-white">View Details</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card bg-danger text-white mb-4 h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="card-value"><?= number_format($stats['employers']) ?></div>
                        <div class="card-name">Employers</div>
                    </div>
                    <div class="card-icon">
                        <i class="fas fa-building fa-2x"></i>
                    </div>
                </div>
                <div class="card-footer d-flex align-items-center justify-content-between">
                    <div class="small text-white">
                        <i class="fas fa-user-tie me-1"></i>
                        <?= $stats['seekers'] ?> job seekers
                    </div>
                    <a href="employers.php" class="small text-white">View Details</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Items & Tasks -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bell me-1"></i>
                    Items Requiring Attention
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if ($pendingUsers > 0): ?>
                            <a href="users.php?filter=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user-clock text-warning me-2"></i> Pending User Approvals</span>
                                <span class="badge bg-warning rounded-pill"><?= $pendingUsers ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pendingJobs > 0): ?>
                            <a href="jobs.php?filter=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-briefcase text-primary me-2"></i> Pending Job Approvals</span>
                                <span class="badge bg-primary rounded-pill"><?= $pendingJobs ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pendingReviews > 0): ?>
                            <a href="reviews.php?filter=pending" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-star-half-alt text-info me-2"></i> Pending Review Approvals</span>
                                <span class="badge bg-info rounded-pill"><?= $pendingReviews ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($reportedContent > 0): ?>
                            <a href="reports.php?tab=reported" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-flag text-danger me-2"></i> Reported Content</span>
                                <span class="badge bg-danger rounded-pill"><?= $reportedContent ?></span>
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($pendingUsers == 0 && $pendingJobs == 0 && $pendingReviews == 0 && $reportedContent == 0): ?>
                            <div class="list-group-item text-center text-success">
                                <i class="fas fa-check-circle me-2"></i> No pending items require your attention
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="admin/tasks.php" class="btn btn-sm btn-primary">View All Tasks</a>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    Recent System Errors
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($systemErrors) > 0): ?>
                                    <?php foreach ($systemErrors as $error): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= formatDateTime($error['timestamp'], 'M d, H:i') ?></td>
                                            <td><span class="badge bg-danger"><?= htmlspecialchars($error['error_type']) ?></span></td>
                                            <td class="text-truncate" style="max-width: 300px;" title="<?= htmlspecialchars($error['error_message']) ?>">
                                                <?= htmlspecialchars($error['error_message']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-success">
                                            <i class="fas fa-check-circle me-2"></i> No recent system errors
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="logs.php?type=errors" class="btn btn-sm btn-danger">View All Errors</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Activity -->
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-list me-1"></i>
                    Recent Activity
                </div>
                <div class="card-body pb-0">
                    <div class="timeline">
                        <?php if (count($recentActivity) > 0): ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker 
                                        <?php
                                            switch ($activity['action']) {
                                                case 'login': echo 'bg-success'; break;
                                                case 'create': echo 'bg-primary'; break;
                                                case 'update': echo 'bg-info'; break;
                                                case 'delete': echo 'bg-danger'; break;
                                                default: echo 'bg-secondary';
                                            }
                                        ?>">
                                        <i class="fas 
                                            <?php
                                                switch ($activity['action']) {
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
                                                <?= htmlspecialchars($activity['user_name'] ?? 'System') ?>
                                                <span class="badge 
                                                    <?php
                                                        switch ($activity['user_type'] ?? '') {
                                                            case 'admin': echo 'bg-danger'; break;
                                                            case 'employer': echo 'bg-primary'; break;
                                                            case 'seeker': echo 'bg-success'; break;
                                                            default: echo 'bg-secondary';
                                                        }
                                                    ?>">
                                                    <?= ucfirst($activity['user_type'] ?? 'System') ?>
                                                </span>
                                            </span>
                                            <span class="timeline-date text-muted">
                                                <?= timeAgo($activity['timestamp']) ?>
                                            </span>
                                        </div>
                                        <p class="mb-2"><?= htmlspecialchars($activity['details']) ?></p>
                                        <div class="timeline-meta text-muted small">
                                            IP: <?= htmlspecialchars($activity['ip_address']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted">No recent activity to display</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <a href="admin/logs.php?type=activity" class="btn btn-sm btn-primary">View All Activity</a>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions & User Distribution -->
        <div class="col-lg-5">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-bolt me-1"></i>
                    Quick Actions
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="users.php?action=add" class="btn btn-primary btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-user-plus me-2"></i> Add User
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="jobs.php?action=add" class="btn btn-success btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-plus-circle me-2"></i> Add Job
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="email_blast.php" class="btn btn-info btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-envelope me-2"></i> Email Users
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="backup.php" class="btn btn-warning btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-database me-2"></i> Backup System
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="reports.php?report=monthly" class="btn btn-secondary btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-chart-bar me-2"></i> Monthly Report
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="settings.php" class="btn btn-dark btn-block w-100 d-flex align-items-center justify-content-center">
                                <i class="fas fa-cogs me-2"></i> Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- User Distribution Chart -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-1"></i>
                    User Distribution
                </div>
                <div class="card-body">
                    <div class="chart-container" style="position: relative; height:200px;">
                        <canvas id="userDistributionChart"></canvas>
                    </div>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <div class="h5 mb-0"><?= number_format($stats['seekers']) ?></div>
                            <div class="small text-muted">Job Seekers</div>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0"><?= number_format($stats['employers']) ?></div>
                            <div class="small text-muted">Employers</div>
                        </div>
                        <div class="col-4">
                            <div class="h5 mb-0"><?= number_format($stats['users'] - $stats['seekers'] - $stats['employers']) ?></div>
                            <div class="small text-muted">Admins</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Script -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>

<script>
// Chart.js configuration
document.addEventListener('DOMContentLoaded', function() {
    // User Distribution Chart
    const userDistCtx = document.getElementById('userDistributionChart').getContext('2d');
    const userDistChart = new Chart(userDistCtx, {
        type: 'doughnut',
        data: {
            labels: ['Job Seekers', 'Employers', 'Admins'],
            datasets: [{
                data: [
                    <?= $stats['seekers'] ?>,
                    <?= $stats['employers'] ?>,
                    <?= $stats['users'] - $stats['seekers'] - $stats['employers'] ?>
                ],
                backgroundColor: [
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(220, 53, 69, 0.8)'
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(0, 123, 255, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            cutout: '70%'
        }
    });
});
</script>

<style>
/* Additional CSS for dashboard */
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

/* Timeline styling */
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

<?php
// Calculate page load time
$pageLoadTime = round(microtime(true) - $pageStartTime, 4);

// Include footer with debug info
include_once '../includes/admin_footer.php';

// Debug info comment
echo "<!-- Page generated at " . date('Y-m-d H:i:s') . " by HasinduNimesh in " . $pageLoadTime . " seconds -->";
?>