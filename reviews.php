<?php
$pageTitle = 'Employer Reviews';
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/session.php';

// Get employer ID from URL parameter
$employerId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// If no ID provided, show list of employers
if (!$employerId) {
    // Get top-rated employers
    $employers = $db->fetchAll("SELECT u.user_id, u.name, u.company_name, u.profile_image, 
                               COUNT(er.review_id) as review_count, 
                               AVG(er.rating) as avg_rating 
                               FROM users u 
                               LEFT JOIN employer_reviews er ON u.user_id = er.employer_id 
                               WHERE u.user_type = 'employer' 
                               GROUP BY u.user_id 
                               ORDER BY avg_rating DESC, review_count DESC 
                               LIMIT 20");
    
    require_once 'includes/header.php';
    ?>
    
    <h1 class="mb-4">Employer Reviews</h1>
    
    <div class="row">
        <div class="col-md-8">
            <p class="lead">
                Browse reviews from job seekers about their experiences with employers on our platform. 
                Find out which companies are most highly rated before you apply.
            </p>
        </div>
        <div class="col-md-4 text-md-end">
            <?php if (isSeeker()): ?>
                <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#findEmployerModal">
                    <i class="fas fa-search me-2"></i>Find an Employer
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Rated Employers -->
    <div class="card mb-4">
        <div class="card-header bg-white">
            <h5 class="mb-0">Top Rated Employers</h5>
        </div>
        <div class="card-body p-0">
            <div class="list-group list-group-flush">
                <?php if (count($employers) > 0): ?>
                    <?php foreach ($employers as $employer): ?>
                        <a href="reviews.php?id=<?= $employer['user_id'] ?>" class="list-group-item list-group-item-action">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <?php if (!empty($employer['profile_image'])): ?>
                                        <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($employer['profile_image']) ?>" alt="<?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?>" class="employer-logo rounded-circle">
                                    <?php else: ?>
                                        <div class="employer-logo rounded-circle bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-building text-secondary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?></h5>
                                    <div class="d-flex align-items-center">
                                        <div class="rating me-2">
                                            <?php 
                                            $rating = round($employer['avg_rating'] * 2) / 2; // Round to nearest 0.5
                                            for ($i = 1; $i <= 5; $i++): 
                                            ?>
                                                <?php if ($i <= floor($rating)): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                <?php elseif ($i - 0.5 == floor($rating)): ?>
                                                    <i class="fas fa-star-half-alt text-warning"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star text-warning"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="text-muted">
                                            <?= number_format($employer['avg_rating'], 1) ?> 
                                            (<?= $employer['review_count'] ?> review<?= $employer['review_count'] != 1 ? 's' : '' ?>)
                                        </span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 ms-2">
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="list-group-item text-center py-4">
                        <p class="mb-0">No employers have been reviewed yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recently Reviewed -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="mb-0">Recent Reviews</h5>
        </div>
        <div class="card-body p-0">
            <?php
            $recentReviews = $db->fetchAll("SELECT er.*, u1.name as employer_name, u1.company_name, 
                                          u2.name as reviewer_name 
                                          FROM employer_reviews er 
                                          JOIN users u1 ON er.employer_id = u1.user_id 
                                          JOIN users u2 ON er.seeker_id = u2.user_id 
                                          WHERE er.status = 'approved' 
                                          ORDER BY er.created_at DESC 
                                          LIMIT 5");
            ?>
            
            <?php if (count($recentReviews) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($recentReviews as $review): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <a href="reviews.php?id=<?= $review['employer_id'] ?>" class="h5 mb-0 text-decoration-none">
                                    <?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?>
                                </a>
                                <div class="rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star text-warning"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="mb-1"><?= htmlspecialchars($review['title']) ?></p>
                            <p class="text-muted mb-2"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    By <?= htmlspecialchars($review['reviewer_name']) ?> - <?= timeAgo($review['created_at']) ?>
                                </small>
                                <?php if (isAdmin() || (isSeeker() && $review['seeker_id'] == $_SESSION['user_id'])): ?>
                                    <div>
                                        <a href="edit_review.php?id=<?= $review['review_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="delete_review.php?id=<?= $review['review_id'] ?>" class="btn btn-sm btn-outline-danger delete-confirm">Delete</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <p class="mb-0">No reviews have been posted yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Find Employer Modal -->
    <div class="modal fade" id="findEmployerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Find an Employer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="employerSearchForm" action="reviews.php" method="get">
                        <div class="mb-3">
                            <label for="searchEmployer" class="form-label">Search by company name:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="searchEmployer" name="search" placeholder="Enter company name">
                                <button class="btn btn-primary" type="submit">Search</button>
                            </div>
                        </div>
                    </form>
                    
                    <div id="employerSearchResults" class="mt-3">
                        <!-- Search results will be displayed here via AJAX -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <?php
} else {
    // Display reviews for a specific employer
    $employer = getUserById($employerId);
    
    // Check if employer exists
    if (!$employer || $employer['user_type'] != 'employer') {
        $_SESSION['error'] = "Employer not found.";
        header("Location: reviews.php");
        exit;
    }
    
    // Get employer rating
    $rating = getEmployerRating($employerId);
    
    // Get employer reviews
    $reviews = $db->fetchAll("SELECT er.*, u.name as reviewer_name 
                             FROM employer_reviews er 
                             JOIN users u ON er.seeker_id = u.user_id 
                             WHERE er.employer_id = ? AND er.status = 'approved'
                             ORDER BY er.created_at DESC", 
                             [$employerId]);
    
    // Check if user has already reviewed this employer
    $hasReviewed = false;
    if (isSeeker()) {
        $hasReviewed = $db->fetchSingle("SELECT review_id FROM employer_reviews 
                                       WHERE employer_id = ? AND seeker_id = ?", 
                                       [$employerId, $_SESSION['user_id']]);
    }
    
    // Handle review submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_review']) && isSeeker()) {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Invalid form submission.";
            header("Location: reviews.php?id=$employerId");
            exit;
        }
        
        $seekerId = $_SESSION['user_id'];
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $title = sanitizeInput($_POST['title']);
        $reviewText = sanitizeInput($_POST['review_text']);
        
        // Validation
        $errors = [];
        
        if ($rating < 1 || $rating > 5) {
            $errors[] = "Please select a rating between 1 and 5.";
        }
        
        if (empty($title)) {
            $errors[] = "Review title is required.";
        }
        
        if (empty($reviewText)) {
            $errors[] = "Review content is required.";
        }
        
        // Check if user has worked with employer
        $hasWorked = $db->fetchSingle("SELECT a.application_id 
                                     FROM applications a 
                                     JOIN job_listings j ON a.job_id = j.job_id 
                                     WHERE j.employer_id = ? AND a.seeker_id = ?", 
                                     [$employerId, $seekerId]);
        
        // Skip work history check if it's a demo/testing account
        $isTestAccount = isDemoAccount($seekerId);
        
        if (!$hasWorked && !$isTestAccount) {
            $errors[] = "You can only review employers you've applied to or worked with.";
        }
        
        // Check if user has already reviewed this employer
        if ($hasReviewed) {
            $errors[] = "You have already reviewed this employer. You can edit your existing review instead.";
        }
        
        // If no errors, save the review
        if (empty($errors)) {
            // Set status based on configurations
            $reviewStatus = REQUIRE_REVIEW_APPROVAL ? 'pending' : 'approved';
            
            $result = $db->executeNonQuery("INSERT INTO employer_reviews 
                                         (employer_id, seeker_id, rating, title, review_text, status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                                         [$employerId, $seekerId, $rating, $title, $reviewText, $reviewStatus]);
            
            if ($result) {
                if ($reviewStatus == 'pending') {
                    $_SESSION['success'] = "Thank you for your review! It will be visible after approval.";
                } else {
                    $_SESSION['success'] = "Thank you for your review!";
                    // Update page data to include the new review
                    $rating = getEmployerRating($employerId);
                    $reviews = $db->fetchAll("SELECT er.*, u.name as reviewer_name 
                                           FROM employer_reviews er 
                                           JOIN users u ON er.seeker_id = u.user_id 
                                           WHERE er.employer_id = ? AND er.status = 'approved'
                                           ORDER BY er.created_at DESC", 
                                           [$employerId]);
                    $hasReviewed = true;
                }
            } else {
                $_SESSION['error'] = "Failed to submit your review. Please try again.";
            }
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
    }
    
    // Update page title
    $pageTitle = 'Reviews: ' . ($employer['company_name'] ?: $employer['name']);
    
    require_once 'includes/header.php';
    ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="reviews.php">All Reviews</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?></li>
                </ol>
            </nav>
            <h1 class="mb-1"><?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?> Reviews</h1>
            
            <div class="d-flex align-items-center mb-3">
                <div class="rating-lg me-2">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= floor($rating['average'])): ?>
                            <i class="fas fa-star text-warning"></i>
                        <?php elseif ($i - 0.5 == floor($rating['average'])): ?>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        <?php else: ?>
                            <i class="far fa-star text-warning"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
                <h4 class="mb-0"><?= number_format($rating['average'], 1) ?></h4>
                <span class="text-muted ms-2">(<?= $rating['count'] ?> review<?= $rating['count'] != 1 ? 's' : '' ?>)</span>
            </div>
        </div>
        <div class="col-md-4 text-md-end">
            <a href="employer_profile.php?id=<?= $employerId ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-building me-1"></i> View Profile
            </a>
            <a href="employer_jobs.php?id=<?= $employerId ?>" class="btn btn-outline-success">
                <i class="fas fa-briefcase me-1"></i> View Jobs
            </a>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Reviews List -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Reviews</h5>
                    <?php if (isSeeker() && !$hasReviewed): ?>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                            <i class="fas fa-plus me-1"></i> Add Review
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="card-body p-0">
                    <?php if (count($reviews) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($reviews as $review): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0"><?= htmlspecialchars($review['title']) ?></h5>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="<?= $i <= $review['rating'] ? 'fas' : 'far' ?> fa-star text-warning"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="mb-3"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="fw-medium"><?= htmlspecialchars($review['reviewer_name']) ?></span>
                                            <span class="text-muted ms-2"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                                        </div>
                                        
                                        <?php if (isAdmin() || (isSeeker() && $review['seeker_id'] == $_SESSION['user_id'])): ?>
                                            <div>
                                                <a href="edit_review.php?id=<?= $review['review_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                <a href="delete_review.php?id=<?= $review['review_id'] ?>" class="btn btn-sm btn-outline-danger delete-confirm">Delete</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                            <h5>No Reviews Yet</h5>
                            <p class="text-muted">Be the first to review this employer!</p>
                            <?php if (isSeeker() && !$hasReviewed): ?>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReviewModal">
                                    Write a Review
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Employer Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">About the Employer</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <?php if (!empty($employer['profile_image'])): ?>
                            <img src="<?= SITE_URL ?>/uploads/profiles/<?= htmlspecialchars($employer['profile_image']) ?>" alt="<?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?>" class="img-fluid employer-img mb-2">
                        <?php else: ?>
                            <div class="employer-img-placeholder mb-2">
                                <i class="fas fa-building fa-3x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mb-1"><?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?></h5>
                    </div>
                    
                    <ul class="list-unstyled mb-0">
                        <?php if (!empty($employer['location'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt me-2 text-primary"></i> <?= htmlspecialchars($employer['location']) ?>
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($employer['website'])): ?>
                            <li class="mb-2">
                                <i class="fas fa-globe me-2 text-primary"></i> 
                                <a href="<?= htmlspecialchars($employer['website']) ?>" target="_blank"><?= htmlspecialchars(getDomain($employer['website'])) ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="mb-2">
                            <i class="fas fa-briefcase me-2 text-primary"></i>
                            <?php 
                            $jobsCount = $db->fetchSingle("SELECT COUNT(*) as count FROM job_listings WHERE employer_id = ? AND status = 'open'", [$employerId])['count']; 
                            ?>
                            <a href="employer_jobs.php?id=<?= $employerId ?>">
                                <?= $jobsCount ?> active job<?= $jobsCount != 1 ? 's' : '' ?>
                            </a>
                        </li>
                        
                        <li>
                            <i class="fas fa-calendar-alt me-2 text-primary"></i>
                            Member since <?= date('M Y', strtotime($employer['created_at'])) ?>
                        </li>
                    </ul>
                    
                    <?php if (!empty($employer['bio'])): ?>
                        <hr>
                        <h6>About</h6>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($employer['bio'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Rating Breakdown -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Rating Breakdown</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get rating breakdown
                    $ratingBreakdown = [];
                    for ($i = 5; $i >= 1; $i--) {
                        $count = $db->fetchSingle("SELECT COUNT(*) as count FROM employer_reviews 
                                               WHERE employer_id = ? AND rating = ? AND status = 'approved'", 
                                               [$employerId, $i])['count'];
                        $ratingBreakdown[$i] = [
                            'count' => $count,
                            'percentage' => $rating['count'] > 0 ? ($count / $rating['count']) * 100 : 0
                        ];
                    }
                    ?>
                    
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="rating-number me-2"><?= $i ?></div>
                            <i class="fas fa-star text-warning me-2"></i>
                            <div class="progress flex-grow-1" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?= $ratingBreakdown[$i]['percentage'] ?>%;" 
                                     aria-valuenow="<?= $ratingBreakdown[$i]['percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="rating-count ms-2">
                                <?= $ratingBreakdown[$i]['count'] ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Report Section -->
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-flag text-danger me-2"></i>Report a Concern</h6>
                    <p class="small mb-2">See something that violates our community guidelines?</p>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reportModal">
                        Report Employer or Reviews
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (isSeeker() && !$hasReviewed): ?>
    <!-- Add Review Modal -->
    <div class="modal fade" id="addReviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Review <?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="reviews.php?id=<?= $employerId ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="submit_review" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Your Rating <span class="text-danger">*</span></label>
                            <div class="rating-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input d-none" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" required>
                                        <label class="form-check-label rating-star" for="rating<?= $i ?>">
                                            <i class="far fa-star"></i>
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reviewTitle" class="form-label">Review Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="reviewTitle" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reviewText" class="form-label">Your Review <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reviewText" name="review_text" rows="5" required
                                      placeholder="Share your experience working with this employer"></textarea>
                        </div>
                        
                        <div class="form-text mb-3">
                            <p><i class="fas fa-info-circle me-1"></i> Review Guidelines:</p>
                            <ul class="small mb-0">
                                <li>Be honest and constructive in your feedback</li>
                                <li>Focus on your personal experience</li>
                                <li>Avoid including confidential information</li>
                                <li>Do not include personal attacks or offensive language</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Review</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Report a Concern</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="report_concern.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="employer_id" value="<?= $employerId ?>">
                        
                        <div class="mb-3">
                            <label for="reportType" class="form-label">What are you reporting? <span class="text-danger">*</span></label>
                            <select class="form-select" id="reportType" name="report_type" required>
                                <option value="">-- Select --</option>
                                <option value="employer">Employer Information</option>
                                <option value="reviews">Specific Review</option>
                                <option value="other">Other Concern</option>
                            </select>
                        </div>
                        
                        <div id="reviewSelection" class="mb-3" style="display: none;">
                            <label for="reviewId" class="form-label">Which review? <span class="text-danger">*</span></label>
                            <select class="form-select" id="reviewId" name="review_id">
                                <option value="">-- Select a review --</option>
                                <?php foreach ($reviews as $review): ?>
                                    <option value="<?= $review['review_id'] ?>">
                                        "<?= htmlspecialchars(mb_substr($review['title'], 0, 30)) ?><?= strlen($review['title']) > 30 ? '...' : '' ?>" 
                                        by <?= htmlspecialchars($review['reviewer_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reportReason" class="form-label">Reason <span class="text-danger">*</span></label>
                            <select class="form-select" id="reportReason" name="reason" required>
                                <option value="">-- Select --</option>
                                <option value="misleading">Misleading Information</option>
                                <option value="inappropriate">Inappropriate Content</option>
                                <option value="fake">Fake Account/Review</option>
                                <option value="harassment">Harassment or Threatening</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reportDetails" class="form-label">Additional Details <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reportDetails" name="details" rows="3" required
                                      placeholder="Please provide specific details about your concern"></textarea>
                        </div>
                        
                        <?php if (!isLoggedIn()): ?>
                        <div class="mb-3">
                            <label for="reporterEmail" class="form-label">Your Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="reporterEmail" name="reporter_email" required
                                   placeholder="We'll contact you if needed">
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit Report</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Star rating selection
            const ratingStars = document.querySelectorAll('.rating-star');
            if (ratingStars.length > 0) {
                ratingStars.forEach(function(star, index) {
                    star.addEventListener('mouseover', function() {
                        // Highlight stars on hover
                        for (let i = 0; i <= index; i++) {
                            ratingStars[i].querySelector('i').className = 'fas fa-star text-warning';
                        }
                        for (let i = index + 1; i < ratingStars.length; i++) {
                            ratingStars[i].querySelector('i').className = 'far fa-star';
                        }
                    });
                    
                    star.addEventListener('click', function() {
                        // Select the rating
                        document.getElementById('rating' + (index + 1)).checked = true;
                    });
                });
                
                // Reset stars on mouse leave
                document.querySelector('.rating-input').addEventListener('mouseleave', function() {
                    ratingStars.forEach(function(star, index) {
                        if (document.getElementById('rating' + (index + 1)).checked) {
                            star.querySelector('i').className = 'fas fa-star text-warning';
                        } else {
                            star.querySelector('i').className = 'far fa-star';
                        }
                    });
                });
            }
            
            // Show review selection dropdown when reporting a review
            const reportType = document.getElementById('reportType');
            const reviewSelection = document.getElementById('reviewSelection');
            const reviewId = document.getElementById('reviewId');
            
            if (reportType && reviewSelection) {
                reportType.addEventListener('change', function() {
                    if (this.value === 'reviews') {
                        reviewSelection.style.display = 'block';
                        reviewId.required = true;
                    } else {
                        reviewSelection.style.display = 'none';
                        reviewId.required = false;
                    }
                });
            }
            
            // Delete confirmation
            document.querySelectorAll('.delete-confirm').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
    
    <?php
}

require_once 'includes/footer.php';
?>