<?php
/**
 * Admin Panel - Job Listings Management
 * Manage, edit, review and control job listings across the AmmooJobs platform
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
$pageTitle = 'Manage Jobs';

// Initialize variables
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : 'list';
$jobId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
        header('Location: jobs.php');
        exit;
    }
    
    // Handle different form actions
    switch ($action) {
        case 'add':
        case 'edit':
            $title = sanitizeInput($_POST['title']);
            $companyId = (int)$_POST['company_id'];
            $location = sanitizeInput($_POST['location']);
            $jobType = sanitizeInput($_POST['job_type']);
            $category = sanitizeInput($_POST['category']);
            $description = sanitizeInput($_POST['description']);
            $requirements = sanitizeInput($_POST['requirements']);
            $salaryMin = !empty($_POST['salary_min']) ? (float)$_POST['salary_min'] : null;
            $salaryMax = !empty($_POST['salary_max']) ? (float)$_POST['salary_max'] : null;
            $remote = isset($_POST['remote']) ? 1 : 0;
            $status = sanitizeInput($_POST['status']);
            
            // Validate input
            if (empty($title)) {
                setFlashMessage('error', 'Job title is required.');
                header('Location: jobs.php?action=' . $action . ($jobId > 0 ? '&id=' . $jobId : ''));
                exit;
            }
            
            if ($action === 'add') {
                // Add new job listing
                $result = $db->executeNonQuery(
                    "INSERT INTO job_listings 
                     (employer_id, title, location, job_type, category, description, requirements, 
                      salary_min, salary_max, is_remote, status, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    [$companyId, $title, $location, $jobType, $category, $description, $requirements, 
                     $salaryMin, $salaryMax, $remote, $status]
                );
                
                if ($result) {
                    $newJobId = $db->getLastId();
                    
                    // Log activity
                    logSystemActivity('create', "Created new job listing: " . $title, $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'Job listing created successfully.');
                    header('Location: jobs.php?action=edit&id=' . $newJobId);
                    exit;
                } else {
                    setFlashMessage('error', 'Failed to create job listing.');
                }
            } else {
                // Update existing job listing
                $result = $db->executeNonQuery(
                    "UPDATE job_listings SET 
                     title = ?, location = ?, job_type = ?, category = ?, description = ?, 
                     requirements = ?, salary_min = ?, salary_max = ?, is_remote = ?, 
                     status = ?, updated_at = NOW() 
                     WHERE job_id = ?",
                    [$title, $location, $jobType, $category, $description, $requirements, 
                     $salaryMin, $salaryMax, $remote, $status, $jobId]
                );
                
                if ($result) {
                    // Log activity
                    logSystemActivity('update', "Updated job listing ID #$jobId: $title", $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'Job listing updated successfully.');
                    header('Location: jobs.php?action=edit&id=' . $jobId);
                    exit;
                } else {
                    setFlashMessage('error', 'Failed to update job listing.');
                }
            }
            break;
            
        case 'delete':
            // Delete job listing
            if ($jobId > 0) {
                // Get job title for logging
                $jobInfo = $db->fetchSingle("SELECT title FROM job_listings WHERE job_id = ?", [$jobId]);
                
                $result = $db->executeNonQuery(
                    "DELETE FROM job_listings WHERE job_id = ?",
                    [$jobId]
                );
                
                if ($result) {
                    // Delete related applications
                    $db->executeNonQuery("DELETE FROM applications WHERE job_id = ?", [$jobId]);
                    
                    // Log activity
                    logSystemActivity('delete', "Deleted job listing ID #$jobId: " . ($jobInfo ? $jobInfo['title'] : 'Unknown'), $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'Job listing deleted successfully.');
                } else {
                    setFlashMessage('error', 'Failed to delete job listing.');
                }
            } else {
                setFlashMessage('error', 'Invalid job ID.');
            }
            header('Location: jobs.php');
            exit;
            
        case 'bulk':
            // Bulk operations
            if (isset($_POST['job_ids']) && is_array($_POST['job_ids']) && !empty($_POST['job_ids'])) {
                $jobIds = array_map('intval', $_POST['job_ids']);
                $operation = sanitizeInput($_POST['bulk_operation']);
                
                if ($operation === 'delete') {
                    // Delete selected jobs
                    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
                    
                    $result = $db->executeNonQuery(
                        "DELETE FROM job_listings WHERE job_id IN ($placeholders)",
                        $jobIds
                    );
                    
                    if ($result) {
                        // Delete related applications
                        $db->executeNonQuery(
                            "DELETE FROM applications WHERE job_id IN ($placeholders)",
                            $jobIds
                        );
                        
                        // Log activity
                        logSystemActivity('delete', "Bulk deleted " . count($jobIds) . " job listings", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($jobIds) . ' job listings deleted successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to delete job listings.');
                    }
                } elseif (in_array($operation, ['activate', 'deactivate', 'approve', 'reject'])) {
                    // Update status of selected jobs
                    $status = '';
                    switch ($operation) {
                        case 'activate': $status = 'active'; break;
                        case 'deactivate': $status = 'inactive'; break;
                        case 'approve': $status = 'active'; break;
                        case 'reject': $status = 'rejected'; break;
                    }
                    
                    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
                    
                    $params = array_merge([$status], $jobIds);
                    $result = $db->executeNonQuery(
                        "UPDATE job_listings SET status = ?, updated_at = NOW() WHERE job_id IN ($placeholders)",
                        $params
                    );
                    
                    if ($result) {
                        // Log activity
                        logSystemActivity('update', "Bulk updated status to '$status' for " . count($jobIds) . " job listings", $_SESSION['user_id']);
                        
                        setFlashMessage('success', count($jobIds) . ' job listings updated successfully.');
                    } else {
                        setFlashMessage('error', 'Failed to update job listings.');
                    }
                }
            } else {
                setFlashMessage('error', 'No jobs selected for bulk operation.');
            }
            header('Location: jobs.php?' . http_build_query(['filter' => $filter, 'sort' => $sortBy, 'order' => $sortOrder, 'page' => $page]));
            exit;
            
        case 'status':
            // Update job status (quick action)
            $newStatus = sanitizeInput($_POST['status']);
            
            if ($jobId > 0 && !empty($newStatus)) {
                $result = $db->executeNonQuery(
                    "UPDATE job_listings SET status = ?, updated_at = NOW() WHERE job_id = ?",
                    [$newStatus, $jobId]
                );
                
                if ($result) {
                    // Log activity
                    $jobInfo = $db->fetchSingle("SELECT title FROM job_listings WHERE job_id = ?", [$jobId]);
                    logSystemActivity('update', "Updated status to '$newStatus' for job listing ID #$jobId: " . ($jobInfo ? $jobInfo['title'] : 'Unknown'), $_SESSION['user_id']);
                    
                    setFlashMessage('success', 'Job status updated successfully.');
                } else {
                    setFlashMessage('error', 'Failed to update job status.');
                }
            } else {
                setFlashMessage('error', 'Invalid job ID or status.');
            }
            
            // Redirect back to the appropriate page
            $redirectUrl = isset($_POST['redirect_url']) ? $_POST['redirect_url'] : 'jobs.php';
            header('Location: ' . $redirectUrl);
            exit;
    }
}

// Build SQL query based on filters
$params = [];
$whereClause = [];

// Search filter
if (!empty($search)) {
    $whereClause[] = "(j.title LIKE ? OR j.description LIKE ? OR j.location LIKE ? OR u.company_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Status filter
if ($filter !== 'all') {
    $whereClause[] = "j.status = ?";
    $params[] = $filter;
}

// Complete where clause
$whereSQL = empty($whereClause) ? "" : "WHERE " . implode(" AND ", $whereClause);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM job_listings j LEFT JOIN users u ON j.employer_id = u.user_id $whereSQL";
$totalJobs = $db->fetchSingle($countQuery, $params)['total'];
$totalPages = ceil($totalJobs / $limit);

// Get jobs with pagination, sorting
$jobsQuery = "SELECT j.*, u.name as employer_name, u.company_name, 
              (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.job_id) as application_count
              FROM job_listings j 
              LEFT JOIN users u ON j.employer_id = u.user_id 
              $whereSQL 
              ORDER BY $sortBy $sortOrder 
              LIMIT $limit OFFSET $offset";
              
$jobs = $db->fetchAll($jobsQuery, $params);

// Handle specific actions
switch ($action) {
    case 'edit':
    case 'view':
        if ($jobId > 0) {
            // Get job details
            $job = $db->fetchSingle(
                "SELECT j.*, u.name as employer_name, u.company_name, u.email as employer_email 
                FROM job_listings j 
                LEFT JOIN users u ON j.employer_id = u.user_id 
                WHERE j.job_id = ?", 
                [$jobId]
            );
            
            if (!$job) {
                setFlashMessage('error', 'Job listing not found.');
                header('Location: jobs.php');
                exit;
            }
            
            // Get application statistics
            $applicationStats = $db->fetchSingle(
                "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
                SUM(CASE WHEN status = 'shortlisted' THEN 1 ELSE 0 END) as shortlisted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'offered' THEN 1 ELSE 0 END) as offered,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted
                FROM applications 
                WHERE job_id = ?", 
                [$jobId]
            );
            
            // Get recent applications
            $recentApplications = $db->fetchAll(
                "SELECT a.*, u.name as applicant_name, u.email as applicant_email 
                FROM applications a 
                JOIN users u ON a.seeker_id = u.user_id 
                WHERE a.job_id = ? 
                ORDER BY a.applied_at DESC 
                LIMIT 5", 
                [$jobId]
            );
            
            // Get available employers for job reassignment
            $employers = $db->fetchAll(
                "SELECT user_id, name, company_name FROM users WHERE user_type = 'employer' ORDER BY company_name"
            );
        } else {
            setFlashMessage('error', 'Invalid job ID.');
            header('Location: jobs.php');
            exit;
        }
        break;
        
    case 'add':
        // Get available employers for new job
        $employers = $db->fetchAll(
            "SELECT user_id, name, company_name FROM users WHERE user_type = 'employer' ORDER BY company_name"
        );
        break;
}

// Include header
include_once '../includes/admin_header.php';
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <?php if ($action === 'list'): ?>
            Manage Job Listings
        <?php elseif ($action === 'add'): ?>
            Add New Job Listing
        <?php elseif ($action === 'edit'): ?>
            Edit Job Listing
        <?php elseif ($action === 'view'): ?>
            Job Details
        <?php endif; ?>
    </h1>

    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
        <?php if ($action === 'list'): ?>
            <li class="breadcrumb-item active">Job Listings</li>
        <?php else: ?>
            <li class="breadcrumb-item"><a href="jobs.php">Job Listings</a></li>
            <li class="breadcrumb-item active">
                <?php if ($action === 'add'): ?>Add New
                <?php elseif ($action === 'edit'): ?>Edit
                <?php elseif ($action === 'view'): ?>View
                <?php endif; ?>
            </li>
        <?php endif; ?>
    </ol>

    <?php if ($action === 'list'): ?>
        <!-- Job Listings Table View -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-briefcase me-1"></i>
                    Job Listings
                </div>
                <div>
                    <a href="jobs.php?action=add" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus"></i> Add New Job
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Search and Filter Controls -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <form action="jobs.php" method="get" class="d-flex">
                            <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                            <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search jobs..." value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="btn-group">
                            <a href="jobs.php" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                            <a href="jobs.php?filter=active" class="btn btn-outline-success <?= $filter === 'active' ? 'active' : '' ?>">Active</a>
                            <a href="jobs.php?filter=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                            <a href="jobs.php?filter=inactive" class="btn btn-outline-secondary <?= $filter === 'inactive' ? 'active' : '' ?>">Inactive</a>
                            <a href="jobs.php?filter=expired" class="btn btn-outline-danger <?= $filter === 'expired' ? 'active' : '' ?>">Expired</a>
                        </div>
                    </div>
                </div>

                <?php if (empty($jobs)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No job listings found matching your criteria.
                    </div>
                <?php else: ?>
                    <!-- Jobs Table -->
                    <form action="jobs.php?action=bulk" method="post" id="jobsForm">
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
                                        <th>
                                            <a href="jobs.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=title&order=<?= $sortBy === 'title' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Title
                                                <?php if ($sortBy === 'title'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="jobs.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=company_name&order=<?= $sortBy === 'company_name' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Company
                                                <?php if ($sortBy === 'company_name'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="jobs.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=location&order=<?= $sortBy === 'location' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Location
                                                <?php if ($sortBy === 'location'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="jobs.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=job_type&order=<?= $sortBy === 'job_type' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Type
                                                <?php if ($sortBy === 'job_type'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Applications</th>
                                        <th>
                                            <a href="jobs.php?filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=created_at&order=<?= $sortBy === 'created_at' && $sortOrder === 'ASC' ? 'DESC' : 'ASC' ?>">
                                                Posted
                                                <?php if ($sortBy === 'created_at'): ?>
                                                    <i class="fas fa-sort-<?= $sortOrder === 'ASC' ? 'up' : 'down' ?> ms-1"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th>Status</th>
                                        <th width="160">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $job): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input job-checkbox" type="checkbox" name="job_ids[]" value="<?= $job['job_id'] ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <a href="jobs.php?action=view&id=<?= $job['job_id'] ?>" class="fw-bold text-truncate d-inline-block" style="max-width: 200px;">
                                                    <?= htmlspecialchars($job['title']) ?>
                                                </a>
                                            </td>
                                            <td><?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></td>
                                            <td>
                                                <span class="d-flex align-items-center">
                                                    <i class="fas fa-map-marker-alt me-1 text-muted"></i>
                                                    <?= htmlspecialchars($job['location']) ?>
                                                    <?php if ($job['is_remote']): ?>
                                                        <span class="badge bg-info ms-1">Remote</span>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                $jobTypes = getJobTypes();
                                                echo htmlspecialchars($jobTypes[$job['job_type']] ?? ucfirst($job['job_type']));
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="applications.php?job_id=<?= $job['job_id'] ?>" class="badge bg-primary fw-normal">
                                                    <?= number_format($job['application_count']) ?>
                                                </a>
                                            </td>
                                            <td><?= formatDate($job['created_at']) ?></td>
                                            <td>
                                                <?php if ($job['status'] === 'active'): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php elseif ($job['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning text-dark">Pending</span>
                                                <?php elseif ($job['status'] === 'inactive'): ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php elseif ($job['status'] === 'expired'): ?>
                                                    <span class="badge bg-danger">Expired</span>
                                                <?php elseif ($job['status'] === 'rejected'): ?>
                                                    <span class="badge bg-danger">Rejected</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark"><?= ucfirst($job['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="jobs.php?action=view&id=<?= $job['job_id'] ?>" class="btn btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="jobs.php?action=edit&id=<?= $job['job_id'] ?>" class="btn btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($job['status'] === 'pending'): ?>
                                                        <form action="jobs.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to approve this job?')">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $job['job_id'] ?>">
                                                            <input type="hidden" name="status" value="active">
                                                            <button type="submit" class="btn btn-success" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form action="jobs.php?action=status" method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to reject this job?')">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="id" value="<?= $job['job_id'] ?>">
                                                            <input type="hidden" name="status" value="rejected">
                                                            <button type="submit" class="btn btn-danger" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-danger" title="Delete" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $job['job_id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?= $job['job_id'] ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?= $job['job_id'] ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?= $job['job_id'] ?>">Delete Job</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Are you sure you want to delete the job: <strong><?= htmlspecialchars($job['title']) ?></strong>?</p>
                                                                <p class="text-danger"><strong>Warning:</strong> This will also delete all applications for this job and cannot be undone.</p>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form action="jobs.php?action=delete" method="post">
                                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                                    <input type="hidden" name="id" value="<?= $job['job_id'] ?>">
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
                                        <option value="activate">Activate Selected</option>
                                        <option value="deactivate">Deactivate Selected</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="reject">Reject Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary" id="bulkActionBtn" disabled onclick="return confirm('Are you sure you want to perform this action on the selected jobs?')">
                                        Apply
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 text-end">
                                <span class="text-muted">Total: <?= number_format($totalJobs) ?> jobs</span>
                            </div>
                        </div>
                    </form>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="jobs.php?page=1&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            First
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="jobs.php?page=<?= $page - 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
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
                                        <a class="page-link" href="jobs.php?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="jobs.php?page=<?= $page + 1 ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
                                            &raquo;
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="jobs.php?page=<?= $totalPages ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>">
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
        <!-- Job Details View -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Job Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-briefcase me-1"></i>
                            Job Details
                        </div>
                        <div>
                            <div class="btn-group">
                                <a href="jobs.php?action=edit&id=<?= $jobId ?>" class="btn btn-primary btn-sm">
                                    <i class="fas fa-edit me-1"></i> Edit Job
                                </a>
                                <a href="../job.php?id=<?= $jobId ?>" class="btn btn-info btn-sm" target="_blank">
                                    <i class="fas fa-external-link-alt me-1"></i> View Public
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteJobModal">
                                    <i class="fas fa-trash me-1"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="job-logo bg-light rounded me-3 p-2">
                                <?php if (!empty($job['company_logo'])): ?>
                                    <img src="../uploads/logos/<?= htmlspecialchars($job['company_logo']) ?>" alt="<?= htmlspecialchars($job['company_name']) ?>" class="img-fluid" style="width: 60px; height: 60px; object-fit: contain;">
                                <?php else: ?>
                                    <div style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-building fa-2x text-secondary"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h2 class="h4 mb-1"><?= htmlspecialchars($job['title']) ?></h2>
                                <p class="mb-0 text-muted">
                                    <?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?>
                                </p>
                            </div>
                        </div>

                        <!-- Status Information -->
                        <div class="alert <?= $job['status'] === 'active' ? 'alert-success' : ($job['status'] === 'pending' ? 'alert-warning' : 'alert-secondary') ?> d-flex align-items-center mb-4">
                            <i class="fas <?= $job['status'] === 'active' ? 'fa-check-circle' : ($job['status'] === 'pending' ? 'fa-clock' : 'fa-ban') ?> me-2"></i>
                            <div>
                                <strong>Status: <?= ucfirst($job['status']) ?></strong>
                                <?php if ($job['status'] === 'pending'): ?>
                                    <div class="mt-2">
                                        <form action="jobs.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <input type="hidden" name="status" value="active">
                                            <input type="hidden" name="redirect_url" value="jobs.php?action=view&id=<?= $jobId ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Approve Job
                                            </button>
                                        </form>
                                        <form action="jobs.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <input type="hidden" name="status" value="rejected">
                                            <input type="hidden" name="redirect_url" value="jobs.php?action=view&id=<?= $jobId ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times me-1"></i> Reject Job
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($job['status'] === 'active'): ?>
                                    <div class="mt-2">
                                        <form action="jobs.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <input type="hidden" name="redirect_url" value="jobs.php?action=view&id=<?= $jobId ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-ban me-1"></i> Deactivate Job
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($job['status'] === 'inactive' || $job['status'] === 'rejected'): ?>
                                    <div class="mt-2">
                                        <form action="jobs.php?action=status" method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <input type="hidden" name="id" value="<?= $jobId ?>">
                                            <input type="hidden" name="status" value="active">
                                            <input type="hidden" name="redirect_url" value="jobs.php?action=view&id=<?= $jobId ?>">
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="fas fa-check me-1"></i> Activate Job
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Job Details -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="140"><i class="fas fa-map-marker-alt me-2 text-muted"></i> Location:</th>
                                        <td>
                                            <?= htmlspecialchars($job['location']) ?>
                                            <?php if ($job['is_remote']): ?>
                                                <span class="badge bg-info ms-1">Remote</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-clock me-2 text-muted"></i> Job Type:</th>
                                        <td>
                                            <?php 
                                            $jobTypes = getJobTypes();
                                            echo htmlspecialchars($jobTypes[$job['job_type']] ?? ucfirst($job['job_type']));
                                            ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-tag me-2 text-muted"></i> Category:</th>
                                        <td>
                                            <?php 
                                            $categories = getJobCategories();
                                            echo htmlspecialchars($categories[$job['category']] ?? ucfirst($job['category']));
                                            ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="140"><i class="fas fa-calendar-alt me-2 text-muted"></i> Posted:</th>
                                        <td><?= formatDateTime($job['created_at']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-dollar-sign me-2 text-muted"></i> Salary:</th>
                                        <td><?= formatSalary($job['salary_min'], $job['salary_max']) ?></td>
                                    </tr>
                                    <tr>
                                        <th><i class="fas fa-file-alt me-2 text-muted"></i> Applications:</th>
                                        <td>
                                            <a href="applications.php?job_id=<?= $jobId ?>" class="text-primary">
                                                <?= number_format($applicationStats['total']) ?> total applications
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Description and Requirements -->
                        <div class="mb-4">
                            <h5>Job Description</h5>
                            <div class="card-text mb-3 job-description">
                                <?= nl2br(htmlspecialchars($job['description'])) ?>
                            </div>
                            
                            <h5>Requirements</h5>
                            <div class="card-text job-requirements">
                                <?= nl2br(htmlspecialchars($job['requirements'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Applications Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-users me-1"></i>
                            Recent Applications
                        </div>
                        <a href="applications.php?job_id=<?= $jobId ?>" class="btn btn-primary btn-sm">
                            View All Applications
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentApplications)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Applicant</th>
                                            <th>Applied On</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentApplications as $application): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="text-truncate">
                                                            <a href="users.php?action=view&id=<?= $application['seeker_id'] ?>">
                                                                <?= htmlspecialchars($application['applicant_name']) ?>
                                                            </a>
                                                            <div class="small text-muted text-truncate">
                                                                <?= htmlspecialchars($application['applicant_email']) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= formatDate($application['applied_at']) ?></td>
                                                <td><?= getStatusLabel($application['status']) ?></td>
                                                <td>
                                                    <a href="applications.php?action=view&id=<?= $application['application_id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye me-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center mb-0">No applications yet for this job.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Employer Information Card -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-building me-1"></i>
                            Employer Information
                        </div>
                        <a href="users.php?action=view&id=<?= $job['employer_id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-external-link-alt me-1"></i> View Profile
                        </a>
                    </div>
                    <div class="card-body">
                        <h5><?= htmlspecialchars($job['company_name'] ?: $job['employer_name']) ?></h5>
                        <p class="text-muted mb-3"><?= htmlspecialchars($job['employer_email']) ?></p>
                        
                        <form action="jobs.php?action=edit&id=<?= $jobId ?>" method="post" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <div class="form-group">
                                <label for="reassignEmployer" class="form-label">Reassign Job to Different Employer</label>
                                <div class="input-group">
                                    <select class="form-select" id="reassignEmployer" name="company_id">
                                        <?php foreach ($employers as $employer): ?>
                                            <option value="<?= $employer['user_id'] ?>" <?= $employer['user_id'] == $job['employer_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary">Reassign</button>
                                </div>
                                <div class="form-text">This will transfer the job to another employer account.</div>
                            </div>
                        </form>
                        
                        <a href="mailto:<?= htmlspecialchars($job['employer_email']) ?>" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-envelope me-1"></i> Contact Employer
                        </a>
                    </div>
                </div>
                
                <!-- Application Statistics Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-bar me-1"></i>
                        Application Statistics
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Total Applications
                                <span class="badge bg-primary rounded-pill"><?= number_format($applicationStats['total']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Pending Review
                                <span class="badge bg-warning text-dark rounded-pill"><?= number_format($applicationStats['pending']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Reviewed
                                <span class="badge bg-info rounded-pill"><?= number_format($applicationStats['reviewed']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Shortlisted
                                <span class="badge bg-primary rounded-pill"><?= number_format($applicationStats['shortlisted']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Rejected
                                <span class="badge bg-danger rounded-pill"><?= number_format($applicationStats['rejected']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Offered
                                <span class="badge bg-success rounded-pill"><?= number_format($applicationStats['offered']) ?></span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                Accepted
                                <span class="badge bg-success rounded-pill"><?= number_format($applicationStats['accepted']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Job History Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-history me-1"></i>
                        Job History
                    </div>
                    <div class="card-body">
                        <ul class="timeline-small">
                            <li class="timeline-small-item">
                                <span class="timeline-small-date"><?= formatDate($job['created_at']) ?></span>
                                <h6 class="timeline-small-title">Job Created</h6>
                                <p class="text-muted small">Job listing was created by employer</p>
                            </li>
                            <?php if ($job['updated_at'] && $job['updated_at'] != $job['created_at']): ?>
                            <li class="timeline-small-item">
                                <span class="timeline-small-date"><?= formatDate($job['updated_at']) ?></span>
                                <h6 class="timeline-small-title">Job Updated</h6>
                                <p class="text-muted small">Job listing was last modified</p>
                            </li>
                            <?php endif; ?>
                            <?php if ($job['expires_at']): ?>
                            <li class="timeline-small-item">
                                <span class="timeline-small-date"><?= formatDate($job['expires_at']) ?></span>
                                <h6 class="timeline-small-title">Expiration Date</h6>
                                <p class="text-muted small">
                                    <?php if (strtotime($job['expires_at']) < time()): ?>
                                        Job listing has expired
                                    <?php else: ?>
                                        Job listing will expire
                                    <?php endif; ?>
                                </p>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Job Modal -->
        <div class="modal fade" id="deleteJobModal" tabindex="-1" aria-labelledby="deleteJobModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteJobModalLabel">Delete Job</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this job listing?</p>
                        <p><strong><?= htmlspecialchars($job['title']) ?></strong></p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. All applications associated with this job will also be deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form action="jobs.php?action=delete" method="post">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="id" value="<?= $jobId ?>">
                            <button type="submit" class="btn btn-danger">Delete Job</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Add/Edit Job Form -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-<?= $action === 'add' ? 'plus' : 'edit' ?> me-1"></i>
                <?= $action === 'add' ? 'Add New Job' : 'Edit Job' ?>
            </div>
            <div class="card-body">
                <form action="jobs.php?action=<?= $action ?><?= $action === 'edit' ? '&id=' . $jobId : '' ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="title" class="form-label">Job Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       value="<?= $action === 'edit' ? htmlspecialchars($job['title']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Employer <span class="text-danger">*</span></label>
                                <select class="form-select" id="company_id" name="company_id" required>
                                    <option value="">Select employer</option>
                                    <?php foreach ($employers as $employer): ?>
                                        <option value="<?= $employer['user_id'] ?>" <?= ($action === 'edit' && $employer['user_id'] == $job['employer_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($employer['company_name'] ?: $employer['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="location" name="location" required
                                       value="<?= $action === 'edit' ? htmlspecialchars($job['location']) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="job_type" class="form-label">Job Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="job_type" name="job_type" required>
                                    <?php foreach (getJobTypes() as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($action === 'edit' && $job['job_type'] === $value) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category" name="category" required>
                                    <?php foreach (getJobCategories() as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($action === 'edit' && $job['category'] === $value) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="salary_min" class="form-label">Minimum Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                    <input type="number" class="form-control" id="salary_min" name="salary_min" min="0" step="0.01"
                                           value="<?= $action === 'edit' && $job['salary_min'] ? htmlspecialchars($job['salary_min']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="salary_max" class="form-label">Maximum Salary</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= CURRENCY_SYMBOL ?></span>
                                    <input type="number" class="form-control" id="salary_max" name="salary_max" min="0" step="0.01"
                                           value="<?= $action === 'edit' && $job['salary_max'] ? htmlspecialchars($job['salary_max']) : '' ?>">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active" <?= ($action === 'edit' && $job['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                                    <option value="pending" <?= ($action === 'edit' && $job['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                                    <option value="inactive" <?= ($action === 'edit' && $job['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                                    <option value="expired" <?= ($action === 'edit' && $job['status'] === 'expired') ? 'selected' : '' ?>>Expired</option>
                                    <option value="rejected" <?= ($action === 'edit' && $job['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remote" name="remote" value="1" 
                               <?= ($action === 'edit' && $job['is_remote']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="remote">Remote Work Available</label>
                        </div>

<div class="mb-3">
    <label for="description" class="form-label">Job Description <span class="text-danger">*</span></label>
    <textarea class="form-control" id="description" name="description" rows="6" required><?= $action === 'edit' ? htmlspecialchars($job['description']) : '' ?></textarea>
</div>

<div class="mb-3">
    <label for="requirements" class="form-label">Job Requirements <span class="text-danger">*</span></label>
    <textarea class="form-control" id="requirements" name="requirements" rows="6" required><?= $action === 'edit' ? htmlspecialchars($job['requirements']) : '' ?></textarea>
    <div class="form-text">List skills, qualifications and experience required for this position.</div>
</div>

<div class="d-flex justify-content-between">
    <a href="jobs.php" class="btn btn-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i> <?= $action === 'add' ? 'Create Job' : 'Update Job' ?>
    </button>
</div>
</form>
</div>
</div>
<?php endif; ?>
</div>

<!-- Helper function to display status badges -->
<?php
function getStatusLabel($status) {
switch ($status) {
case 'pending':
return '<span class="badge bg-warning text-dark">Pending</span>';
case 'reviewed':
return '<span class="badge bg-info">Reviewed</span>';
case 'shortlisted':
return '<span class="badge bg-primary">Shortlisted</span>';
case 'rejected':
return '<span class="badge bg-danger">Rejected</span>';
case 'offered':
return '<span class="badge bg-success">Offered</span>';
case 'accepted':
return '<span class="badge bg-success">Accepted</span>';
default:
return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}
}
?>

<style>
.timeline-small {
margin: 0;
padding: 0;
list-style-type: none;
}

.timeline-small-item {
position: relative;
padding-left: 24px;
padding-bottom: 20px;
border-left: 2px solid #e9ecef;
}

.timeline-small-item:last-child {
border-left: 2px solid transparent;
}

.timeline-small-item::before {
content: '';
position: absolute;
left: -7px;
top: 0;
width: 12px;
height: 12px;
border-radius: 50%;
background-color: #007bff;
}

.timeline-small-date {
display: block;
font-size: 0.8rem;
color: #6c757d;
margin-bottom: 5px;
}

.timeline-small-title {
margin-bottom: 5px;
font-size: 1rem;
}

.job-description, .job-requirements {
white-space: pre-line;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
// Select all checkbox functionality
const selectAllCheckbox = document.getElementById('selectAll');
if (selectAllCheckbox) {
selectAllCheckbox.addEventListener('change', function() {
document.querySelectorAll('.job-checkbox').forEach(checkbox => {
checkbox.checked = this.checked;
});
updateBulkActionButton();
});
}

// Individual checkbox change
document.querySelectorAll('.job-checkbox').forEach(checkbox => {
checkbox.addEventListener('change', updateBulkActionButton);
});

// Enable/disable bulk action button
function updateBulkActionButton() {
const checkedBoxes = document.querySelectorAll('.job-checkbox:checked');
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
echo "<!-- Page generated at " . date('Y-m-d H:i:s') . " by HasinduNimesh -->";
?>