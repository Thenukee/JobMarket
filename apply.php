<?php
$pageTitle = 'Apply for Job';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Only job seekers can apply for jobs
requireSeeker();

// Check if job ID is provided
if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    $_SESSION['error'] = "Invalid job ID.";
    header("Location: jobs.php");
    exit;
}

$jobId = (int)$_GET['job_id'];
$seekerId = $_SESSION['user_id'];

// Get job details
$job = getJobById($jobId);

if (!$job) {
    $_SESSION['error'] = "Job not found.";
    header("Location: jobs.php");
    exit;
}

// Check if job is still open
if ($job['status'] != 'open') {
    $_SESSION['error'] = "This job is no longer accepting applications.";
    header("Location: job_detail.php?id=$jobId");
    exit;
}

// Check if user has already applied
if (hasApplied($seekerId, $jobId)) {
    $_SESSION['error'] = "You have already applied for this job.";
    header("Location: job_detail.php?id=$jobId");
    exit;
}

// Get user profile info
$seeker = getUserById($seekerId);

// Check if user has previous resumes
$previousResumes = $db->fetchAll("SELECT DISTINCT resume_path, created_at
                                FROM applications 
                                WHERE seeker_id = ? AND resume_path IS NOT NULL
                                ORDER BY created_at DESC
                                LIMIT 5",
                                [$seekerId]);

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid form submission.";
        header("Location: apply.php?job_id=$jobId");
        exit;
    }
    
    $coverLetter = isset($_POST['cover_letter']) ? $_POST['cover_letter'] : '';
    $useExistingResume = isset($_POST['use_existing_resume']) && $_POST['use_existing_resume'] == '1';
    $existingResumePath = isset($_POST['existing_resume_path']) ? $_POST['existing_resume_path'] : '';
    
    // Validation
    $errors = [];
    
    if (empty($coverLetter)) {
        $errors[] = "Cover letter is required.";
    }
    
    // Resume handling
    $resumePath = null;
    
    if ($useExistingResume) {
        // Check if selected resume exists and belongs to user
        if (empty($existingResumePath)) {
            $errors[] = "Please select an existing resume.";
        } else {
            // Verify the resume belongs to this user
            $verifyResume = $db->fetchSingle("SELECT application_id FROM applications 
                                            WHERE seeker_id = ? AND resume_path = ?",
                                            [$seekerId, $existingResumePath]);
                                            
            if ($verifyResume) {
                $resumePath = $existingResumePath;
            } else {
                $errors[] = "Selected resume is not valid.";
            }
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
                $fileName = time() . '_' . $seekerId . '_' . basename($_FILES['resume']['name']);
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
        $result = $db->executeNonQuery("INSERT INTO applications (job_id, seeker_id, cover_letter, resume_path, status, created_at) 
                                      VALUES (?, ?, ?, ?, 'pending', NOW())", 
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

// Update page title
$pageTitle = "Apply for: " . $job['title'];
?>

<?php require_once 'includes/header.php'; ?>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <!-- Job Information -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">You're applying for:</h5>
                    <a href="job_detail.php?id=<?= $jobId ?>" class="btn btn-sm btn-outline-primary">View Job Details</a>
                </div>
                
                <div class="job-summary">
                    <h3><?= htmlspecialchars($job['title']) ?></h3>
                    <p class="text-muted mb-1">
                        <i class="fas fa-building me-1"></i> <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                    </p>
                    <p class="mb-1">
                        <i class="fas fa-map-marker-alt me-1"></i> <?= htmlspecialchars($job['location']) ?>
                        <span class="ms-3">
                            <i class="fas fa-tag me-1"></i> <?= htmlspecialchars(getJobCategories()[$job['category']] ?? ucfirst($job['category'])) ?>
                        </span>
                    </p>
                    <?php if ($job['application_deadline']): ?>
                        <p class="mb-0 text-<?= strtotime($job['application_deadline']) < time() ? 'danger' : 'info' ?>">
                            <i class="fas fa-calendar-alt me-1"></i> Deadline: <?= formatDate($job['application_deadline']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Application Form -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Complete Your Application</h5>
            </div>
            <div class="card-body">
                <form id="applicationForm" method="POST" action="apply.php?job_id=<?= $jobId ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <!-- Contact Information -->
                    <div class="mb-4">
                        <h6>Contact Information</h6>
                        <p class="text-muted small">This information is from your profile</p>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($seeker['name']) ?>" disabled readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($seeker['email']) ?>" disabled readonly>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" value="<?= htmlspecialchars($seeker['phone'] ?? 'Not provided') ?>" disabled readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($seeker['location'] ?? 'Not provided') ?>" disabled readonly>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Want to update your contact information? <a href="profile.php">Edit your profile here</a>
                        </div>
                    </div>
                    
                    <!-- Cover Letter -->
                    <div class="mb-4">
                        <h6>Cover Letter</h6>
                        <div class="mb-3">
                            <label for="cover_letter" class="form-label">Why are you a good fit for this role? <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="cover_letter" name="cover_letter" rows="6" required><?= isset($_POST['cover_letter']) ? htmlspecialchars($_POST['cover_letter']) : '' ?></textarea>
                            <div class="form-text">Highlight relevant skills and experiences that make you a strong candidate for this position.</div>
                        </div>
                    </div>
                    
                    <!-- Resume Upload -->
                    <div class="mb-4">
                        <h6>Resume / CV</h6>
                        
                        <?php if (count($previousResumes) > 0): ?>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="use_existing_resume" name="use_existing_resume" value="1" 
                                           <?= isset($_POST['use_existing_resume']) && $_POST['use_existing_resume'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="use_existing_resume">
                                        Use an existing resume
                                    </label>
                                </div>
                            </div>
                            
                            <div id="existing_resume_section" class="mb-3" style="display: none;">
                                <label class="form-label">Select a previously used resume:</label>
                                <div class="list-group">
                                    <?php foreach($previousResumes as $index => $resume): ?>
                                        <label class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <input class="form-check-input me-2" type="radio" name="existing_resume_path" 
                                                           value="<?= htmlspecialchars($resume['resume_path']) ?>"
                                                           <?= isset($_POST['existing_resume_path']) && $_POST['existing_resume_path'] == $resume['resume_path'] ? 'checked' : ($index === 0 ? 'checked' : '') ?>>
                                                    <i class="far fa-file-pdf me-2"></i>
                                                    Resume <?= $index + 1 ?> (<?= date('M j, Y', strtotime($resume['created_at'])) ?>)
                                                </div>
                                                <a href="<?= SITE_URL . '/' . RESUME_PATH . $resume['resume_path'] ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div id="new_resume_section" class="mb-3">
                            <label for="resume" class="form-label">Upload Resume / CV <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="resume" name="resume" accept=".pdf,.doc,.docx">
                            <div class="form-text">Accepted formats: PDF, DOC, DOCX. Maximum size: 5MB.</div>
                        </div>
                    </div>
                    
                    <!-- Additional Questions -->
                    <?php
                    // You can add job-specific questions here if needed
                    // This could be fetched from a database table of application questions
                    ?>
                    
                    <!-- Terms & Conditions -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms_agree" required>
                            <label class="form-check-label" for="terms_agree">
                                I certify that the information provided is true and complete to the best of my knowledge.
                            </label>
                        </div>
                    </div>
                    
                    <!-- Submit Application -->
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Application</button>
                        <a href="job_detail.php?id=<?= $jobId ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Application Tips -->
        <div class="card mt-4 bg-light">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-lightbulb me-2 text-warning"></i>Application Tips</h5>
                <ul class="mb-0">
                    <li>Tailor your cover letter to highlight relevant skills for this position</li>
                    <li>Ensure your resume is up-to-date and properly formatted</li>
                    <li>Be concise but thorough in describing your qualifications</li>
                    <li>Proofread everything before submitting</li>
                    <li>Follow up after a reasonable period if you don't hear back</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle resume sections based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const useExistingCheckbox = document.getElementById('use_existing_resume');
        const existingResumeSection = document.getElementById('existing_resume_section');
        const newResumeSection = document.getElementById('new_resume_section');
        
        function toggleResumeSections() {
            if (useExistingCheckbox && existingResumeSection && newResumeSection) {
                if (useExistingCheckbox.checked) {
                    existingResumeSection.style.display = 'block';
                    newResumeSection.style.display = 'none';
                } else {
                    existingResumeSection.style.display = 'none';
                    newResumeSection.style.display = 'block';
                }
            }
        }
        
        // Initial toggle
        toggleResumeSections();
        
        // Add event listener
        if (useExistingCheckbox) {
            useExistingCheckbox.addEventListener('change', toggleResumeSections);
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>