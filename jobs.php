<?php
// jobs.php
// 1) BOOTSTRAP
session_start();
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

$pageTitle = 'Browse Jobs';

require_once __DIR__ . '/includes/config.php';   // DB_HOST, DB_NAME, DB_USER, DB_PASS, DEBUG_MODE
require_once __DIR__ . '/includes/db.php';       // Instantiates $db = new Database()
require_once __DIR__ . '/includes/functions.php';// sanitizeInput(), formatSalary(), timeAgo(), etc.
require_once __DIR__ . '/includes/auth.php';     // isLoggedIn(), isEmployer(), isSeeker()
require_once __DIR__ . '/includes/session.php';  // updateLastActivity() uses isLoggedIn()

// 2) COLLECT & SANITIZE FILTERS
$filters = [
    'keyword'  => isset($_GET['keyword'])  ? sanitizeInput($_GET['keyword'])  : '',
    'location' => isset($_GET['location']) ? sanitizeInput($_GET['location']) : '',
    'category' => isset($_GET['category']) ? sanitizeInput($_GET['category']) : '',
    'job_type' => isset($_GET['job_type']) ? sanitizeInput($_GET['job_type']) : '',
    'sort'     => isset($_GET['sort'])     ? sanitizeInput($_GET['sort'])     : 'newest',
    'featured' => !empty($_GET['featured']),
];

// 3) PAGINATION SETUP
$page    = (isset($_GET['page']) && is_numeric($_GET['page'])) ? max(1,(int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// 4) QUERY CONSTRUCTION & EXECUTION
try {
    // Base
    $sql    = "SELECT j.*, u.name AS employer_name, u.company_name
               FROM job_listings j
               JOIN users u ON j.employer_id = u.user_id
               WHERE j.status = 'active'";
    $params = [];

    // Filters
    if ($filters['keyword'] !== '') {
        $sql     .= " AND (j.title LIKE ? OR j.description LIKE ?)";
        $kw       = "%" . $filters['keyword'] . "%";
        $params[] = $kw;
        $params[] = $kw;
    }
    if ($filters['location'] !== '') {
        $sql     .= " AND j.location LIKE ?";
        $params[] = "%" . $filters['location'] . "%";
    }
    if ($filters['category'] !== '') {
        $sql     .= " AND j.category = ?";
        $params[] = $filters['category'];
    }
    if ($filters['job_type'] !== '') {
        $sql     .= " AND j.job_type = ?";
        $params[] = $filters['job_type'];
    }
    if ($filters['featured']) {
        $sql .= " AND j.featured = 1";
    }

    // Total count
    $countSql   = str_replace("j.*, u.name AS employer_name, u.company_name", "COUNT(*) AS total", $sql);
    $row        = $db->fetchSingle($countSql, $params);
    $totalJobs  = $row ? (int)$row['total'] : 0;
    $totalPages = max(1, ceil($totalJobs / $perPage));

    // Sorting
    switch($filters['sort']) {
        case 'salary':
            $sql .= " ORDER BY j.salary_max DESC, j.salary_min DESC, j.featured DESC";
            break;
        case 'deadline':
            $sql .= " ORDER BY j.expires_at ASC, j.featured DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY j.created_at ASC, j.featured DESC";
            break;
        case 'newest':
        default:
            $sql .= " ORDER BY j.featured DESC, j.created_at DESC";
    }

    // Limit / Offset
    $sql       .= " LIMIT ? OFFSET ?";
    $params[]   = $perPage;
    $params[]   = $offset;

    // Fetch jobs
    $jobs       = $db->fetchAll($sql, $params);

    // Sidebar categories
    $categories = $db->fetchAll("SELECT DISTINCT category FROM job_listings WHERE status='active' ORDER BY category");

} catch (Throwable $e) {
    error_log("jobs.php error: " . $e->getMessage());
    $_SESSION['error'] = "Unable to load job listings right now.";
    $jobs       = [];
    $categories = [];
    $totalJobs  = 0;
    $totalPages = 1;
}

// 5) AJAX HANDLER
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    // Render just job cards + pagination
    foreach ($jobs as $job): ?>
      <div class="card job-card mb-3">
        <div class="card-body">
          <div class="row">
            <div class="col-md-8">
              <h5 class="card-title">
                <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none">
                  <?= htmlspecialchars($job['title']) ?>
                </a>
                <?php if ($job['featured']): ?>
                  <span class="badge bg-warning text-dark ms-2">Featured</span>
                <?php endif; ?>
              </h5>
              <p class="text-muted"><i class="fas fa-building me-1"></i>
                <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></p>
              <p><i class="fas fa-map-marker-alt me-1"></i>
                <?= htmlspecialchars($job['location']) ?></p>
            </div>
            <div class="col-md-4 text-md-end">
              <span class="badge bg-<?= $job['job_type']==='full_time'?'primary':'secondary' ?>">
                <?= ucfirst(str_replace('_',' ',$job['job_type'])) ?></span>
              <?php if ($job['salary_min']||$job['salary_max']): ?>
                <p><i class="fas fa-money-bill-wave me-1"></i>
                  <?= formatSalary($job['salary_min'],$job['salary_max']) ?></p>
              <?php endif; ?>
              <?php if (!empty($job['expires_at'])): ?>
                <p><i class="fas fa-calendar-alt me-1"></i>
                  Deadline: <?= formatDate($job['expires_at']) ?></p>
              <?php endif; ?>
              <p><small class="text-muted"><i class="fas fa-clock me-1"></i>
                <?= timeAgo($job['created_at']) ?></small></p>
              <a href="job_detail.php?id=<?= $job['job_id'] ?>"
                 class="btn btn-outline-primary btn-sm">View Details</a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach;

    // Pagination snippet
    if ($totalPages>1): ?>
      <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
          <li class="page-item <?= $page>1?'':'disabled' ?>">
            <a class="page-link" href="?page=<?= max(1,$page-1) ?>&<?= http_build_query($filters) ?>">
              &laquo;
            </a>
          </li>
          <?php
            $start = max(1,$page-2);
            $end   = min($start+4,$totalPages);
            for($i=$start;$i<=$end;$i++): ?>
              <li class="page-item <?= $i==$page?'active':'' ?>">
                <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                  <?= $i ?>
                </a>
              </li>
          <?php endfor; ?>
          <li class="page-item <?= $page<$totalPages?'':'disabled' ?>">
            <a class="page-link" href="?page=<?= min($totalPages,$page+1) ?>&<?= http_build_query($filters) ?>">
              &raquo;
            </a>
          </li>
        </ul>
      </nav>
    <?php endif;

    exit;
}

// 6) FULL PAGE RENDER
require_once __DIR__ . '/includes/header.php';
?>
<div class="row mb-4">
  <div class="col-md-8">
    <h1>Browse Jobs</h1>
    <p class="text-muted">Find the perfect job opportunity that matches your skills and career goals.</p>
  </div>
  <div class="col-md-4 text-md-end">
    <?php if (isEmployer()): ?>
      <a href="post_job.php" class="btn btn-primary">
        <i class="fas fa-plus-circle me-2"></i>Post a Job
      </a>
    <?php endif; ?>
  </div>
</div>

<div class="row">
  <!-- Filters Sidebar -->
  <aside class="col-lg-3 mb-4">
    <div class="card shadow-sm">
      <div class="card-header">Filter Jobs</div>
      <div class="card-body">
        <form method="get" action="jobs.php">
          <div class="mb-3">
            <label for="keyword" class="form-label">Keyword</label>
            <input id="keyword" name="keyword" class="form-control"
                   value="<?= htmlspecialchars($filters['keyword']) ?>">
          </div>
          <div class="mb-3">
            <label for="location" class="form-label">Location</label>
            <input id="location" name="location" class="form-control"
                   value="<?= htmlspecialchars($filters['location']) ?>">
          </div>
          <div class="mb-3">
            <label for="category" class="form-label">Category</label>
            <select id="category" name="category" class="form-select">
              <option value="">All Categories</option>
              <?php foreach(getJobCategories() as $val=>$lab): ?>
                <option value="<?= $val ?>"
                  <?= $filters['category']===$val?'selected':'' ?>>
                  <?= $lab ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Job Type</label>
            <?php foreach(getJobTypes() as $val=>$lab): ?>
              <div class="form-check">
                <input class="form-check-input" type="radio"
                       name="job_type" id="jt_<?= $val ?>" value="<?= $val ?>"
                  <?= $filters['job_type']===$val?'checked':'' ?>>
                <label class="form-check-label" for="jt_<?= $val ?>"><?= $lab ?></label>
              </div>
            <?php endforeach; ?>
            <div class="form-check">
              <input class="form-check-input" type="radio"
                     name="job_type" id="jt_all" value=""
                <?= $filters['job_type']===''?'checked':'' ?>>
              <label class="form-check-label" for="jt_all">All Types</label>
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="featured"
                   name="featured" value="1"
              <?= $filters['featured']?'checked':'' ?>>
            <label class="form-check-label" for="featured">Featured Only</label>
          </div>
          <div class="mb-3">
            <label for="sort" class="form-label">Sort By</label>
            <select id="sort" name="sort" class="form-select">
              <option value="newest"   <?= $filters['sort']==='newest'?'selected':'' ?>>Newest First</option>
              <option value="oldest"   <?= $filters['sort']==='oldest'?'selected':'' ?>>Oldest First</option>
              <option value="salary"   <?= $filters['sort']==='salary'?'selected':'' ?>>Highest Salary</option>
              <option value="deadline" <?= $filters['sort']==='deadline'?'selected':'' ?>>Application Deadline</option>
            </select>
          </div>
          <div class="d-grid gap-2">
            <button class="btn btn-primary">Apply Filters</button>
            <a href="jobs.php" class="btn btn-outline-secondary">Reset Filters</a>
          </div>
        </form>
      </div>
    </div>
  </aside>

  <!-- Job Listings -->
  <section class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h5 class="mb-0">
          <?= $totalJobs>0
             ? "Found {$totalJobs} job".($totalJobs>1?'s':'')
             : "No jobs found" ?>
        </h5>
      </div>
      <?php if ($totalJobs>0): ?>
        <select id="sortDropdown" class="form-select form-select-sm w-auto">
          <option value="newest"   <?= $filters['sort']==='newest'?'selected':'' ?>>Newest</option>
          <option value="oldest"   <?= $filters['sort']==='oldest'?'selected':'' ?>>Oldest</option>
          <option value="salary"   <?= $filters['sort']==='salary'?'selected':'' ?>>Salary</option>
          <option value="deadline" <?= $filters['sort']==='deadline'?'selected':'' ?>>Deadline</option>
        </select>
      <?php endif; ?>
    </div>

    <div id="jobResults">
      <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      <?php if ($jobs): ?>
        <?php foreach($jobs as $job): ?>
          <div class="card job-card mb-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-8">
                  <h5 class="card-title">
                    <a href="job_detail.php?id=<?= $job['job_id'] ?>" class="text-decoration-none">
                      <?= htmlspecialchars($job['title']) ?>
                    </a>
                    <?php if ($job['featured']): ?>
                      <span class="badge bg-warning text-dark ms-2">Featured</span>
                    <?php endif; ?>
                  </h5>
                  <p class="text-muted"><i class="fas fa-building me-1"></i>
                    <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></p>
                  <p><i class="fas fa-map-marker-alt me-1"></i>
                    <?= htmlspecialchars($job['location']) ?></p>
                  <p><?= mb_substr(strip_tags($job['description']),0,120) ?>…</p>
                </div>
                <div class="col-md-4 text-md-end">
                  <span class="badge bg-<?= $job['job_type']==='full_time'?'primary':'secondary' ?> mb-2">
                    <?= ucfirst(str_replace('_',' ',$job['job_type'])) ?></span>
                  <?php if ($job['salary_min']||$job['salary_max']): ?>
                    <p><i class="fas fa-money-bill-wave me-1"></i>
                      <?= formatSalary($job['salary_min'],$job['salary_max']) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($job['expires_at'])): ?>
                    <p><i class="fas fa-calendar-alt me-1"></i>
                      Deadline: <?= formatDate($job['expires_at']) ?></p>
                  <?php endif; ?>
                  <p><small class="text-muted"><i class="fas fa-clock me-1"></i>
                    <?= timeAgo($job['created_at']) ?></small></p>
                  <a href="job_detail.php?id=<?= $job['job_id'] ?>"
                     class="btn btn-outline-primary btn-sm">View Details</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($totalPages>1): ?>
          <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
              <li class="page-item <?= $page>1?'':'disabled' ?>">
                <a class="page-link" href="?page=<?= max(1,$page-1) ?>&<?= http_build_query($filters) ?>">&laquo;</a>
              </li>
              <?php
                $start = max(1,$page-2);
                $end   = min($start+4,$totalPages);
                for($i=$start;$i<=$end;$i++): ?>
                  <li class="page-item <?= $i==$page?'active':'' ?>">
                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                  </li>
              <?php endfor; ?>
              <li class="page-item <?= $page<$totalPages?'':'disabled' ?>">
                <a class="page-link" href="?page=<?= min($totalPages,$page+1) ?>&<?= http_build_query($filters) ?>">&raquo;</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="card bg-light text-center py-5">
          <div class="card-body">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <h3>No Jobs Found</h3>
            <p class="text-muted">Try adjusting your filters or come back later.</p>
            <a href="jobs.php" class="btn btn-primary">Reset Filters</a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
  document.addEventListener('DOMContentLoaded',()=>{
    const sd = document.getElementById('sortDropdown');
    if(sd) sd.onchange = ()=> {
      const p = new URLSearchParams(location.search);
      p.set('sort',sd.value);
      p.delete('page');
      location.href = 'jobs.php?'+p;
    }
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
