<?php
$pageTitle = 'Job Detail';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid job ID.";
    header("Location: jobs.php");
    exit;
}

$jobId = (int)$_GET['id'];

// Get job details
$job = getJobById($jobId);

if (!$job) {
    $_SESSION['error'] = "Job not found.";
    header("Location: jobs.php");
    exit;
}

// Update page title
$pageTitle = $job['title'];

// Get employer details
$employer = getUserById($job['employer_id']);

// Get employer rating
$employerRating = getEmployerRating($job['employer_id']);

// Check if user has already applied (for job seekers)
$hasApplied = false;
if (isSeeker()) {
    $hasApplied = hasApplied($_SESSION['user_id'], $jobId);
}

// Get similar jobs - FIXED: Changed 'open' to 'active'
$similarJobs = $db->fetchAll("SELECT j.*, u.name as employer_name, u.company_name 
                             FROM job_listings j 
                             JOIN users u ON j.employer_id = u.user_id 
                             WHERE j.status = 'active' 
                             AND j.category = ? 
                             AND j.job_id != ? 
                             ORDER BY j.is_featured DESC, j.created_at DESC 
                             LIMIT 3", 
                             [$job['category'], $jobId]);

// Handle application submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_job']) && isSeeker()) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: job_detail.php?id=$jobId");
        exit;
    }
    
    $seekerId = $_SESSION['user_id'];
    $coverLetter = isset($_POST['cover_letter']) ? $_POST['cover_letter'] : '';
    $useExisting = isset($_POST['use_existing_resume']) && $_POST['use_existing_resume'] == '1';
    
    // Validation
    $errors = [];
    
    if (empty($coverLetter)) {
        $errors[] = "Cover letter is required.";
    }
    
    // Check if already applied
    if (hasApplied($seekerId, $jobId)) {
        $errors[] = "You have already applied for this job.";
    }
    
    // Resume handling
    $resumePath = null;
    
    if ($useExisting) {
        // Check if user has an existing resume
        $existingResume = $db->fetchSingle("SELECT resume_path FROM applications 
                                          WHERE seeker_id = ? AND resume_path IS NOT NULL 
                                          ORDER BY created_at DESC LIMIT 1", 
                                          [$seekerId]);
        
        if ($existingResume) {
            $resumePath = $existingResume['resume_path'];
        } else {
            $errors[] = "No existing resume found. Please upload a resume.";
        }
    } else {
        // Handle new resume upload
        if (!isset($_FILES['resume']) || $_FILES['resume']['error'] != UPLOAD_ERR_OK) {
            $errors[] = "Resume upload is required.";
        } else {
            $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $fileType = $_FILES['resume']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Only PDF and Word documents are allowed.";
            }
            
            if ($_FILES['resume']['size'] > 5000000) { // 5MB limit
                $errors[] = "Resume file size must be less than 5MB.";
            }
            
            if (empty($errors)) {
                $fileName = time() . '_' . $_SESSION['user_id'] . '_' . basename($_FILES['resume']['name']);
                $targetFilePath = RESUME_PATH . $fileName;
                
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $targetFilePath)) {
                    $resumePath = $fileName;
                } else {
                    $errors[] = "Failed to upload resume. Please try again.";
                }
            }
        }
    }
    
    // If no errors, submit the application
    if (empty($errors)) {
        $result = $db->executeNonQuery("INSERT INTO applications (job_id, seeker_id, cover_letter, resume_path, status) 
                                      VALUES (?, ?, ?, ?, 'pending')", 
                                      [$jobId, $seekerId, $coverLetter, $resumePath]);
        
        if ($result) {
            $_SESSION['success'] = "Your application has been submitted successfully.";
            header("Location: application_success.php?job_id=$jobId");
            exit;
        } else {
            $_SESSION['error'] = "Failed to submit application. Please try again.";
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Handle AJAX application
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_ajax']) && isSeeker()) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => "Invalid form submission."]);
        exit;
    }
    
    $seekerId = $_SESSION['user_id'];
    
    // Check if already applied
    if (hasApplied($seekerId, $jobId)) {
        echo json_encode(['success' => false, 'message' => "You have already applied for this job."]);
        exit;
    }
    
    // Check if job is still active - FIXED: Changed 'open' to 'active'
    if ($job['status'] != 'active') {
        echo json_encode(['success' => false, 'message' => "This job is no longer accepting applications."]);
        exit;
    }
    
    // Redirect to application form
    echo json_encode(['success' => true, 'redirect' => "apply.php?job_id=$jobId"]);
    exit;
}
?>

<?php require_once 'includes/header.php'; ?>

<!-- Job Header - Updated with modern styling -->
<section class="py-4 bg-light">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="jobs.php">Jobs</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($job['title']) ?></li>
                    </ol>
                </nav>
                <h1 class="mb-2"><?= htmlspecialchars($job['title']) ?></h1>
                <div class="d-flex flex-wrap align-items-center mb-3">
                    <span class="me-3 text-gray-600">
                        <i class="fas fa-building me-1 text-primary"></i> 
                        <?= htmlspecialchars($job['company_name'] ?: $employer['name']) ?>
                    </span>
                    <span class="me-3 text-gray-600">
                        <i class="fas fa-map-marker-alt me-1 text-primary"></i> 
                        <?= htmlspecialchars($job['location']) ?>
                    </span>
                    <!-- FIXED: Changed job_type comparison to use underscores -->
                    <span class="badge bg-<?= $job['job_type'] == 'full_time' ? 'primary' : ($job['job_type'] == 'part_time' ? 'success' : ($job['job_type'] == 'contract' ? 'info' : 'secondary')) ?> me-2">
                        <?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?>
                    </span>
                    <?php if ($job['is_featured']): ?>
                        <span class="badge bg-warning text-dark">Featured</span>
                    <?php endif; ?>
                </div>
                <p class="text-muted mb-0">
                    <i class="fas fa-clock me-1 text-primary"></i> Posted <?= timeAgo($job['created_at']) ?>
                    <!-- FIXED: Changed application_deadline to expires_at -->
                    <?php if (isset($job['expires_at']) && $job['expires_at']): ?>
                        <span class="ms-3">
                            <i class="fas fa-calendar-alt me-1 text-primary"></i> Deadline: <?= formatDate($job['expires_at']) ?>
                            <?php 
                            $daysLeft = floor((strtotime($job['expires_at']) - time()) / (60 * 60 * 24));
                            if ($daysLeft > 0): 
                            ?>
                                <span class="badge bg-info ms-1"><?= $daysLeft ?> day<?= $daysLeft != 1 ? 's' : '' ?> left</span>
                            <?php elseif ($daysLeft == 0): ?>
                                <span class="badge bg-danger ms-1">Last day</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if (isSeeker()): ?>
                    <?php if ($hasApplied): ?>
                        <button class="btn btn-success" disabled>
                            <i class="fas fa-check-circle me-2"></i>Applied
                        </button>
                    <!-- FIXED: Changed status check from 'open' to 'active' -->
                    <?php elseif ($job['status'] == 'active'): ?>
                        <a href="apply.php?job_id=<?= $jobId ?>" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Apply Now
                        </a>
                        <button type="button" class="btn btn-outline-primary ms-2" onclick="saveJob(<?= $jobId ?>)">
                            <i class="far fa-bookmark me-1"></i>Save
                        </button>
                    <?php else: ?>
                        <button class="btn btn-secondary" disabled>
                            Applications Closed
                        </button>
                    <?php endif; ?>
                <?php elseif (isEmployer() && $job['employer_id'] == $_SESSION['user_id']): ?>
                    <a href="applicants.php?job_id=<?= $jobId ?>" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>View Applicants
                    </a>
                    <a href="post_job.php?edit=<?= $jobId ?>" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                <?php elseif (!isLoggedIn()): ?>
                    <a href="login.php?redirect=job_detail.php?id=<?= $jobId ?>" class="btn btn-primary">
                        Login to Apply
                    </a>
                    <a href="register.php?type=seeker&redirect=job_detail.php?id=<?= $jobId ?>" class="btn btn-outline-primary ms-2">
                        Register
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="container py-4">
    <div class="row">
        <!-- Main Job Content -->
        <div class="col-lg-8">
            <!-- Job Description -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Description</h5>
                </div>
                <div class="card-body">
                    <div class="job-description mb-4">
                        <?= $job['description'] ?>
                    </div>
                    
                    <?php if (!empty($job['responsibilities'])): ?>
                    <div class="mb-4">
                        <h5>Responsibilities</h5>
                        <hr>
                        <?= $job['responsibilities'] ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($job['requirements'])): ?>
                    <div class="mb-4">
                        <h5>Requirements</h5>
                        <hr>
                        <?= $job['requirements'] ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- FIXED: Changed status check from 'open' to 'active' -->
                    <?php if (isSeeker() && !$hasApplied && $job['status'] == 'active'): ?>
                    <div class="text-center mt-4">
                        <a href="apply.php?job_id=<?= $jobId ?>" class="btn btn-lg btn-primary">
                            Apply for this Position
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Company Overview -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">About the Employer</h5>
                    <a href="employer_profile.php?id=<?= $job['employer_id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                </div>
                <div class="card-body">
                    <div class="d-flex">
                        <?php if (!empty($employer['profile_image'])): ?>
                            <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($employer['profile_image']) ?>" class="employer-logo me-3">
                        <?php else: ?>
                            <div class="employer-img-placeholder me-3">
                                <i class="fas fa-building text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h5><?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?></h5>
                            <?php if ($employerRating['count'] > 0): ?>
                            <div class="rating mb-2">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= round($employerRating['average'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - 0.5 <= $employerRating['average']): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="ms-1"><?= $employerRating['average'] ?> (<?= $employerRating['count'] ?> reviews)</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($employer['location'])): ?>
                            <p class="mb-1"><i class="fas fa-map-marker-alt me-2 text-primary"></i><?= htmlspecialchars($employer['location']) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($employer['website'])): ?>
                            <p class="mb-1"><i class="fas fa-globe me-2 text-primary"></i><a href="<?= htmlspecialchars($employer['website']) ?>" target="_blank"><?= htmlspecialchars($employer['website']) ?></a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($employer['bio'])): ?>
                    <div class="mt-3">
                        <p><?= nl2br(htmlspecialchars($employer['bio'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <a href="employer_jobs.php?id=<?= $job['employer_id'] ?>" class="btn btn-outline-primary mt-2">
                        View All Jobs from this Employer
                    </a>
                </div>
            </div>
            
            <!-- Similar Jobs -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Similar Jobs</h5>
                </div>
                <div class="card-body">
                    <?php if (count($similarJobs) > 0): ?>
                        <div class="row">
                            <?php foreach ($similarJobs as $similarJob): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 job-item hover-lift">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="job_detail.php?id=<?= $similarJob['job_id'] ?>" class="text-decoration-none">
                                                <?= htmlspecialchars($similarJob['title']) ?>
                                            </a>
                                        </h6>
                                        <p class="card-text text-muted small">
                                            <i class="fas fa-building me-1 text-primary"></i> 
                                            <?= htmlspecialchars($similarJob['company_name'] ?: $similarJob['employer_name']) ?>
                                        </p>
                                        <div class="mb-2">
                                            <small class="d-block mb-1"><i class="fas fa-map-marker-alt me-1 text-primary"></i> <?= htmlspecialchars($similarJob['location']) ?></small>
                                            <!-- FIXED: Changed job_type comparison to use underscores -->
                                            <span class="badge bg-<?= $similarJob['job_type'] == 'full_time' ? 'primary' : ($similarJob['job_type'] == 'part_time' ? 'success' : ($similarJob['job_type'] == 'contract' ? 'info' : 'secondary')) ?>">
                                                <?= ucfirst(str_replace('_', ' ', $similarJob['job_type'])) ?>
                                            </span>
                                        </div>
                                        <a href="job_detail.php?id=<?= $similarJob['job_id'] ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No similar jobs found.</p>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="jobs.php?category=<?= urlencode($job['category']) ?>" class="btn btn-outline-primary">
                            Browse More <?= htmlspecialchars(getJobCategories()[$job['category']] ?? ucfirst($job['category'])) ?> Jobs
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Job Summary -->
            <div class="card job-meta mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Job Summary</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3">
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            <strong>Posted Date:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($job['created_at'])) ?></span>
                        </li>
                        
                        <!-- FIXED: Changed application_deadline to expires_at -->
                        <?php if (isset($job['expires_at']) && $job['expires_at']): ?>
                        <li class="mb-3">
                            <i class="fas fa-hourglass-end me-2 text-primary"></i>
                            <strong>Application Deadline:</strong>
                            <span class="float-end"><?= date('M j, Y', strtotime($job['expires_at'])) ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                            <strong>Location:</strong>
                            <span class="float-end"><?= htmlspecialchars($job['location']) ?></span>
                        </li>
                        
                        <li class="mb-3">
                            <i class="fas fa-briefcase me-2 text-primary"></i>
                            <strong>Job Type:</strong>
                            <!-- FIXED: Changed job_type display format -->
                            <span class="float-end"><?= ucfirst(str_replace('_', ' ', $job['job_type'])) ?></span>
                        </li>
                        
                        <li class="mb-3">
                            <i class="fas fa-tag me-2 text-primary"></i>
                            <strong>Category:</strong>
                            <span class="float-end"><?= htmlspecialchars(getJobCategories()[$job['category']] ?? ucfirst($job['category'])) ?></span>
                        </li>
                        
                        <?php if ($job['salary_min'] || $job['salary_max']): ?>
                        <li class="mb-3">
                            <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                            <strong>Salary:</strong>
                            <span class="float-end"><?= formatSalary($job['salary_min'], $job['salary_max']) ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <li>
                            <i class="fas fa-users me-2 text-primary"></i>
                            <strong>Applications:</strong>
                            <span class="float-end">
                                <?php 
                                $appCount = $db->fetchSingle("SELECT COUNT(*) as count FROM applications WHERE job_id = ?", [$jobId])['count']; 
                                echo $appCount;
                                ?>
                            </span>
                        </li>
                    </ul>
                    
                    <?php if (isSeeker()): ?>
                        <hr>
                        <div class="d-grid">
                            <?php if ($hasApplied): ?>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle me-2"></i>Applied
                                </button>
                            <!-- FIXED: Changed status check from 'open' to 'active' -->
                            <?php elseif ($job['status'] == 'active'): ?>
                                <a href="apply.php?job_id=<?= $jobId ?>" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Apply Now
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>
                                    Applications Closed
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="text-center mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="saveJob(<?= $jobId ?>)">
                                <i class="far fa-bookmark me-1"></i> Save Job
                            </button>
                        </div>
                    <?php elseif (!isLoggedIn()): ?>
                        <hr>
                        <div class="d-grid gap-2">
                            <a href="login.php?redirect=job_detail.php?id=<?= $jobId ?>" class="btn btn-primary">
                                Login to Apply
                            </a>
                            <a href="register.php?type=seeker&redirect=job_detail.php?id=<?= $jobId ?>" class="btn btn-outline-primary">
                                Register as Job Seeker
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Share Job -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Share This Job</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(SITE_URL . '/job_detail.php?id=' . $jobId) ?>" target="_blank" class="btn btn-outline-primary w-100 me-2">
                            <i class="fab fa-facebook-f me-2"></i>Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=<?= urlencode(SITE_URL . '/job_detail.php?id=' . $jobId) ?>&text=<?= urlencode('Check out this job: ' . $job['title']) ?>" target="_blank" class="btn btn-outline-info w-100 me-2">
                            <i class="fab fa-twitter me-2"></i>Twitter
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode(SITE_URL . '/job_detail.php?id=' . $jobId) ?>" target="_blank" class="btn btn-outline-secondary w-100">
                            <i class="fab fa-linkedin-in me-2"></i>LinkedIn
                        </a>
                    </div>
                    <div class="input-group">
                        <input type="text" id="job-url" class="form-control" value="<?= SITE_URL ?>/job_detail.php?id=<?= $jobId ?>" readonly>
                        <button class="btn btn-outline-primary" type="button" onclick="copyJobUrl()">
                            <i class="fas fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Report Job -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="mb-2"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Report This Job</p>
                    <p class="small text-muted mb-2">If you believe this job is inappropriate or violates our terms of service, please report it.</p>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                        Report Issue
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Job Modal -->
<div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportModalLabel">Report This Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm" method="post" action="report_job.php">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="job_id" value="<?= $jobId ?>">
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Report:</label>
                        <select class="form-select" id="reason" name="reason" required>
                            <option value="">-- Select Reason --</option>
                            <option value="spam">Spam or Misleading</option>
                            <option value="inappropriate">Inappropriate Content</option>
                            <option value="scam">Fraudulent Job / Scam</option>
                            <option value="duplicate">Duplicate Posting</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="details" class="form-label">Additional Details:</label>
                        <textarea class="form-control" id="details" name="details" rows="3" required></textarea>
                    </div>
                    
                    <?php if (!isLoggedIn()): ?>
                    <div class="mb-3">
                        <label for="reporter_email" class="form-label">Your Email:</label>
                        <input type="email" class="form-control" id="reporter_email" name="reporter_email" required>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="reportForm" class="btn btn-danger">Submit Report</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Copy job URL function
    function copyJobUrl() {
        const jobUrl = document.getElementById('job-url');
        jobUrl.select();
        jobUrl.setSelectionRange(0, 99999);
        document.execCommand('copy');
        
        // Show tooltip or alert that URL was copied
        alert('Job URL copied to clipboard!');
    }
    
    // Save job function
    function saveJob(jobId) {
        // Check if user is logged in
        <?php if (!isLoggedIn()): ?>
            window.location.href = 'login.php?redirect=job_detail.php?id=<?= $jobId ?>';
            return;
        <?php endif; ?>
        
        fetch('save_job.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'job_id=' + jobId + '&csrf_token=<?= generateCSRFToken() ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Job saved to your favorites!');
            } else {
                alert(data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>