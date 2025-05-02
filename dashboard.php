<?php
$pageTitle = 'Dashboard';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Require login for dashboard
requireLogin();

// Get user data
$userId = $_SESSION['user_id'];
$user = getUserById($userId);
$userType = $_SESSION['user_type'];

// Initialize variables
$stats = [];
$recentItems = [];

// Load dashboard data based on user type
if ($userType == 'employer') {
    // Get employer stats
    $stats['total_jobs'] = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE employer_id = ?", [$userId])['count'];
    $stats['active_jobs'] = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE employer_id = ? AND status = 'open'", [$userId])['count'];
    $stats['total_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications a 
                                                   JOIN job_listings j ON a.job_id = j.job_id 
                                                   WHERE j.employer_id = ?", [$userId])['count'];
    $stats['new_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications a 
                                                  JOIN job_listings j ON a.job_id = j.job_id 
                                                  WHERE j.employer_id = ? AND a.status = 'pending'", [$userId])['count'];
    
    // Get employer rating
    $rating = getEmployerRating($userId);
    $stats['rating'] = [
        'average' => $rating['average'],
        'count' => $rating['count']
    ];
    
    // Get recent job listings
    $recentJobs = $db->fetchAll("SELECT * FROM job_listings 
                               WHERE employer_id = ? 
                               ORDER BY created_at DESC 
                               LIMIT 5", [$userId]);
    
    // Get recent applications
    $recentApplications = $db->fetchAll("SELECT a.*, j.title as job_title, u.name as applicant_name 
                                        FROM applications a 
                                        JOIN job_listings j ON a.job_id = j.job_id 
                                        JOIN users u ON a.seeker_id = u.user_id 
                                        WHERE j.employer_id = ? 
                                        ORDER BY a.created_at DESC 
                                        LIMIT 5", [$userId]);
} 
else if ($userType == 'seeker') {
    // Get seeker stats
    $stats['total_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ?", [$userId])['count'];
    $stats['pending_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ? AND status = 'pending'", [$userId])['count'];
    $stats['reviewed_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ? AND status IN ('reviewed', 'interviewed')", [$userId])['count'];
    $stats['accepted_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE seeker_id = ? AND status = 'accepted'", [$userId])['count'];
    
    // Get recent applications
    $recentApplications = $db->fetchAll("SELECT a.*, j.title as job_title, u.name as employer_name, u.company_name 
                                        FROM applications a 
                                        JOIN job_listings j ON a.job_id = j.job_id 
                                        JOIN users u ON j.employer_id = u.user_id 
                                        WHERE a.seeker_id = ? 
                                        ORDER BY a.created_at DESC 
                                        LIMIT 5", [$userId]);
    
    // Get recommended jobs based on previous applications
    $recommendedJobs = $db->fetchAll("SELECT j.*, u.name as employer_name, u.company_name 
                                     FROM job_listings j 
                                     JOIN users u ON j.employer_id = u.user_id 
                                     WHERE j.status = 'open' 
                                     AND j.job_id NOT IN (SELECT job_id FROM applications WHERE seeker_id = ?) 
                                     ORDER BY j.created_at DESC 
                                     LIMIT 3", [$userId]);
} 
else if ($userType == 'admin') {
    // Get admin stats
    $stats['total_users'] = $db->fetchSingle("SELECT COUNT(*) as count FROM users")['count'];
    $stats['total_employers'] = $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE user_type = 'employer'")['count'];
    $stats['total_seekers'] = $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE user_type = 'seeker'")['count'];
    $stats['total_jobs'] = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings")['count'];
    $stats['total_applications'] = $db->fetchSingle("SELECT COUNT(*) as count FROM applications")['count'];
    
    // Get recent users
    $recentUsers = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
    
    // Get recent job listings
    $recentJobs = $db->fetchAll("SELECT j.*, u.name as employer_name, u.company_name 
                               FROM job_listings j 
                               JOIN users u ON j.employer_id = u.user_id 
                               ORDER BY j.created_at DESC 
                               LIMIT 5");
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row mb-4">
    <div class="col-md-8">
        <h1 class="mb-0">Dashboard</h1>
        <p class="text-muted">Welcome back, <?= htmlspecialchars($user['name']) ?>!</p>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if (isEmployer()): ?>
            <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Post a Job</a>
        <?php elseif (isSeeker()): ?>
            <a href="jobs.php" class="btn btn-primary"><i class="fas fa-search me-2"></i>Find Jobs</a>
        <?php endif; ?>
    </div>
</div>

<!-- Current Date Display -->
<div class="alert alert-light mb-4">
    <i class="far fa-calendar-alt me-2"></i> Today is <?= date('l, F j, Y') ?> | <i class="far fa-clock me-2"></i> Last updated: <?= date('H:i:s') ?>
</div>

<!-- Stats Section -->
<div class="row">
    <?php if (isEmployer()): ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card blue">
                <h3>Active Jobs</h3>
                <div class="stat-number"><?= $stats['active_jobs'] ?></div>
                <p>of <?= $stats['total_jobs'] ?> total</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card green">
                <h3>New Applications</h3>
                <div class="stat-number"><?= $stats['new_applications'] ?></div>
                <p>of <?= $stats['total_applications'] ?> total</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card orange">
                <h3>Employer Rating</h3>
                <div class="stat-number"><?= $stats['rating']['average'] ?><small>/5</small></div>
                <p><?= $stats['rating']['count'] ?> reviews</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="profile.php" class="text-decoration-none">
                <div class="stat-card red">
                    <h3>Profile Completion</h3>
                    <?php
                    // Calculate profile completion percentage
                    $completionFields = ['name', 'email', 'company_name', 'location', 'phone', 'website', 'bio', 'profile_image'];
                    $filledFields = 0;
                    foreach ($completionFields as $field) {
                        if (!empty($user[$field])) $filledFields++;
                    }
                    $completionPercent = round(($filledFields / count($completionFields)) * 100);
                    ?>
                    <div class="stat-number"><?= $completionPercent ?>%</div>
                    <p>Click to update profile</p>
                </div>
            </a>
        </div>
    <?php elseif (isSeeker()): ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card blue">
                <h3>Total Applications</h3>
                <div class="stat-number"><?= $stats['total_applications'] ?></div>
                <p>Jobs applied</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card orange">
                <h3>Pending</h3>
                <div class="stat-number"><?= $stats['pending_applications'] ?></div>
                <p>Awaiting response</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card green">
                <h3>In Progress</h3>
                <div class="stat-number"><?= $stats['reviewed_applications'] ?></div>
                <p>Reviewed/Interviewed</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="profile.php" class="text-decoration-none">
                <div class="stat-card red">
                    <h3>Profile Completion</h3>
                    <?php
                    // Calculate profile completion percentage
                    $completionFields = ['name', 'email', 'location', 'phone', 'bio', 'profile_image'];
                    $filledFields = 0;
                    foreach ($completionFields as $field) {
                        if (!empty($user[$field])) $filledFields++;
                    }
                    $completionPercent = round(($filledFields / count($completionFields)) * 100);
                    ?>
                    <div class="stat-number"><?= $completionPercent ?>%</div>
                    <p>Click to update profile</p>
                </div>
            </a>
        </div>
    <?php elseif (isAdmin()): ?>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card blue">
                <h3>Total Users</h3>
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <p><?= $stats['total_employers'] ?> employers, <?= $stats['total_seekers'] ?> seekers</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card green">
                <h3>Total Jobs</h3>
                <div class="stat-number"><?= $stats['total_jobs'] ?></div>
                <p>Active job listings</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <div class="stat-card orange">
                <h3>Applications</h3>
                <div class="stat-number"><?= $stats['total_applications'] ?></div>
                <p>Total submissions</p>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-4">
            <a href="admin/" class="text-decoration-none">
                <div class="stat-card red">
                    <h3>Admin Panel</h3>
                    <div class="stat-number"><i class="fas fa-cogs"></i></div>
                    <p>Manage platform</p>
                </div>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Main Dashboard Content -->
<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        <?php if (isEmployer()): ?>
            <!-- Recent Job Listings -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Your Job Listings</h5>
                    <a href="manage_jobs.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentJobs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Posted</th>
                                        <th>Status</th>
                                        <th>Applications</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentJobs as $job): ?>
                                        <?php 
                                        $appCount = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE job_id = ?", [$job['job_id']])['count']; 
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none fw-medium">
                                                    <?= htmlspecialchars($job['title']) ?>
                                                </a>
                                                <?php if ($job['is_featured']): ?>
                                                    <span class="badge bg-warning text-dark ms-1">Featured</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small><?= timeAgo($job['created_at']) ?></small></td>
                                            <td>
                                                <span class="badge bg-<?= $job['status'] == 'open' ? 'success' : ($job['status'] == 'closed' ? 'danger' : 'secondary') ?>">
                                                    <?= ucfirst($job['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="applicants.php?job_id=<?= $job['job_id'] ?>" class="text-decoration-none">
                                                    <?= $appCount ?> applicant<?= $appCount != 1 ? 's' : '' ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit_job.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($job['status'] == 'open'): ?>
                                                        <a href="toggle_job_status.php?id=<?= $job['job_id'] ?>&action=close" 
                                                           class="btn btn-outline-danger" title="Close Listing"
                                                           data-confirm="Are you sure you want to close this job listing?">
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="toggle_job_status.php?id=<?= $job['job_id'] ?>&action=open" 
                                                           class="btn btn-outline-success" title="Reopen Listing">
                                                            <i class="fas fa-redo"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-clipboard-list fa-3x text-muted"></i>
                            </div>
                            <p class="mb-3">You haven't posted any jobs yet.</p>
                            <a href="post_job.php" class="btn btn-primary">Post Your First Job</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Applications -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Recent Applications</h5>
                    <a href="applicants.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentApplications) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($recentApplications as $app): ?>
                                <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($app['applicant_name']) ?></h6>
                                        <p class="mb-1 text-muted">Applied for <a href="job_detail.php?id=<?= $app['job_id'] ?>"><?= htmlspecialchars($app['job_title']) ?></a></p>
                                        <small><?= timeAgo($app['created_at']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="me-3"><?= getStatusLabel($app['status']) ?></span>
                                        <a href="view_application.php?id=<?= $app['application_id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-user-clock fa-3x text-muted"></i>
                            </div>
                            <p>No applications have been received yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif (isSeeker()): ?>
            <!-- Application Status -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Your Applications</h5>
                    <a href="my_applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recentApplications) > 0): ?>
                        <div class="list-group">
                            <?php foreach ($recentApplications as $app): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($app['job_title']) ?></h6>
                                        <small><?= timeAgo($app['created_at']) ?></small>
                                    </div>
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-building me-1"></i> 
                                        <?= htmlspecialchars($app['company_name'] ?: $app['employer_name']) ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <div><?= getStatusLabel($app['status']) ?></div>
                                        <a href="application_detail.php?id=<?= $app['application_id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-file-alt fa-3x text-muted"></i>
                            </div>
                            <p class="mb-3">You haven't applied for any jobs yet.</p>
                            <a href="jobs.php" class="btn btn-primary">Browse Available Jobs</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recommended Jobs -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Recommended Jobs</h5>
                    <a href="jobs.php" class="btn btn-sm btn-outline-primary">View All Jobs</a>
                </div>
                <div class="card-body">
                    <?php if (count($recommendedJobs) > 0): ?>
                        <div class="row">
                            <?php foreach ($recommendedJobs as $job): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100 job-item">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                                            <p class="card-text text-muted">
                                                <i class="fas fa-building me-1"></i> 
                                                <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                            </p>
                                            <div class="mb-3">
                                                <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?></p>
                                                <p class="mb-1"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars(getJobCategories()[$job['category']] ?? $job['category']) ?></p>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="badge bg-<?= $job['job_type'] == 'full-time' ? 'primary' : ($job['job_type'] == 'part-time' ? 'success' : ($job['job_type'] == 'remote' ? 'info' : 'secondary')) ?>">
                                                    <?= ucfirst(str_replace('-', ' ', $job['job_type'])) ?>
                                                </span>
                                                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p>No recommended jobs at the moment. Browse all available jobs instead.</p>
                            <a href="jobs.php" class="btn btn-primary">Browse Jobs</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif (isAdmin()): ?>
            <!-- Recent Users -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Recent Users</h5>
                    <a href="admin/users.php" class="btn btn-sm btn-outline-primary">Manage Users</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'employer' ? 'primary' : 'success') ?>">
                                                <?= ucfirst($user['user_type']) ?>
                                            </span>
                                        </td>
                                        <td><small><?= timeAgo($user['created_at']) ?></small></td>
                                        <td>
                                            <a href="admin/edit_user.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Recent Jobs -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <h5 class="mb-0">Recent Job Listings</h5>
                    <a href="admin/jobs.php" class="btn btn-sm btn-outline-primary">Manage Jobs</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Employer</th>
                                    <th>Posted</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentJobs as $job): ?>
                                    <tr>
                                        <td>
                                            <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($job['title']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></td>
                                        <td><small><?= timeAgo($job['created_at']) ?></small></td>
                                        <td>
                                            <span class="badge bg-<?= $job['status'] == 'open' ? 'success' : ($job['status'] == 'closed' ? 'danger' : 'secondary') ?>">
                                                <?= ucfirst($job['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="admin/edit_job.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($job['status'] == 'open'): ?>
                                                    <a href="admin/toggle_job_status.php?id=<?= $job['job_id'] ?>&status=closed" 
                                                       class="btn btn-outline-danger"
                                                       data-confirm="Are you sure you want to close this job listing?">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="admin/toggle_job_status.php?id=<?= $job['job_id'] ?>&status=open" 
                                                       class="btn btn-outline-success">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($user['profile_image']) ?>" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px;">
                            <i class="fas fa-user fa-3x text-secondary"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <h5 class="card-title"><?= htmlspecialchars($user['name']) ?></h5>
                <?php if (isEmployer() && !empty($user['company_name'])): ?>
                    <p class="text-muted mb-2"><?= htmlspecialchars($user['company_name']) ?></p>
                <?php endif; ?>
                <p class="text-muted mb-3">
                    <i class="fas fa-user-tag me-1"></i> <?= ucfirst($user['user_type']) ?>
                </p>
                
                <?php if (!empty($user['location'])): ?>
                    <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($user['location']) ?></p>
                <?php endif; ?>
                
                <hr>
                <div class="d-grid gap-2">
                    <a href="profile.php" class="btn btn-outline-primary">Edit Profile</a>
                </div>
            </div>
        </div>
        
        <?php if (isEmployer()): ?>
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="post_job.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-plus-circle me-2 text-primary"></i> Post a New Job
                        </a>
                        <a href="applicants.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2 text-primary"></i> View All Applicants
                        </a>
                        <a href="manage_jobs.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-briefcase me-2 text-primary"></i> Manage Job Listings
                        </a>
                        <a href="employer_reviews.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-star me-2 text-primary"></i> View Employer Reviews
                        </a>
                    </div>
                </div>
            </div>
        <?php elseif (isSeeker()): ?>
            <!-- Job Search Tips -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Search Tips</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Update your profile regularly
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Upload a professional resume
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Personalize each application
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Follow up on applications
                        </li>
                        <li>
                            <i class="fas fa-check-circle text-success me-2"></i>
                            Set up job alerts
                        </li>
                    </ul>
                </div>
            </div>
        <?php elseif (isAdmin()): ?>
            <!-- Admin Quick Links -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Admin Quick Links</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="admin/users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2 text-primary"></i> Manage Users
                        </a>
                        <a href="admin/jobs.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-briefcase me-2 text-primary"></i> Manage Jobs
                        </a>
                        <a href="admin/applications.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-clipboard-list me-2 text-primary"></i> Manage Applications
                        </a>
                        <a href="admin/reviews.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-star me-2 text-primary"></i> Manage Reviews
                        </a>
                        <a href="admin/settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2 text-primary"></i> System Settings
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Latest Platform Updates -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Latest Updates</h5>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>New Feature</strong>
                            <small class="text-muted">May 1, 2025</small>
                        </div>
                        <p class="mb-0">Enhanced job application tracking system is now live!</p>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Platform Update</strong>
                            <small class="text-muted">Apr 15, 2025</small>
                        </div>
                        <p class="mb-0">Improved search algorithm for better job matching.</p>
                    </div>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Community</strong>
                            <small class="text-muted">Apr 5, 2025</small>
                        </div>
                        <p class="mb-0">Join our upcoming virtual job fair on May 15th!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>