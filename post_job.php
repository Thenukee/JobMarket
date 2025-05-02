<?php
$pageTitle = 'Post a Job';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Only employers can post jobs
requireEmployer();

// Get employer data
$userId = $_SESSION['user_id'];
$employer = getUserById($userId);

// Check if editing existing job
$isEditing = false;
$job = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $jobId = (int)$_GET['edit'];
    $job = $db->fetchSingle("SELECT * FROM job_listings WHERE job_id = ? AND employer_id = ?", 
                           [$jobId, $userId]);
    
    if ($job) {
        $isEditing = true;
        $pageTitle = 'Edit Job';
    } else {
        $_SESSION['error'] = "Job not found or you don't have permission to edit it.";
        header("Location: dashboard.php");
        exit;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: post_job.php");
        exit;
    }
    
    // Sanitize and validate input
    $title = sanitizeInput($_POST['title']);
    $description = $_POST['description']; // Allow HTML formatting
    $responsibilities = $_POST['responsibilities']; // Allow HTML formatting
    $requirements = $_POST['requirements']; // Allow HTML formatting
    $location = sanitizeInput($_POST['location']);
    $jobType = sanitizeInput($_POST['job_type']);
    $category = sanitizeInput($_POST['category']);
    $salaryMin = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
    $salaryMax = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
    $applicationDeadline = !empty($_POST['application_deadline']) ? sanitizeInput($_POST['application_deadline']) : null;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $status = sanitizeInput($_POST['status']);
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = "Job title is required.";
    }
    
    if (empty($description)) {
        $errors[] = "Job description is required.";
    }
    
    if (empty($location)) {
        $errors[] = "Job location is required.";
    }
    
    if ($salaryMax && $salaryMin && $salaryMax < $salaryMin) {
        $errors[] = "Maximum salary cannot be less than minimum salary.";
    }
    
    // If no errors, save the job
    if (empty($errors)) {
        $jobData = [
            'employer_id' => $userId,
            'title' => $title,
            'description' => $description,
            'responsibilities' => $responsibilities,
            'requirements' => $requirements,
            'location' => $location,
            'job_type' => $jobType,
            'category' => $category,
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'application_deadline' => $applicationDeadline,
            'is_featured' => $isFeatured,
            'status' => $status
        ];
        // Add required timestamps
        $jobData['created_at'] = date('Y-m-d H:i:s');
        $jobData['updated_at'] = date('Y-m-d H:i:s');

        // Fix status mapping
        if ($jobData['status'] == 'open') {
            $jobData['status'] = 'active';
        } elseif ($jobData['status'] == 'closed') {
            $jobData['status'] = 'inactive';
        } elseif ($jobData['status'] == 'draft') {
            $jobData['status'] = 'pending';
        }

        // Remove fields that don't exist in the database
        if (!columnExists('job_listings', 'responsibilities')) {
            unset($jobData['responsibilities']);
        }

        // Calculate expires_at based on application_deadline if provided
        if (!empty($jobData['application_deadline'])) {
            $jobData['expires_at'] = $jobData['application_deadline'] . ' 23:59:59';
            unset($jobData['application_deadline']); // Remove non-existent field
        }
        if ($isEditing) {
            // Update existing job
            $jobId = $job['job_id'];
            
            // Build update query
            $setClause = "";
            $params = [];
            
            foreach ($jobData as $key => $value) {
                $setClause .= "$key = ?, ";
                $params[] = $value;
            }
            
            $setClause = rtrim($setClause, ", ");
            $params[] = $jobId;
            
            $sql = "UPDATE job_listings SET $setClause WHERE job_id = ?";
            $result = $db->executeNonQuery($sql, $params);
            
            if ($result) {
                $_SESSION['success'] = "Job listing updated successfully.";
                header("Location: job_detail.php?id=$jobId");
                exit;
            } else {
                $_SESSION['error'] = "Failed to update job listing. Please try again.";
            }
        } else {
            // Insert new job
            $columns = implode(', ', array_keys($jobData));
            $placeholders = implode(', ', array_fill(0, count($jobData), '?'));
            
            $sql = "INSERT INTO job_listings ($columns) VALUES ($placeholders)";
            $result = $db->executeNonQuery($sql, array_values($jobData));
            
            if ($result) {
                $jobId = $db->getLastId();
                $_SESSION['success'] = "Job listing created successfully.";
                header("Location: job_detail.php?id=$jobId");
                exit;
            } else {
                $_SESSION['error'] = "Failed to create job listing. Please try again.";
            }
        }
    } else {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title mb-4"><?= $isEditing ? 'Edit Job Listing' : 'Post a New Job' ?></h2>
                
                <form id="jobPostForm" method="POST" action="<?= $isEditing ? "post_job.php?edit={$job['job_id']}" : "post_job.php" ?>" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <!-- Basic Job Information -->
                    <div class="mb-4">
                        <h5>Basic Information</h5>
                        <hr>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Job Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?= $isEditing ? htmlspecialchars($job['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Job Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <?php foreach (getJobCategories() as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $isEditing && $job['category'] == $value ? 'selected' : (isset($_POST['category']) && $_POST['category'] == $value ? 'selected' : '') ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="job_type" class="form-label">Employment Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="job_type" name="job_type" required>
                                    <option value="">Select Type</option>
                                    <?php foreach (getJobTypes() as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= $isEditing && $job['job_type'] == $value ? 'selected' : (isset($_POST['job_type']) && $_POST['job_type'] == $value ? 'selected' : '') ?>>
                                            <?= $label ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="location" name="location" required placeholder="City, State or Remote"
                                       value="<?= $isEditing ? htmlspecialchars($job['location']) : (isset($_POST['location']) ? htmlspecialchars($_POST['location']) : '') ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="salary_min" class="form-label">Minimum Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="salary_min" name="salary_min" placeholder="Optional"
                                           value="<?= $isEditing && $job['salary_min'] ? $job['salary_min'] : (isset($_POST['salary_min']) ? $_POST['salary_min'] : '') ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="salary_max" class="form-label">Maximum Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control" id="salary_max" name="salary_max" placeholder="Optional"
                                           value="<?= $isEditing && $job['salary_max'] ? $job['salary_max'] : (isset($_POST['salary_max']) ? $_POST['salary_max'] : '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="application_deadline" class="form-label">Application Deadline</label>
                            <input type="date" class="form-control" id="application_deadline" name="application_deadline"
                                   min="<?= date('Y-m-d') ?>"
                                   value="<?= $isEditing && $job['application_deadline'] ? date('Y-m-d', strtotime($job['application_deadline'])) : (isset($_POST['application_deadline']) ? $_POST['application_deadline'] : '') ?>">
                            <small class="text-muted">Leave blank for no deadline</small>
                        </div>
                    </div>
                    
                    <!-- Job Description -->
                    <div class="mb-4">
                        <h5>Job Details</h5>
                        <hr>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Job Description <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="description" name="description" rows="6" required><?= $isEditing ? $job['description'] : (isset($_POST['description']) ? $_POST['description'] : '') ?></textarea>
                            <small class="text-muted">Provide an overview of the position and your company.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="responsibilities" class="form-label">Responsibilities</label>
                            <textarea class="form-control" id="responsibilities" name="responsibilities" rows="4"><?= $isEditing ? $job['responsibilities'] : (isset($_POST['responsibilities']) ? $_POST['responsibilities'] : '') ?></textarea>
                            <small class="text-muted">List the main duties and responsibilities of the role.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="requirements" class="form-label">Requirements</label>
                            <textarea class="form-control" id="requirements" name="requirements" rows="4"><?= $isEditing ? $job['requirements'] : (isset($_POST['requirements']) ? $_POST['requirements'] : '') ?></textarea>
                            <small class="text-muted">Specify qualifications, skills, and experience needed.</small>
                        </div>
                    </div>
                    
                    <!-- Job Status & Options -->
                    <div class="mb-4">
                        <h5>Listing Options</h5>
                        <hr>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" value="1"
                                       <?= $isEditing && $job['is_featured'] ? 'checked' : (isset($_POST['is_featured']) ? 'checked' : '') ?>>
                                <label class="form-check-label" for="is_featured">
                                    Feature this job (appears at the top of search results)
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Job Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="open" <?= $isEditing && $job['status'] == 'open' ? 'selected' : (!$isEditing ? 'selected' : '') ?>>
                                    Open (Accepting Applications)
                                </option>
                                <option value="closed" <?= $isEditing && $job['status'] == 'closed' ? 'selected' : '' ?>>
                                    Closed (Not Accepting Applications)
                                </option>
                                <option value="draft" <?= $isEditing && $job['status'] == 'draft' ? 'selected' : '' ?>>
                                    Draft (Not Visible to Job Seekers)
                                </option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg"><?= $isEditing ? 'Update Job Listing' : 'Post Job' ?></button>
                        <a href="<?= $isEditing ? "job_detail.php?id={$job['job_id']}" : "dashboard.php" ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Form Tips -->
        <div class="card mt-4 bg-light">
            <div class="card-body">
                <h5 class="card-title">Tips for Creating an Effective Job Posting</h5>
                <ul class="mb-0">
                    <li>Be specific about the role and responsibilities</li>
                    <li>Clearly state required qualifications and experience</li>
                    <li>Include information about your company culture</li>
                    <li>Specify whether the position is remote, in-office, or hybrid</li>
                    <li>Provide salary information if possible to attract qualified candidates</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Rich Text Editor Script -->
<script src="https://cdn.tiny.cloud/1/ukmrehn0iqw68m7canq8aqc9r9os7z0v2m31aqevnj02n7kp/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
<script>
    tinymce.init({
        selector: '#description, #responsibilities, #requirements',
        height: 300,
        menubar: false,
        plugins: [
            'advlist autolink lists link image charmap print preview anchor',
            'searchreplace visualblocks code fullscreen',
            'insertdatetime media table paste code help wordcount'
        ],
        toolbar: 'undo redo | formatselect | ' +
        'bold italic backcolor | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'removeformat | help',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; }'
    });
</script>

<?php require_once 'includes/footer.php'; ?>