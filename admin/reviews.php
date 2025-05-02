<?php
/**
 * Admin Panel - Company Reviews Management
 * Manage, approve, and moderate employer reviews across the AmmooJobs platform
 * 
 * @version 1.2.0
 * @last_updated 2025-05-01
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Ensure only admins can access this page
requireAdmin();

// Page title
$pageTitle = 'Manage Reviews';

// Initialize variables
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$reviewId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitizeInput($_GET['filter']) : 'all';
$sortBy = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'created_at';
$sortOrder = isset($_GET['order']) ? sanitizeInput($_GET['order']) : 'DESC';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('error', 'Invalid security token. Please try again.');
        header('Location: reviews.php');
        exit;
    }
    
    // Handle different form actions
    switch ($action) {
        case 'edit':
            // Update review
            $rating = (int)$_POST['rating'];
            $title = sanitizeInput($_POST['title']);
            $content = sanitizeInput($_POST['content']);
            $status = sanitizeInput($_POST['status']);
            $moderationNotes = sanitizeInput($_POST['moderation_notes']);
            
            // Validate input
            if ($rating < 1 || $rating > 5) {
                setFlashMessage('error', 'Rating must be between 1 and 5 stars.');
                header("Location: reviews.php?action=edit&id=$reviewId");
                exit;
            }
            
            if (empty($title) || empty($content)) {
                setFlashMessage('error', 'Title and content are required.');
                header("Location: reviews.php?action=edit&id=$reviewId");
                exit;
            }
            
            // Update the review
            $result = $db->executeNonQuery(
                "UPDATE employer_reviews 
                SET rating = ?, title = ?, content = ?, status = ?, 
                moderation_notes = ?, moderated_by = ?, moderated_at = NOW(), updated_at = NOW()
                WHERE review_id = ?",
                [$rating, $title, $content, $status, $moderationNotes, $_SESSION['user_id'], $reviewId]
            );
            
            if ($result) {
                // Log activity
                logSystemActivity('update', "Updated employer review #$reviewId: $title", $_SESSION['user_id']);
                
                setFlashMessage('success', 'Review updated successfully.');
                header('Location: reviews.php?action=view&id=' . $reviewId);
            } else {
                setFlashMessage('error', 'Failed to update review.');
                header("Location: reviews.php?action=edit&id=$reviewId");
            }
            exit;

            
        case 'delete':
            // Delete review
            if ($reviewId > 0) {
                // Get review info for logging
                $reviewInfo = $db->fetchSingle(
                    "SELECT er.title, er.employer_id, u.company_name 
                     FROM employer_reviews er 
                     JOIN users u ON er.employer_id = u.user_id 
                     WHERE er.review_id = ?", 
                    [$reviewId]
                );
                
                $result = $db->executeNonQuery(
                    "DELETE FROM employer_reviews WHERE review_id = ?",
                    [$reviewId]
                );
                
                if ($result) {
                    // Log activity
                    $companyName = $reviewInfo ? $reviewInfo['company_name'] : 'Unknown';
                    logSystemActivity('delete', "Deleted review #$reviewId for $companyName: " . ($reviewInfo ? $reviewInfo['title'] : 'Unknown'), $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'Review deleted successfully.');
                    header('Location: reviews.php');
                } else {
                    setFlashMessage('error', 'Failed to delete review.');
                    header("Location: reviews.php?action=view&id=$reviewId");
                }
            } else {
                setFlashMessage('error', 'Invalid review ID.');
                header('Location: reviews.php');
            }
            exit;
                        
        case 'status':
            // Update review status
            $status = sanitizeInput($_POST['status']);
            $moderationNotes = sanitizeInput($_POST['moderation_notes'] ?? '');
            
            // Validate status
            if (!in_array($status, ['pending', 'approved', 'rejected'])) {
                setFlashMessage('error', 'Invalid review status.');
                header('Location: reviews.php');
                exit;
            }
            
            // Update the review status
            $result = $db->executeNonQuery(
                "UPDATE employer_reviews 
                 SET status = ?, moderation_notes = ?, 
                 moderated_by = ?, moderated_at = NOW(), updated_at = NOW()
                 WHERE review_id = ?",
                [$status, $moderationNotes, $_SESSION['user_id'], $reviewId]
            );
            
            if ($result) {
                // Get review info for notification
                $reviewInfo = $db->fetchSingle(
                    "SELECT er.*, u.company_name 
                     FROM employer_reviews er 
                     JOIN users u ON er.employer_id = u.user_id 
                     WHERE er.review_id = ?", 
                    [$reviewId]
                );
                
                // Notify the review author
                if ($reviewInfo) {
                    $statusText = ucfirst($status);
                    $message = "Your review of {$reviewInfo['company_name']} has been $status.";
                    
                    if ($status === 'rejected' && !empty($moderationNotes)) {
                        $message .= " Reason: $moderationNotes";
                    }
                    
                    addNotification(
                        $reviewInfo['author_id'],
                        'review',
                        $message,
                        "reviews.php?action=view&id=$reviewId"
                    );
                }
                
                // Log activity
                logSystemActivity('update', "Updated review #$reviewId status to $status", $_SESSION['user_id']);
                
                setFlashMessage('success', "Review has been $status successfully.");
                
                // Redirect to appropriate page
                $redirectUrl = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'reviews.php';
                header("Location: $redirectUrl");
            } else {
                setFlashMessage('error', 'Failed to update review status.');
                header("Location: reviews.php?action=view&id=$reviewId");
            }
            exit;
            
        case 'bulk':
            // Bulk operations
            if (isset($_POST['review_ids']) && is_array($_POST['review_ids']) && !empty($_POST['review_ids'])) {
                $reviewIds = array_map('intval', $_POST['review_ids']);
                $operation = sanitizeInput($_POST['bulk_operation']);
                
                if ($operation === 'delete') {
                    // Delete selected reviews
                    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "DELETE FROM employer_reviews WHERE review_id IN ($placeholders)",
                        $reviewIds
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('delete', "Bulk deleted " . count($reviewIds) . " employer reviews", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($reviewIds) . ' reviews deleted successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to delete reviews.');
                    }
                } elseif (in_array($operation, ['approve', 'reject'])) {
                    // Update status of selected reviews
                    $status = $operation === 'approve' ? 'approved' : 'rejected';
                    
                    $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
                    
                    $params = array_merge(
                        [$status, $_SESSION['user_id']],
                        $reviewIds
                    );
                    
                    $result = $db->executeNonQuery(
                        "UPDATE employer_reviews 
                         SET status = ?, moderated_by = ?, moderated_at = NOW(), updated_at = NOW()
                         WHERE review_id IN ($placeholders)",
                        $params
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk updated status to '$status' for " . count($reviewIds) . " reviews", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($reviewIds) . ' reviews updated successfully.');
                        
                        // Notify users
                        foreach ($reviewIds as $id) {
                            // Get review info for notification
                            $reviewInfo = $db->fetchSingle(
                                "SELECT er.*, u.company_name 
                                 FROM employer_reviews er 
                                 JOIN users u ON er.employer_id = u.user_id 
                                 WHERE er.review_id = ?", 
                                [$id]
                            );
                            
                            if ($reviewInfo) {
                                $statusText = ucfirst($status);
                                $message = "Your review of {$reviewInfo['company_name']} has been $status.";
                                
                                addNotification(
                                    $reviewInfo['author_id'],
                                    'review',
                                    $message,
                                    "reviews.php?action=view&id=$id"
                                );
                            }
                        }
                    } else {
                        setFlashMessage('error', 'Failed to update review statuses.');
                    }
                }
            } else {
                setFlashMessage('error', 'No reviews selected for bulk operation.');
            }
            
            header('Location: reviews.php?' . http_build_query(['filter' => $filter, 'sort' => $sortBy, 'order' => $sortOrder, 'page' => $page]));
            exit;
    }
}

// Build SQL query based on filters
$params = [];
$whereClause = [];

// Search filter
if (!empty($search)) {
    $whereClause[] = "(er.title LIKE ? OR er.content LIKE ? OR u.company_name LIKE ? OR au.name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Status filter
if ($filter !== 'all') {
    $whereClause[] = "er.status = ?";
    $params[] = $filter;
}

// Complete where clause
$whereSQL = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM employer_reviews er 
               LEFT JOIN users u ON er.employer_id = u.user_id 
               LEFT JOIN users au ON er.author_id = au.user_id 
               $whereSQL";
$totalReviews = $db->fetchSingle($countQuery, $params)['total'];
$totalPages = ceil($totalReviews / $limit);

// Get reviews with pagination, sorting
$reviewsQuery = "SELECT er.*, 
                 u.company_name, u.name as employer_name, 
                 au.name as author_name,
                 mu.name as moderator_name
                 FROM employer_reviews er 
                 LEFT JOIN users u ON er.employer_id = u.user_id 
                 LEFT JOIN users au ON er.author_id = au.user_id 
                 LEFT JOIN users mu ON er.moderated_by = mu.user_id 
                 $whereSQL 
                 ORDER BY $sortBy $sortOrder 
                 LIMIT $limit OFFSET $offset";
              
$reviews = $db->fetchAll($reviewsQuery, $params);

// Handle specific actions
switch ($action) {
    case 'view':
    case 'edit':
        if ($reviewId > 0) {
            // Get review details
            $review = $db->fetchSingle(
                "SELECT er.*, 
                 u.company_name, u.name as employer_name, u.profile_image as employer_image,
                 au.name as author_name, au.email as author_email, au.profile_image as author_image,
                 mu.name as moderator_name
                 FROM employer_reviews er 
                 LEFT JOIN users u ON er.employer_id = u.user_id 
                 LEFT JOIN users au ON er.author_id = au.user_id 
                 LEFT JOIN users mu ON er.moderated_by = mu.user_id 
                 WHERE er.review_id = ?", 
                [$reviewId]
            );
            
            if (!$review) {
                setFlashMessage('error', 'Review not found.');
                header('Location: reviews.php');
                exit;
            }
            
            // Get other reviews by same author
            $userReviews = $db->fetchAll(
                "SELECT er.*, u.company_name
                 FROM employer_reviews er 
                 JOIN users u ON er.employer_id = u.user_id
                 WHERE er.author_id = ? AND er.review_id != ? 
                 ORDER BY er.created_at DESC
                 LIMIT 5", 
                [$review['author_id'], $reviewId]
            );
        } else {
            setFlashMessage('error', 'Invalid review ID.');
            header('Location: reviews.php');
            exit;
        }
        break;
}

// Include header
include_once '../includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <?php if ($action === 'list'): ?>
            Manage Company Reviews
        <?php elseif ($action === 'edit'): ?>
            Edit Review
        <?php elseif ($action === 'view'): ?>
            Review Details
        <?php endif; ?>
    </h1>

    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <?php if ($action === 'list'): ?>
            <li class="breadcrumb-item active">Company Reviews</li>
        <?php else: ?>
            <li class="breadcrumb-item"><a href="reviews.php">Company Reviews</a></li>
            <li class="breadcrumb-item active">
                <?php if ($action === 'edit'): ?>Edit Review
                <?php elseif ($action === 'view'): ?>View Review
                <?php endif; ?>
            </li>
        <?php endif; ?>
    </ol>

    <?php if ($action === 'list'): ?>
        <!-- Reviews Table View -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-star me-1"></i>
                    Company Reviews
                </div>
            </div>
            <div class="card-body">
                <!-- Search and Filter Controls -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form action="reviews.php" method="get" class="d-flex">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                            <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search reviews..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group">
                            <a href="reviews.php" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                            <a href="reviews.php?filter=approved" class="btn btn-outline-success <?= $filter === 'approved' ? 'active' : '' ?>">Approved</a>
                            <a href="reviews.php?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                            <a href="reviews.php?filter=rejected" class="btn btn-outline-danger <?= $filter === 'rejected' ? 'active' : '' ?>">Rejected</a>
                        </div>
                    </div>
                </div>

                <?php if (empty($reviews)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No reviews found matching your criteria.
                    </div>
                <?php else: ?>
                    <!-- Reviews Table -->
                    <form action="reviews.php?action=bulk" method="post" id="reviewsForm">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="30">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAll">
                                            </div>
                                        </th>
                                        <th width="80">
                                            <a href="reviews.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=rating&order=<?= $sortBy === 'rating' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Rating
                                                <?php if ($sortBy === 'rating'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="reviews.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=title&order=<?= $sortBy === 'title' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Review Title
                                                <?php if ($sortBy === 'title'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="reviews.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=company_name&order=<?= $sortBy === 'company_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Company
                                                <?php if ($sortBy === 'company_name'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="reviews.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=author_name&order=<?= $sortBy === 'author_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Author
                                                <?php if ($sortBy === 'author_name'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="reviews.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=created_at&order=<?= $sortBy === 'created_at' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Date
                                                <?php if ($sortBy === 'created_at'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Status</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reviews as $review): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input review-checkbox" type="checkbox" name="review_ids[]" value="<?= $review['review_id'] ?>">
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="font-weight-bold"><?= $review['rating'] ?>/5</div>
                                            </td>
                                            <td>
                                                <a href="reviews.php?action=view&id=<?= $review['review_id'] ?>" class="fw-bold text-truncate d-inline-block" style="max-width: 200px;">
                                                    <?= htmlspecialchars($review['title']) ?>
                                                </a>
                                                <div class="text-muted small text-truncate" style="max-width: 200px;">
                                                    <?= htmlspecialchars(truncateText($review['content'], 60)) ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?></td>
                                            <td><?= htmlspecialchars($review['author_name']) ?></td>
                                            <td><?= formatDate($review['created_at']) ?></td>
                                            <td>
                                                <?php if ($review['status'] === 'approved'): ?>
                                                    <span class="badge bg-success">Approved</span>
                                                <?php elseif ($review['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($review['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="reviews.php?action=view&id=<?= $review['review_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="reviews.php?action=edit&id=<?= $review['review_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($review['status'] === 'pending'): ?>
                                                        <form action="reviews.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this review?')">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $review['review_id'] ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="btn btn-success" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form action="reviews.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to reject this review?')">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $review['review_id'] ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" class="btn btn-danger" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $review['review_id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?= $review['review_id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $review['review_id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?= $review['review_id'] ?>">Delete Review</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the review: <strong><?= htmlspecialchars($review['title']) ?></strong>?</p>
                                                                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="reviews.php?action=delete" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                                    <input type="hidden" name="id" value="<?= $review['review_id'] ?>">
                                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Bulk Actions -->
                        <div class="row mb-3 align-items-center">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <select name="bulk_operation" class="form-select" required>
                                        <option value="">-- Bulk Action --</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="reject">Reject Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary" id="bulkActionBtn" disabled onclick="return confirm('Are you sure you want to perform this action on the selected reviews?')">
                                        Apply
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted">Total: <?= number_format($totalReviews) ?> reviews</span>
                            </div>
                        </div>
                    </form>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="reviews.php?page=1&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            First
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="reviews.php?page=<?= $page - 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            &laquo;
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $startPage = max(1, min($page - 2, $totalPages - 4));
                                $endPage = min($startPage + 4, $totalPages);

                                for ($i = $startPage; $i <= $endPage; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="reviews.php?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="reviews.php?page=<?= $page + 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            &raquo;
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="reviews.php?page=<?= $totalPages ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            Last
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($action === 'view'): ?>
        <!-- Review Details View -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Review Content Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-star me-1"></i>
                            Review Details
                        </div>
                        <div>
                            <div class="btn-group">
                                <a href="reviews.php?action=edit&id=<?= $reviewId ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i> Edit Review
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteReviewModal">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="company-logo bg-light rounded me-3 p-2">
                                <?php if (!empty($review['employer_image'])): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($review['employer_image']) ?>" alt="<?= htmlspecialchars($review['company_name']) ?>" class="img-fluid" style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-building fa-2x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h4 class="mb-1">
                                    Review of <?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?>
                                </h4>
                                <div class="d-flex align-items-center">
                                    <div class="rating-stars me-2">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="text-muted"><?= $review['rating'] ?>/5</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Information -->
                        <div class="alert <?= $review['status'] === 'approved' ? 'alert-success' : ($review['status'] === 'pending' ? 'alert-warning' : 'alert-danger') ?> d-flex align-items-center mb-4">
                            <i class="fas <?= $review['status'] === 'approved' ? 'fa-check-circle' : ($review['status'] === 'pending' ? 'fa-clock' : 'fa-ban') ?> me-2"></i>
                            <div>
                                <strong>Status: <?= ucfirst($review['status']) ?></strong>
                                <?php if ($review['moderated_by'] && $review['status'] !== 'pending'): ?>
                                    <div class="small">
                                        Moderated by <?= htmlspecialchars($review['moderator_name']) ?> on <?= formatDateTime($review['moderated_at']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($review['status'] === 'pending'): ?>
                                    <div class="mt-2">
                                        <form action="reviews.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $reviewId ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <input type="hidden" name="redirect_url" value="reviews.php?action=view&id=<?= $reviewId ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Approve Review
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectReviewModal">
                                            <i class="fas fa-times me-1"></i> Reject Review
                                        </button>
                                    </div>
                                <?php elseif ($review['status'] === 'approved'): ?>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectReviewModal">
                                            <i class="fas fa-ban me-1"></i> Reject Review
                                        </button>
                                    </div>
                                <?php elseif ($review['status'] === 'rejected'): ?>
                                    <div class="mt-2">
                                        <form action="reviews.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $reviewId ?>">
                                            <input type="hidden" name="status" value="approved">
                                            <input type="hidden" name="redirect_url" value="reviews.php?action=view&id=<?= $reviewId ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Approve Review
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Review Content -->
                        <div class="mb-4">
                            <h5><?= htmlspecialchars($review['title']) ?></h5>
                            <p class="text-muted small">
                                Submitted <?= formatDate($review['created_at']) ?> by <?= htmlspecialchars($review['author_name']) ?>
                            </p>
                            <div class="card-text mb-3 review-content">
                                <?= nl2br(htmlspecialchars($review['content'])) ?>
                            </div>
                        </div>
                        
                        <!-- Moderation Notes -->
                        <?php if (!empty($review['moderation_notes'])): ?>
                            <div class="card bg-light mb-4">
                                <div class="card-header">
                                    <i class="fas fa-comment-alt me-1"></i>
                                    Moderation Notes
                                </div>
                                <div class="card-body">
                                    <?= nl2br(htmlspecialchars($review['moderation_notes'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Author Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-user me-1"></i>
                            Review Author
                        </div>
                        <a href="users.php?action=view&id=<?= $review['author_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i> View Profile
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar me-3">
                                <?php if (!empty($review['author_image'])): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($review['author_image']) ?>" alt="<?= htmlspecialchars($review['author_name']) ?>" class="rounded-circle" width="60" height="60">
                                <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="fas fa-user text-white fa-lg"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($review['author_name']) ?></h5>
                                <p class="mb-0 text-muted small"><?= htmlspecialchars($review['author_email']) ?></p>
                            </div>
                        </div>
                        
                        <a href="mailto:<?= htmlspecialchars($review['author_email']) ?>" class="btn btn-outline-secondary w-100 mb-3">
                            <i class="fas fa-envelope me-1"></i> Contact Author
                        </a>
                        
                        <?php if (!empty($userReviews)): ?>
                            <h6 class="mt-3">Other reviews by this user:</h6>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($userReviews as $userReview): ?>
                                    <li class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="reviews.php?action=view&id=<?= $userReview['review_id'] ?>">
                                                    <?= htmlspecialchars(truncateText($userReview['title'], 30)) ?>
                                                </a>
                                                <div class="text-muted small"><?= htmlspecialchars($userReview['company_name']) ?></div>
                                            </div>
                                            <div class="rating-stars small">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $userReview['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-muted small">This is the only review by this user.</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Company Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-building me-1"></i>
                            Company Information
                        </div>
                        <a href="employers.php?action=view&id=<?= $review['employer_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i> View Company
                        </a>
                    </div>
                    <div class="card-body">
                        <h5><?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?></h5>
                        
                        <div class="mt-3">
                            <a href="reviews.php?search=<?= urlencode($review['company_name']) ?>" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-star me-1"></i> View All Reviews for This Company
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Review Modal -->
        <div class="modal fade" id="deleteReviewModal" tabindex="-1" aria-labelledby="deleteReviewModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteReviewModalLabel">Delete Review</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this review?</p>
                        <p><strong><?= htmlspecialchars($review['title']) ?></strong></p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="reviews.php?action=delete" method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $reviewId ?>">
                            <button type="submit" class="btn btn-danger">Delete Review</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Review Modal -->
        <div class="modal fade" id="rejectReviewModal" tabindex="-1" aria-labelledby="rejectReviewModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rejectReviewModalLabel">Reject Review</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="reviews.php?action=status" method="post">
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $reviewId ?>">
                            <input type="hidden" name="status" value="rejected">
                            <input type="hidden" name="redirect_url" value="reviews.php?action=view&id=<?= $reviewId ?>">
                            
                            <p>Are you sure you want to reject this review?</p>
                            
                            <div class="mb-3">
                                <label for="moderation_notes" class="form-label">Reason for Rejection:</label>
                                <textarea class="form-control" id="moderation_notes" name="moderation_notes" rows="3" placeholder="Explain why this review is being rejected..."><?= !empty($review['moderation_notes']) ? htmlspecialchars($review['moderation_notes']) : '' ?></textarea>
                                <div class="form-text">This note will be visible to the review author.</div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Reject Review</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    <?php elseif ($action === 'edit'): ?>
        <!-- Edit Review Form -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-edit me-1"></i>
                Edit Review
            </div>
            <div class="card-body">
                <form action="reviews.php?action=edit&id=<?= $reviewId ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1">Company:</p>
                            <h5><?= htmlspecialchars($review['company_name'] ?: $review['employer_name']) ?></h5>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1">Author:</p>
                            <h5><?= htmlspecialchars($review['author_name']) ?></h5>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating <span class="text-danger">*</span></label>
                        <div>
                            <div class="rating-input">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" <?= $review['rating'] == $i ? 'checked' : '' ?> required>
                                        <label class="form-check-label" for="rating<?= $i ?>">
                                            <?= $i ?> <i class="fas fa-star text-warning"></i>
                                        </label>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="title" class="form-label">Review Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" value="<?= htmlspecialchars($review['title']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Review Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="6" required><?= htmlspecialchars($review['content']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Review Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="approved" <?= $review['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="pending" <?= $review['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="rejected" <?= $review['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="moderation_notes" class="form-label">Moderation Notes</label>
                        <textarea class="form-control" id="moderation_notes" name="moderation_notes" rows="3"><?= htmlspecialchars($review['moderation_notes']) ?></textarea>
                        <div class="form-text">These notes will be visible to the review author if the review is rejected.</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="reviews.php?action=view&id=<?= $reviewId ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Review
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.rating-stars {
    color: #e4e5e9;
    display: inline-block;
}

.rating-stars i {
    font-size: 1rem;
}

.rating-stars i.text-warning {
    color: #ffc107;
}

.rating-input {
    display: flex;
}

.review-content {
    white-space: pre-line;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox functionality
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.review-checkbox').forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActionButton();
        });
    }
    
    // Individual checkbox change
    document.querySelectorAll('.review-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateBulkActionButton);
    });
    
    // Enable/disable bulk action button
    function updateBulkActionButton() {
        const checkedBoxes = document.querySelectorAll('.review-checkbox:checked');
        const bulkActionBtn = document.getElementById('bulkActionBtn');
        if (bulkActionBtn) {
            bulkActionBtn.disabled = checkedBoxes.length === 0;
        }
    }
});
</script>

<?php
// Include footer
include_once '../includes/admin_footer.php';

// Debug comment with current time and user
echo "<!-- Page generated at 2025-05-01 17:53:16 by HasinduNimesh -->";
?>