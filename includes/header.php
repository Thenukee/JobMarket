<?php
/**
 * Header template for AmmooJobs platform
 * Contains HTML header, navigation, and top-level UI elements
 * 
 * @version 1.3.0
 * @last_updated 2025-05-02
 */

// Start measuring page load time if debug mode is enabled
if (!isset($GLOBALS['page_start_time']) && defined('DEBUG_MODE') && DEBUG_MODE) {
    $GLOBALS['page_start_time'] = microtime(true);
}

// Default page title if not set
if (!isset($pageTitle)) {
    $pageTitle = DEFAULT_PAGE_TITLE;
} else {
    $pageTitle = $pageTitle . ' - ' . SITE_NAME;
}

// Get current user's notification count
$notificationCount = 0;
if (isLoggedIn()) {
    $notificationCount = getUnreadNotificationCount($_SESSION['user_id']);
}

// Current date/time and user for debugging
$currentDateTime = date('Y-m-d H:i:s'); // 2025-05-02 09:26:03
$currentUser = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest'; // HasinduNimesh
?>
<!DOCTYPE html>
<html lang="en" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Meta tags -->
    <meta name="description" content="AmmooJobs - Find your dream job or hire the best talent">
    <meta name="keywords" content="jobs, career, employment, recruitment, hiring, resume">
    <meta name="author" content="AmmooJobs">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/favicon.ico" type="image/x-icon">
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <!-- FIXED: Changed style.css to styles.css and added cache-busting timestamp -->
    <link href="assets/css/styles.css?v=<?= time() ?>" rel="stylesheet">
    
    <?php if (isset($pageStyles) && is_array($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
            <link href="<?= htmlspecialchars($style) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Custom Theme (if applicable) -->
    <?php
    $userTheme = 'light'; // Default theme
    if (isset($_SESSION['theme'])) {
        $userTheme = $_SESSION['theme'];
    } elseif (isset($_COOKIE['theme'])) {
        $userTheme = $_COOKIE['theme'];
    }
    
    if ($userTheme == 'dark'): 
    ?>
        <link href="assets/css/dark-theme.css?v=<?= time() ?>" rel="stylesheet">
    <?php endif; ?>
    
    <!-- Open Graph Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="AmmooJobs - Find your dream job or hire the best talent">
    <meta property="og:image" content="<?= SITE_URL ?>/assets/img/og-image.jpg">
    <meta property="og:url" content="<?= getCurrentUrl() ?>">
    <meta property="og:type" content="website">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "AmmooJobs",
        "url": "<?= SITE_URL ?>",
        "logo": "<?= SITE_URL ?>/assets/img/logo.png",
        "sameAs": [
            "https://facebook.com/ammoojobs",
            "https://twitter.com/ammoojobs",
            "https://linkedin.com/company/ammoojobs",
            "https://instagram.com/ammoojobs"
        ]
    }
    </script>
    
    <!-- CSS Debugging Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log("CSS files loaded:");
        const cssFiles = Array.from(document.styleSheets).map(sheet => sheet.href);
        console.log(cssFiles);
        
        // Check if our styles.css is loaded
        const ourCss = cssFiles.filter(href => href && href.includes('styles.css'));
        if (ourCss.length === 0) {
            console.error("WARNING: styles.css not found! Please check the file path and name.");
        } else {
            console.log("✅ styles.css loaded successfully");
        }
    });
    </script>
    
    <!-- Fallback Styling -->
    <style>
    /* Critical CSS fallback in case external stylesheet fails */
    :root {
        --primary: #3b82f6;
        --primary-dark: #1d4ed8;
        --primary-light: #93c5fd;
        --body-bg: #f9fafb;
        --border-radius: 0.5rem;
        --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }
    
    body {
        font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        background-color: var(--body-bg);
    }
    
    .job-header {
        background-color: #f9fafb;
        padding: 2rem 0;
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 2rem;
    }
    
    .card {
        border: none;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        margin-bottom: 1.5rem;
    }
    
    .card-header {
        background-color: white;
        border-bottom: 1px solid #e5e7eb;
        padding: 1rem 1.5rem;
        font-weight: 600;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    </style>
</head>
<body class="d-flex flex-column h-100">
    <!-- Maintenance Mode Banner -->
    <?php if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && isAdmin()): ?>
        <div class="alert alert-warning text-center mb-0 rounded-0">
            <i class="fas fa-tools me-2"></i>
            <strong>Maintenance Mode Active</strong> - Only administrators can access the site.
        </div>
    <?php endif; ?>

    <!-- Header Navigation -->
    <header class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/logo.png" alt="AmmooJobs Logo" height="40">
                <span class="d-none d-md-inline ms-2">AmmooJobs</span>
            </a>
            
            <!-- Responsive Menu Button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" 
                    aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Main Navigation -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.php">Find Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="employers.php">Companies</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reviews.php">Reviews</a>
                    </li>
                    
                    <?php if (isSeeker()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="jobSeekerDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                For Job Seekers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="jobSeekerDropdown">
                                <li><a class="dropdown-item" href="my_applications.php">My Applications</a></li>
                                <li><a class="dropdown-item" href="saved_jobs.php">Saved Jobs</a></li>
                                <li><a class="dropdown-item" href="resume.php">Resume Builder</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="career_advice.php">Career Advice</a></li>
                                <li><a class="dropdown-item" href="resources.php">Resources</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isEmployer()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="employerDropdown" role="button" 
                               data-bs-toggle="dropdown" aria-expanded="false">
                                For Employers
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="employerDropdown">
                                <li><a class="dropdown-item" href="post_job.php">Post a Job</a></li>
                                <li><a class="dropdown-item" href="manage_jobs.php">Manage Jobs</a></li>
                                <li><a class="dropdown-item" href="applicants.php">Review Applicants</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="search_resumes.php">Search Resumes</a></li>
                                <li><a class="dropdown-item" href="employer_resources.php">Resources</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                    
                    <?php if (isAdmin()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                Admin
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                                <li><a class="dropdown-item" href="admin/index.php">Dashboard</a></li>
                                <li><a class="dropdown-item" href="admin/users.php">Manage Users</a></li>
                                <li><a class="dropdown-item" href="admin/jobs.php">Manage Jobs</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="admin/reports.php">Reports</a></li>
                                <li><a class="dropdown-item" href="admin/settings.php">Site Settings</a></li>
                                <li><a class="dropdown-item" href="admin/logs.php">System Logs</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Right Side Menu -->
                <div class="d-flex align-items-center">
                    <?php if (!isLoggedIn()): ?>
                        <!-- Guest User -->
                        <div class="d-none d-md-block">
                            <a href="login.php" class="btn btn-outline-primary me-2">Log In</a>
                            <a href="register.php" class="btn btn-primary">Register</a>
                        </div>
                        <div class="d-md-none">
                            <a href="login.php" class="btn btn-sm btn-outline-primary me-2">Log In</a>
                            <a href="register.php" class="btn btn-sm btn-primary">Register</a>
                        </div>
                    <?php else: ?>
                        <!-- Logged In User -->
                        <!-- Notifications -->
                        <div class="dropdown me-3">
                            <a href="#" class="position-relative text-dark notification-bell" id="notificationDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell fa-lg"></i>
                                <?php if ($notificationCount > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $notificationCount > 9 ? '9+' : $notificationCount ?>
                                    <span class="visually-hidden">unread notifications</span>
                                </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown p-0" aria-labelledby="notificationDropdown">
                                <li class="dropdown-header d-flex justify-content-between align-items-center">
                                    <span>Notifications</span>
                                    <?php if ($notificationCount > 0): ?>
                                    <a href="notifications.php?mark_all_read=1" class="text-primary small">Mark all as read</a>
                                    <?php endif; ?>
                                </li>
                                
                                <div class="notification-list">
                                    <?php
                                    if (isLoggedIn()) {
                                        $notifications = $db->fetchAll(
                                            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5", 
                                            [$_SESSION['user_id']]
                                        );
                                        
                                        if ($notifications && count($notifications) > 0):
                                            foreach ($notifications as $notification):
                                                $isUnread = $notification['is_read'] == 0;
                                    ?>
                                                <li>
                                                    <a href="<?= $notification['link'] ? htmlspecialchars($notification['link']) : 'notifications.php?mark_read=' . $notification['notification_id'] ?>" 
                                                       class="dropdown-item notification-item <?= $isUnread ? 'unread' : '' ?>">
                                                        <div class="d-flex align-items-center">
                                                            <div class="notification-icon rounded-circle me-3
                                                                <?php
                                                                    switch ($notification['type']) {
                                                                        case 'application':
                                                                            echo ' bg-primary ';
                                                                            $icon = 'far fa-file-alt';
                                                                            break;
                                                                        case 'message':
                                                                            echo ' bg-success ';
                                                                            $icon = 'far fa-envelope';
                                                                            break;
                                                                        case 'alert':
                                                                            echo ' bg-danger ';
                                                                            $icon = 'fas fa-exclamation-circle';
                                                                            break;
                                                                        default:
                                                                            echo ' bg-info ';
                                                                            $icon = 'fas fa-bell';
                                                                    }
                                                                ?>">
                                                                <i class="<?= $icon ?> text-white"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <p class="mb-1"><?= htmlspecialchars($notification['message']) ?></p>
                                                                <span class="small text-muted"><?= timeAgo($notification['created_at']) ?></span>
                                                            </div>
                                                            <?php if ($isUnread): ?>
                                                                <span class="unread-indicator"></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </a>
                                                </li>
                                    <?php 
                                            endforeach;
                                        else:
                                    ?>
                                            <li><div class="dropdown-item text-center py-3 text-muted">No notifications</div></li>
                                    <?php 
                                        endif;
                                    } 
                                    ?>
                                </div>
                                <li><hr class="dropdown-divider m-0"></li>
                                <li><a class="dropdown-item text-center py-2" href="notifications.php">View All</a></li>
                            </ul>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="dropdown">
                            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" 
                               id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($_SESSION['profile_image'])): ?>
                                    <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($_SESSION['profile_image']) ?>" 
                                         alt="<?= htmlspecialchars($_SESSION['name']) ?>" class="rounded-circle me-2" width="32" height="32">
                                <?php else: ?>
                                    <div class="user-avatar-placeholder rounded-circle me-2">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['name']) ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li class="dropdown-header">
                                    <div class="d-flex flex-column">
                                        <strong><?= htmlspecialchars($_SESSION['name']) ?></strong>
                                        <span class="text-muted small"><?= htmlspecialchars($_SESSION['email']) ?></span>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>My Profile</a></li>
                                <?php if (isSeeker()): ?>
                                    <li><a class="dropdown-item" href="my_applications.php"><i class="fas fa-clipboard-list me-2"></i>My Applications</a></li>
                                    <li><a class="dropdown-item" href="saved_jobs.php"><i class="fas fa-bookmark me-2"></i>Saved Jobs</a></li>
                                <?php endif; ?>
                                <?php if (isEmployer()): ?>
                                    <li><a class="dropdown-item" href="post_job.php"><i class="fas fa-plus-circle me-2"></i>Post a Job</a></li>
                                    <li><a class="dropdown-item" href="manage_jobs.php"><i class="fas fa-briefcase me-2"></i>Manage Jobs</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="account_settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <?php if (defined('DEBUG_MODE') && DEBUG_MODE && isAdmin()): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="admin/system_info.php"><i class="fas fa-bug me-2"></i>System Debug</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    
    <?php if (isEmployer() && defined('PREMIUM_ENABLED') && PREMIUM_ENABLED): ?>
        <!-- Premium Upgrade Banner (for non-premium employers) -->
        <?php
        $isPremium = false;
        if (isLoggedIn() && isEmployer()) {
            $employerData = $db->fetchSingle("SELECT subscription_level FROM users WHERE user_id = ?", [$_SESSION['user_id']]);
            $isPremium = isset($employerData['subscription_level']) && $employerData['subscription_level'] != 'free';
        }
        
        if (!$isPremium):
        ?>
        <div class="bg-gradient-primary text-white py-2">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <p class="mb-md-0"><i class="fas fa-star me-1"></i> Upgrade to Premium: Get more applications with featured jobs, advanced analytics, and priority support!</p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <a href="pricing.php" class="btn btn-sm btn-outline-light">View Plans</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Search Bar (only on specific pages) -->
    <?php 
    $showSearchBar = in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'jobs.php', 'search.php']);
    if ($showSearchBar): 
    ?>
    <div class="bg-primary py-4">
        <div class="container">
            <form action="jobs.php" method="GET" class="job-search-form">
                <div class="row g-2">
                    <div class="col-lg-5 col-md-5">
                        <div class="search-input-group">
                            <i class="fas fa-search text-muted"></i>
                            <input type="text" name="keyword" class="form-control form-control-lg border-0" placeholder="Job title, keywords, or company" 
                                  value="<?= isset($_GET['keyword']) ? htmlspecialchars($_GET['keyword']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <div class="search-input-group">
                            <i class="fas fa-map-marker-alt text-muted"></i>
                            <input type="text" name="location" class="form-control form-control-lg border-0" placeholder="City, state, or remote" 
                                  value="<?= isset($_GET['location']) ? htmlspecialchars($_GET['location']) : '' ?>">
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-3">
                        <button type="submit" class="btn btn-lg btn-dark w-100">
                            <i class="fas fa-search me-2"></i> Find Jobs
                        </button>
                    </div>
                </div>
                <div class="mt-2 search-options text-white">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="remoteOnly" name="remote" value="1" 
                                      <?= isset($_GET['remote']) && $_GET['remote'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remoteOnly">Remote Only</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="fullTimeOnly" name="full_time" value="1"
                                      <?= isset($_GET['full_time']) && $_GET['full_time'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="fullTimeOnly">Full Time</label>
                            </div>
                        </div>
                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                            <a href="advanced_search.php" class="text-white"><i class="fas fa-sliders-h me-1"></i> Advanced Search</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Display Flash Messages -->
    <div class="container mt-3">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['info'])): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?= $_SESSION['info'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['info']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= $_SESSION['warning'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['warning']); ?>
        <?php endif; ?>
    </div>
    
    <!-- Debug banner showing current time and user (only in debug mode) -->
    <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <div class="bg-dark text-white py-1 small">
            <div class="container">
                <div class="row">
                    <div class="col-md-6">
                        <i class="fas fa-clock me-1"></i> Current time (UTC): <?= $currentDateTime ?> <!-- 2025-05-02 09:26:03 -->
                    </div>
                    <div class="col-md-6 text-md-end">
                        <i class="fas fa-user me-1"></i> User: <?= $currentUser ?> <!-- HasinduNimesh -->
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <div class="container main-content py-4">
        <?php
        // Hidden comment with current time and user for debugging
        echo "<!-- Page requested at $currentDateTime by $currentUser -->";
        ?>