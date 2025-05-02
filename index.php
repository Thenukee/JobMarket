<?php
// Make sure there is absolutely no whitespace before this opening PHP tag
// Start the session before any output
session_start();

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Home';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php'; // Added auth.php since you use isLoggedIn()
require_once 'includes/header.php';

// Wrap database queries in try/catch to prevent 500 errors
try {
    // Get featured jobs - FIXED: changed 'open' to 'active'
    $featuredJobs = $db->fetchAll("SELECT j.*, u.name as employer_name, u.company_name 
                                FROM job_listings j 
                                JOIN users u ON j.employer_id = u.user_id 
                                WHERE j.status = 'active' AND j.is_featured = 1 
                                ORDER BY j.created_at DESC LIMIT 6");

    // Get recent jobs - FIXED: changed 'open' to 'active'
    $recentJobs = $db->fetchAll("SELECT j.*, u.name as employer_name, u.company_name 
                                FROM job_listings j 
                                JOIN users u ON j.employer_id = u.user_id 
                                WHERE j.status = 'active' 
                                ORDER BY j.created_at DESC LIMIT 8");

    // Get job categories with counts - FIXED: changed 'open' to 'active'
    $categories = $db->fetchAll("SELECT category, COUNT(*) as count 
                             FROM job_listings 
                             WHERE status = 'active' 
                             GROUP BY category 
                             ORDER BY count DESC LIMIT 8");

    // Get stats - FIXED: changed 'open' to 'active'
    $stats = [
        'jobs' => $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE status = 'active'")['count'] ?? 0,
        'companies' => $db->fetchSingle("SELECT COUNT(DISTINCT employer_id) as count FROM job_listings")['count'] ?? 0,
        'candidates' => $db->fetchSingle("SELECT COUNT(*) as count FROM users WHERE user_type = 'seeker'")['count'] ?? 0
    ];
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log("Database error: " . $e->getMessage());
    $featuredJobs = [];
    $recentJobs = [];
    $categories = [];
    $stats = [
        'jobs' => 0,
        'companies' => 0,
        'candidates' => 0
    ];
}

// Make sure getJobCategories() exists and returns expected values
if (!function_exists('getJobCategories')) {
    function getJobCategories() {
        return [
            'technology' => 'Technology',
            'healthcare' => 'Healthcare',
            'education' => 'Education',
            'finance' => 'Finance',
            'marketing' => 'Marketing',
            'engineering' => 'Engineering',
            'hospitality' => 'Hospitality',
            'retail' => 'Retail',
            'administrative' => 'Administrative',
            'other' => 'Other'
        ];
    }
}

// Make sure formatSalary() exists
if (!function_exists('formatSalary')) {
    function formatSalary($min, $max) {
        if (!$min && !$max) return 'Not specified';
        if (!$min) return 'Up to $' . number_format($max);
        if (!$max) return 'From $' . number_format($min);
        return '$' . number_format($min) . ' - $' . number_format($max);
    }
}

// Make sure timeAgo() exists
if (!function_exists('timeAgo')) {
    function timeAgo($timestamp) {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff/60) . ' minutes ago';
        if ($diff < 86400) return floor($diff/3600) . ' hours ago';
        if ($diff < 604800) return floor($diff/86400) . ' days ago';
        
        return date('M j, Y', $time);
    }
}
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h1 class="display-4 fw-bold mb-4">Find Your Dream Job Today</h1>
                <p class="lead mb-4">Search through thousands of job listings to find the perfect match for your skills and career goals.</p>
                <div class="d-flex gap-3">
                    <a href="jobs.php" class="btn btn-light btn-lg">Find Jobs</a>
                    <?php if (!isLoggedIn()): ?>
                    <a href="register.php?type=employer" class="btn btn-outline-light btn-lg">For Employers</a>
                    <?php elseif (isEmployer()): ?>
                    <a href="post_job.php" class="btn btn-outline-light btn-lg">Post a Job</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="search-form p-4 rounded bg-white shadow">
                    <h4 class="mb-3">Search Jobs</h4>
                    <form id="jobSearchForm" class="home-search" action="jobs.php" method="get">
                        <div class="mb-3">
                            <label for="searchKeyword" class="form-label">Keywords</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchKeyword" name="keyword" placeholder="Job title, skills, or company">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="searchLocation" class="form-label">Location</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                <input type="text" class="form-control" id="searchLocation" name="location" placeholder="City, state, or remote">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="searchCategory" class="form-label">Category</label>
                            <select class="form-select" id="searchCategory" name="category">
                                <option value="">All Categories</option>
                                <?php foreach (getJobCategories() as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Search Jobs</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-4 mb-4 mb-md-0">
                <h2 class="display-4 fw-bold text-primary"><?= number_format($stats['jobs']) ?></h2>
                <p class="lead">Live Job Listings</p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h2 class="display-4 fw-bold text-primary"><?= number_format($stats['companies']) ?></h2>
                <p class="lead">Companies Hiring</p>
            </div>
            <div class="col-md-4">
                <h2 class="display-4 fw-bold text-primary"><?= number_format($stats['candidates']) ?></h2>
                <p class="lead">Registered Candidates</p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Jobs -->
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h2 mb-0">Featured Jobs</h2>
            <a href="jobs.php?featured=1" class="btn btn-outline-primary">View All</a>
        </div>
        
        <div class="row">
            <?php if (count($featuredJobs) > 0): ?>
                <?php foreach ($featuredJobs as $job): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card job-item card-featured h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="badge bg-warning text-dark mb-2">Featured</span>
                                    <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                                <p class="card-text text-muted">
                                    <i class="fas fa-building me-1"></i> 
                                    <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                </p>
                                <div class="mb-3">
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?></p>
                                    <p class="mb-1"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars(getJobCategories()[$job['category']] ?? $job['category']) ?></p>
                                    <p><i class="fas fa-money-bill-wave me-1"></i> <?= formatSalary($job['salary_min'], $job['salary_max']) ?></p>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?= $job['job_type'] == 'full_time' ? 'primary' : ($job['job_type'] == 'part_time' ? 'success' : ($job['job_type'] == 'remote' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                                    </span>
                                    <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No featured jobs available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Recent Jobs -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h2 mb-0">Recently Added Jobs</h2>
            <a href="jobs.php" class="btn btn-outline-primary">Browse All Jobs</a>
        </div>
        
        <div class="row">
            <?php if (count($recentJobs) > 0): ?>
                <?php foreach ($recentJobs as $job): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card job-item h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="badge bg-<?= $job['job_type'] == 'full_time' ? 'primary' : ($job['job_type'] == 'part_time' ? 'success' : ($job['job_type'] == 'remote' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                                    </span>
                                    <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                                </div>
                                <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                                <p class="card-text text-muted">
                                    <i class="fas fa-building me-1"></i> 
                                    <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                </p>
                                <div class="mb-3">
                                    <p class="mb-1"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?></p>
                                </div>
                                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No jobs available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="py-5">
    <div class="container">
        <h2 class="h2 mb-4 text-center">Popular Job Categories</h2>
        
        <div class="row">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-3 col-sm-6 mb-4">
                        <a href="jobs.php?category=<?= urlencode($category['category']) ?>" class="text-decoration-none">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-<?= $category['category'] == 'technology' ? 'laptop-code' : 
                                            ($category['category'] == 'healthcare' ? 'heartbeat' : 
                                            ($category['category'] == 'education' ? 'graduation-cap' : 
                                            ($category['category'] == 'finance' ? 'chart-line' : 
                                            ($category['category'] == 'marketing' ? 'bullhorn' : 
                                            ($category['category'] == 'engineering' ? 'cogs' : 
                                            ($category['category'] == 'hospitality' ? 'concierge-bell' : 
                                            ($category['category'] == 'retail' ? 'shopping-cart' : 
                                            ($category['category'] == 'administrative' ? 'clipboard' : 'briefcase')))))))) ?> fa-2x text-primary"></i>
                                    </div>
                                    <h5><?= htmlspecialchars(getJobCategories()[$category['category']] ?? ucfirst($category['category'])) ?></h5>
                                    <p class="mb-0 text-muted"><?= $category['count'] ?> open position<?= $category['count'] != 1 ? 's' : '' ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">No job categories available at the moment.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="h2 mb-5 text-center">How It Works</h2>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-4" style="width:80px; height:80px;">
                            <i class="fas fa-user-plus fa-2x"></i>
                        </div>
                        <h4>Create an Account</h4>
                        <p class="text-muted">Sign up as a job seeker or employer to get started with our platform.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-4" style="width:80px; height:80px;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                        <h4>Find Your Job</h4>
                        <p class="text-muted">Browse through our extensive job listings or post jobs for qualified candidates.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-4" style="width:80px; height:80px;">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                        <h4>Apply or Hire</h4>
                        <p class="text-muted">Apply for positions with just a click or find the perfect candidate for your opening.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card border-0 bg-primary text-white">
                    <div class="card-body p-5 text-center">
                        <h2 class="mb-4">Ready to Take the Next Step in Your Career?</h2>
                        <p class="lead mb-4">Join thousands of job seekers and employers who trust AmmooJobs to find their perfect match.</p>
                        <div class="d-flex justify-content-center gap-3 flex-wrap">
                            <?php if (!isLoggedIn()): ?>
                                <a href="register.php?type=seeker" class="btn btn-light btn-lg">Sign Up as Job Seeker</a>
                                <a href="register.php?type=employer" class="btn btn-outline-light btn-lg">Sign Up as Employer</a>
                            <?php elseif (isSeeker()): ?>
                                <a href="jobs.php" class="btn btn-light btn-lg">Browse Jobs</a>
                            <?php elseif (isEmployer()): ?>
                                <a href="post_job.php" class="btn btn-light btn-lg">Post a Job</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>