<?php
// Make sure there is absolutely no whitespace before this opening tag
session_start();

// Show all errors in development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Home';

require_once 'includes/config.php';
require_once 'includes/db.php';         // $db = new Database();
require_once 'includes/functions.php';
require_once 'includes/auth.php';       // isLoggedIn(), isEmployer(), isSeeker()
require_once 'includes/header.php';

try {
    // FEATURED JOBS (limit 6)
    $featuredJobs = $db->fetchAll("
        SELECT j.*, u.name AS employer_name, u.company_name 
        FROM job_listings j 
        JOIN users u ON j.employer_id = u.user_id 
        WHERE j.status = 'active' 
          AND j.featured = 1 
        ORDER BY j.created_at DESC 
        LIMIT 6
    ");

    // RECENT JOBS (limit 8)
    $recentJobs = $db->fetchAll("
        SELECT j.*, u.name AS employer_name, u.company_name 
        FROM job_listings j 
        JOIN users u ON j.employer_id = u.user_id 
        WHERE j.status = 'active' 
        ORDER BY j.created_at DESC 
        LIMIT 8
    ");

    // CATEGORY COUNTS
    $categories = $db->fetchAll("
        SELECT category, COUNT(*) AS count 
        FROM job_listings 
        WHERE status = 'active' 
        GROUP BY category 
        ORDER BY count DESC 
        LIMIT 8
    ");

    // STATS
    $stats = [
        'jobs'       => (int)$db->fetchSingle("SELECT COUNT(*) AS count FROM job_listings WHERE status = 'active'")['count'],
        'companies'  => (int)$db->fetchSingle("SELECT COUNT(DISTINCT employer_id) AS count FROM job_listings")['count'],
        'candidates' => (int)$db->fetchSingle("SELECT COUNT(*) AS count FROM users WHERE user_type = 'seeker'")['count'],
    ];

} catch (Throwable $e) {
    error_log("Database error: " . $e->getMessage());
    $featuredJobs = $recentJobs = $categories = [];
    $stats = ['jobs'=>0,'companies'=>0,'candidates'=>0];
}

// Fallback helpers if missing
if (!function_exists('getJobCategories')) {
    function getJobCategories() {
        return [
            'technology'=>'Technology','healthcare'=>'Healthcare','education'=>'Education',
            'finance'=>'Finance','marketing'=>'Marketing','engineering'=>'Engineering',
            'hospitality'=>'Hospitality','retail'=>'Retail','administrative'=>'Administrative',
            'other'=>'Other'
        ];
    }
}

if (!function_exists('formatSalary')) {
    function formatSalary($min, $max) {
        if (!$min && !$max) return 'Not specified';
        if (!$min) return 'Up to $' . number_format($max);
        if (!$max) return 'From $' . number_format($min);
        return '$' . number_format($min) . ' - $' . number_format($max);
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($timestamp) {
        if (!$timestamp) return '';
        $diff = time() - strtotime($timestamp);
        if ($diff < 60)      return 'Just now';
        if ($diff < 3600)    return floor($diff/60) . ' minutes ago';
        if ($diff < 86400)   return floor($diff/3600) . ' hours ago';
        if ($diff < 604800)  return floor($diff/86400) . ' days ago';
        return date('M j, Y', strtotime($timestamp));
    }
}
?>

<!-- Hero Section -->
<section class="hero py-5 bg-primary text-white">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 mb-4 mb-lg-0">
        <h1 class="display-4 fw-bold mb-3">Find Your Dream Job Today</h1>
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
        <div class="bg-white p-4 rounded shadow">
          <h4 class="mb-3">Search Jobs</h4>
          <form action="jobs.php" method="get">
            <div class="mb-3">
              <label for="searchKeyword" class="form-label">Keywords</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" id="searchKeyword" name="keyword" class="form-control" placeholder="Job title, skills, or company">
              </div>
            </div>
            <div class="mb-3">
              <label for="searchLocation" class="form-label">Location</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                <input type="text" id="searchLocation" name="location" class="form-control" placeholder="City, state, or remote">
              </div>
            </div>
            <div class="mb-3">
              <label for="searchCategory" class="form-label">Category</label>
              <select id="searchCategory" name="category" class="form-select">
                <option value="">All Categories</option>
                <?php foreach(getJobCategories() as $val=>$lab): ?>
                  <option value="<?= $val ?>"><?= $lab ?></option>
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
      <h2>Featured Jobs</h2>
      <a href="jobs.php?featured=1" class="btn btn-outline-primary">View All</a>
    </div>
    <div class="row">
      <?php if ($featuredJobs): ?>
        <?php foreach ($featuredJobs as $job): ?>
          <div class="col-md-4 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="badge bg-warning text-dark">Featured</span>
                  <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                </div>
                <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                <p class="text-muted"><i class="fas fa-building me-1"></i>
                  <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></p>
                <p><i class="fas fa-map-marker-alt me-1"></i>
                  <?= htmlspecialchars($job['location']) ?></p>
              </div>
              <div class="card-footer text-end">
                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-primary btn-sm">View Details</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12"><div class="alert alert-info">No featured jobs available.</div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Recent Jobs -->
<section class="py-5 bg-light">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2>Recently Added Jobs</h2>
      <a href="jobs.php" class="btn btn-outline-primary">Browse All</a>
    </div>
    <div class="row">
      <?php if ($recentJobs): ?>
        <?php foreach ($recentJobs as $job): ?>
          <div class="col-md-6 mb-4">
            <div class="card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                  <span class="badge bg-<?= $job['job_type']=='full_time'?'primary':'secondary' ?>">
                    <?= ucfirst(str_replace('_',' ',$job['job_type'])) ?></span>
                  <small class="text-muted"><?= timeAgo($job['created_at']) ?></small>
                </div>
                <h5 class="card-title"><?= htmlspecialchars($job['title']) ?></h5>
                <p class="text-muted"><i class="fas fa-building me-1"></i>
                  <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></p>
                <p><i class="fas fa-map-marker-alt me-1"></i>
                  <?= htmlspecialchars($job['location']) ?></p>
              </div>
              <div class="card-footer text-end">
                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary btn-sm">View Details</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12"><div class="alert alert-info">No recent jobs found.</div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Categories -->
<section class="py-5">
  <div class="container">
    <h2 class="text-center mb-4">Popular Job Categories</h2>
    <div class="row">
      <?php if ($categories): ?>
        <?php foreach ($categories as $cat): ?>
          <div class="col-md-3 mb-4">
            <a href="jobs.php?category=<?= urlencode($cat['category']) ?>" class="text-decoration-none">
              <div class="card h-100 text-center">
                <div class="card-body">
                  <h5><?= htmlspecialchars(getJobCategories()[$cat['category']] ?? ucfirst($cat['category'])) ?></h5>
                  <p class="text-muted mb-0"><?= $cat['count'] ?> open position<?= $cat['count']!=1?'s':'' ?></p>
                </div>
              </div>
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12"><div class="alert alert-info">No categories to show.</div></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require_once 'includes/footer.php'; ?>
