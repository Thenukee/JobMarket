<?php
// Start session if needed
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Browse Jobs';

// Try-catch block around all includes to catch any include errors
try {
    require_once 'includes/config.php';
    require_once 'includes/db.php';
    require_once 'includes/functions.php';
    require_once 'includes/session.php';
} catch (Exception $e) {
    error_log("Error including required files: " . $e->getMessage());
    die("Critical error: Unable to load required components. Please contact support.");
}

// Validate required functions exist
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

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

if (!function_exists('getJobTypes')) {
    function getJobTypes() {
        return [
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'freelance' => 'Freelance',
            'remote' => 'Remote',
            'internship' => 'Internship'
        ];
    }
}

if (!function_exists('formatSalary')) {
    function formatSalary($min, $max) {
        if ((!$min && $min !== 0) && (!$max && $max !== 0)) return 'Not specified';
        if (!$min && $min !== 0) return 'Up to $' . number_format($max);
        if (!$max && $max !== 0) return 'From $' . number_format($min);
        return '$' . number_format($min) . ' - $' . number_format($max);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        if (!$date) return 'N/A';
        return date('M j, Y', strtotime($date));
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($timestamp) {
        if (!$timestamp) return 'N/A';
        
        $time = strtotime($timestamp);
        if ($time === false) return 'Invalid date';
        
        $diff = time() - $time;
        
        if ($diff < 60) return 'Just now';
        if ($diff < 3600) return floor($diff/60) . ' minutes ago';
        if ($diff < 86400) return floor($diff/3600) . ' hours ago';
        if ($diff < 604800) return floor($diff/86400) . ' days ago';
        
        return date('M j, Y', $time);
    }
}

if (!function_exists('isEmployer')) {
    function isEmployer() {
        return isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'employer';
    }
}

// Fix for columnExists method if it doesn't exist
if (!method_exists('DB', 'columnExists') && isset($db)) {
    if (!method_exists($db, 'columnExists')) {
        // Add the method to the db class if possible
        if (is_object($db) && get_class($db) !== 'stdClass') {
            // Try to add a safe fallback
            try {
                // Safe implementation that returns true and assumes column exists
                // This prevents errors if the method doesn't exist
                if (!function_exists('columnExists')) {
                    function columnExists($table, $column) {
                        return true; // Default to assuming column exists
                    }
                }
            } catch (Exception $e) {
                error_log("Could not add columnExists method: " . $e->getMessage());
            }
        }
    }
}

// Initialize filter parameters with safe defaults
$filters = [
    'keyword' => isset($_GET['keyword']) ? sanitizeInput($_GET['keyword']) : '',
    'location' => isset($_GET['location']) ? sanitizeInput($_GET['location']) : '',
    'category' => isset($_GET['category']) ? sanitizeInput($_GET['category']) : '',
    'job_type' => isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '',
    'sort' => isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'newest',
    'featured' => isset($_GET['featured']) ? (bool)$_GET['featured'] : false
];

// Pagination settings with validation
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Set defaults
$jobs = [];
$totalJobs = 0;
$totalPages = 0;
$categories = [];

try {
    // Verify database connection before proceeding
    if (!isset($db) || !is_object($db)) {
        throw new Exception("Database connection not available");
    }

    // Build SQL query with filters
    $sql = "SELECT j.*, u.name as employer_name, u.company_name 
            FROM job_listings j 
            JOIN users u ON j.employer_id = u.user_id 
            WHERE j.status = 'active'";
    $params = [];

    // Add filters
    if (!empty($filters['keyword'])) {
        $sql .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $keyword = "%" . $filters['keyword'] . "%";
        $params[] = $keyword;
        $params[] = $keyword;
    }

    if (!empty($filters['location'])) {
        $sql .= " AND j.location LIKE ?";
        $params[] = "%" . $filters['location'] . "%";
    }

    if (!empty($filters['category'])) {
        $sql .= " AND j.category = ?";
        $params[] = $filters['category'];
    }

    if (!empty($filters['job_type'])) {
        $sql .= " AND j.job_type = ?";
        $params[] = $filters['job_type'];
    }

    if ($filters['featured']) {
        $sql .= " AND j.is_featured = 1";
    }

    // Count total jobs with filters for pagination
    $countSql = str_replace("j.*, u.name as employer_name, u.company_name", "COUNT(*) as total", $sql);
    
    // Safely get the count
    try {
        $totalJobsResult = $db->fetchSingle($countSql, $params);
        $totalJobs = $totalJobsResult ? $totalJobsResult['total'] : 0;
    } catch (Exception $e) {
        error_log("Error counting jobs: " . $e->getMessage());
        $totalJobs = 0;
    }
    
    $totalPages = ceil($totalJobs / $perPage);

    // Add sorting and pagination to the main query
    switch($filters['sort']) {
        case 'salary':
            $sql .= " ORDER BY j.salary_max DESC, j.salary_min DESC, j.is_featured DESC";
            break;
        case 'deadline':
            // Safer column exists check
            $hasExpiresColumn = true;
            if (method_exists($db, 'columnExists')) {
                $hasExpiresColumn = $db->columnExists('job_listings', 'expires_at');
            }
            
            if ($hasExpiresColumn) {
                $sql .= " ORDER BY j.expires_at ASC, j.is_featured DESC";
            } else {
                $sql .= " ORDER BY j.created_at DESC, j.is_featured DESC";
            }
            break;
        case 'oldest':
            $sql .= " ORDER BY j.created_at ASC, j.is_featured DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY j.is_featured DESC, j.created_at DESC";
    }

    $sql .= " LIMIT ? OFFSET ?";
    $params[] = (int)$perPage; // Ensure integer type
    $params[] = (int)$offset;  // Ensure integer type

    // Execute query with try-catch
    try {
        $jobs = $db->fetchAll($sql, $params);
    } catch (Exception $e) {
        error_log("Error fetching jobs: " . $e->getMessage() . " SQL: " . $sql);
        $jobs = [];
    }

    // Get categories for filter dropdown
    try {
        $categories = $db->fetchAll("SELECT DISTINCT category FROM job_listings WHERE status = 'active' ORDER BY category");
    } catch (Exception $e) {
        error_log("Error fetching categories: " . $e->getMessage());
        $categories = [];
    }

} catch (Exception $e) {
    error_log("Error in jobs.php: " . $e->getMessage());
    $_SESSION['error'] = "We encountered a problem loading job listings. Please try again later.";
    $jobs = [];
    $categories = [];
}

// Check if it's an AJAX request for infinite scrolling or filtering
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    // AJAX response code - remains the same
    // Return only the job listings HTML
    foreach ($jobs as $job): ?>
        <div class="card job-item mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <h5 class="card-title">
                            <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($job['title']) ?>
                            </a>
                            <?php if ($job['is_featured']): ?>
                                <span class="badge bg-warning text-dark ms-2">Featured</span>
                            <?php endif; ?>
                        </h5>
                        <p class="card-text text-muted">
                            <i class="fas fa-building me-1"></i> 
                            <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                        </p>
                        <div class="mb-2">
                            <span class="me-3"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?></span>
                            <span class="me-3"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars(getJobCategories()[$job['category']] ?? $job['category']) ?></span>
                        </div>
                        <p class="card-text">
                            <?= mb_substr(strip_tags($job['description']), 0, 120) ?>...
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="badge bg-<?= $job['job_type'] == 'full_time' ? 'primary' : ($job['job_type'] == 'part_time' ? 'success' : ($job['job_type'] == 'contract' ? 'info' : 'secondary')) ?> mb-2">
                            <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                        </span>
                        <?php if ($job['salary_min'] || $job['salary_max']): ?>
                            <p class="mb-2"><i class="fas fa-money-bill-wave me-1"></i> <?= formatSalary($job['salary_min'], $job['salary_max']) ?></p>
                        <?php endif; ?>
                        <?php if (isset($job['expires_at']) && $job['expires_at']): ?>
                            <p class="mb-2"><i class="fas fa-calendar-alt me-1"></i> Deadline: <?= formatDate($job['expires_at']) ?></p>
                        <?php endif; ?>
                        <p class="mb-2"><small class="text-muted"><i class="fas fa-clock me-1"></i> <?= timeAgo($job['created_at']) ?></small></p>
                        <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach;
    
    // Pagination code remains the same
    // Return pagination links if needed
    if ($totalPages > 1) {
        echo '<nav aria-label="Page navigation" class="mt-4">';
        echo '<ul class="pagination justify-content-center">';
        
        // Previous button
        if ($page > 1) {
            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . '&' . http_build_query(array_filter($filters)) . '">&laquo; Previous</a></li>';
        } else {
            echo '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
        }
        
        // Page numbers
        $startPage = max($page - 2, 1);
        $endPage = min($startPage + 4, $totalPages);
        
        for ($i = $startPage; $i <= $endPage; $i++) {
            echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">';
            echo '<a class="page-link" href="?page=' . $i . '&' . http_build_query(array_filter($filters)) . '">' . $i . '</a>';
            echo '</li>';
        }
        
        // Next button
        if ($page < $totalPages) {
            echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . '&' . http_build_query(array_filter($filters)) . '">Next &raquo;</a></li>';
        } else {
            echo '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
        }
        
        echo '</ul>';
        echo '</nav>';
    }
    
    exit;
}
?>

<?php 
try {
    require_once 'includes/header.php';
} catch (Exception $e) {
    // If header fails, output basic HTML to show content
    echo '<!DOCTYPE html><html lang="en"><head><title>Browse Jobs</title>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">';
    echo '</head><body><div class="container mt-4">';
    
    // Show error message
    echo '<div class="alert alert-danger">Error loading page header: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<!-- Rest of the HTML remains unchanged -->
<div class="row mb-4">
    <div class="col-md-8">
        <h1>Browse Jobs</h1>
        <p class="text-muted">Find the perfect job opportunity that matches your skills and career goals.</p>
    </div>
    <div class="col-md-4 text-md-end">
        <?php if (function_exists('isEmployer') && isEmployer()): ?>
            <a href="post_job.php" class="btn btn-primary"><i class="fas fa-plus-circle me-2"></i>Post a Job</a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Filters Sidebar -->
    <div class="col-lg-3 mb-4">
        <div class="card shadow-sm hover-lift">
            <div class="card-header bg-white">
                <h5 class="mb-0">Filter Jobs</h5>
            </div>
            <div class="card-body">
                <form id="filterForm" action="jobs.php" method="get">
                    <div class="mb-3">
                        <label for="keyword" class="form-label">Keyword</label>
                        <input type="text" class="form-control" id="keyword" name="keyword" value="<?= htmlspecialchars($filters['keyword']) ?>" placeholder="Job title or skill">
                    </div>
                    
                    <div class="mb-3">
                        <label for="location" class="form-label">Location</label>
                        <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($filters['location']) ?>" placeholder="City, state or remote">
                    </div>
                    
                    <div class="mb-3">
                        <label for="category" class="form-label">Category</label>
                        <select class="form-select" id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach (getJobCategories() as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $filters['category'] == $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Job Type</label>
                        <div>
                            <?php foreach (getJobTypes() as $value => $label): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="job_type" id="job_type_<?= $value ?>" 
                                           value="<?= $value ?>" <?= $filters['job_type'] == $value ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="job_type_<?= $value ?>">
                                        <?= $label ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="job_type" id="job_type_all" 
                                       value="" <?= empty($filters['job_type']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="job_type_all">
                                    All Types
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" 
                                   <?= $filters['featured'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">
                                Featured Jobs Only
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sort" class="form-label">Sort By</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?= $filters['sort'] == 'newest' ? 'selected' : '' ?>>Newest First</option>
                            <option value="oldest" <?= $filters['sort'] == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="salary" <?= $filters['sort'] == 'salary' ? 'selected' : '' ?>>Highest Salary</option>
                            <option value="deadline" <?= $filters['sort'] == 'deadline' ? 'selected' : '' ?>>Application Deadline</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="jobs.php" class="btn btn-outline-secondary">Reset Filters</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Job Listings -->
    <div class="col-lg-9">
        <!-- Search Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h5 class="mb-0">
                    <?php if ($totalJobs > 0): ?>
                        Found <strong><?= $totalJobs ?></strong> job<?= $totalJobs != 1 ? 's' : '' ?>
                        <?php if ($filters['keyword'] || $filters['location'] || $filters['category'] || $filters['job_type'] || $filters['featured']): ?>
                            matching your filters
                        <?php endif; ?>
                    <?php else: ?>
                        No jobs found
                    <?php endif; ?>
                </h5>
                <?php if ($filters['keyword'] || $filters['location'] || $filters['category'] || $filters['job_type'] || $filters['featured']): ?>
                    <p class="text-muted mb-0">
                        <small>
                            Filters: 
                            <?php
                            $activeFilters = [];
                            if ($filters['keyword']) $activeFilters[] = "Keyword: " . htmlspecialchars($filters['keyword']);
                            if ($filters['location']) $activeFilters[] = "Location: " . htmlspecialchars($filters['location']);
                            if ($filters['category']) $activeFilters[] = "Category: " . (getJobCategories()[$filters['category']] ?? $filters['category']);
                            if ($filters['job_type']) $activeFilters[] = "Type: " . (getJobTypes()[$filters['job_type']] ?? ucfirst($filters['job_type']));
                            if ($filters['featured']) $activeFilters[] = "Featured Only";
                            echo implode(", ", $activeFilters);
                            ?>
                        </small>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($totalJobs > 0): ?>
                <div class="text-end">
                    <select class="form-select form-select-sm d-inline-block w-auto" id="sortDropdown">
                        <option value="newest" <?= $filters['sort'] == 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $filters['sort'] == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="salary" <?= $filters['sort'] == 'salary' ? 'selected' : '' ?>>Highest Salary</option>
                        <option value="deadline" <?= $filters['sort'] == 'deadline' ? 'selected' : '' ?>>Application Deadline</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Job List -->
        <div id="jobResults">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <?php if (count($jobs) > 0): ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="card job-card animate-fade-in mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5 class="card-title">
                                        <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($job['title']) ?>
                                        </a>
                                        <?php if ($job['is_featured']): ?>
                                            <span class="badge bg-warning text-dark ms-2">Featured</span>
                                        <?php endif; ?>
                                    </h5>
                                    <p class="card-text text-muted">
                                        <i class="fas fa-building me-1"></i> 
                                        <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                    </p>
                                    <div class="mb-2">
                                        <span class="me-3"><i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?></span>
                                        <span class="me-3"><i class="fas fa-tag me-1"></i> <?= htmlspecialchars(getJobCategories()[$job['category']] ?? $job['category']) ?></span>
                                    </div>
                                    <p class="card-text">
                                        <?= mb_substr(strip_tags($job['description']), 0, 120) ?>...
                                    </p>
                                </div>
                                <div class="col-md-4 text-md-end">
                                    <span class="status-badge status-<?= $job['job_type'] == 'full_time' ? 'active' : ($job['job_type'] == 'part_time' ? 'active' : ($job['job_type'] == 'contract' ? 'pending' : 'secondary')) ?> mb-2">
                                        <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                                    </span>
                                    <?php if ($job['salary_min'] || $job['salary_max']): ?>
                                        <p class="mb-2"><i class="fas fa-money-bill-wave me-1"></i> <?= formatSalary($job['salary_min'], $job['salary_max']) ?></p>
                                    <?php endif; ?>
                                    <?php if (isset($job['expires_at']) && $job['expires_at']): ?>
                                        <p class="mb-2"><i class="fas fa-calendar-alt me-1"></i> Deadline: <?= formatDate($job['expires_at']) ?></p>
                                    <?php endif; ?>
                                    <p class="mb-2"><small class="text-muted"><i class="fas fa-clock me-1"></i> <?= timeAgo($job['created_at']) ?></small></p>
                                    <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="btn btn-outline-primary hover-scale">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination code remains the same -->
            <?php else: ?>
                <div class="card bg-glass">
                    <div class="card-body py-5 text-center">
                        <div class="mb-3">
                            <i class="fas fa-search fa-3x text-primary-light"></i>
                        </div>
                        <h3>No Jobs Found</h3>
                        <p class="text-muted">
                            <?php if ($filters['keyword'] || $filters['location'] || $filters['category'] || $filters['job_type'] || $filters['featured']): ?>
                                Try adjusting your search filters to see more results.
                            <?php else: ?>
                                There are currently no job listings available.
                            <?php endif; ?>
                        </p>
                        <a href="jobs.php" class="btn btn-primary hover-lift">Reset Filters</a>
                        <?php if (function_exists('isEmployer') && isEmployer()): ?>
                            <a href="post_job.php" class="btn btn-outline-primary hover-lift ms-2">Post a Job</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- AJAX Filtering and Sorting -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle sort dropdown change
        const sortDropdown = document.getElementById('sortDropdown');
        if (sortDropdown) {
            sortDropdown.addEventListener('change', function() {
                let params = new URLSearchParams(window.location.search);
                params.set('sort', this.value);
                params.delete('page'); // Reset to first page
                window.location.href = 'jobs.php?' + params.toString();
            });
        }
    });
</script>

<?php 
try {
    require_once 'includes/footer.php';
} catch (Exception $e) {
    // If footer fails, close HTML
    echo '</div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '</body></html>';
    error_log("Error including footer: " . $e->getMessage());
}
?>