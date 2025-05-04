<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireAdmin();

$pageTitle = 'System Logs';
$type = isset($_GET['type']) ? $_GET['type'] : 'activity';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

// Base queries
$activityQuery = "SELECT * FROM system_activity_log";
$errorQuery = "SELECT * FROM system_errors";

// Apply search filter
if (!empty($search)) {
    if ($type === 'errors') {
        $errorQuery .= " WHERE error_message LIKE ?";
        $errorParams = ["%" . $search . "%"];
    } else {
        $activityQuery .= " WHERE (user_name LIKE ? OR action LIKE ? OR details LIKE ?)";
        $activityParams = ["%" . $search . "%", "%" . $search . "%", "%" . $search . "%"];
    }
}

// Apply type filter for activity logs
if ($type === 'activity' && !empty($filter)) {
    if (!empty($search)) {
        $activityQuery .= " AND action = ?";
    } else {
        $activityQuery .= " WHERE action = ?";
    }
    $activityParams[] = $filter;
}

// Order and limit
$activityQuery .= " ORDER BY timestamp DESC LIMIT $limit";
$errorQuery .= " ORDER BY timestamp DESC LIMIT $limit";

// Execute queries
if ($type === 'errors') {
    $logs = !empty($search) ? 
        $db->fetchAll($errorQuery, $errorParams) : 
        $db->fetchAll($errorQuery);
} else {
    $logs = !empty($search) || !empty($filter) ? 
        $db->fetchAll($activityQuery, $activityParams) : 
        $db->fetchAll($activityQuery);
}

// Get distinct activity types for filter
$actionTypes = $db->fetchAll("SELECT DISTINCT action FROM system_activity_log ORDER BY action");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body class="sb-nav-fixed">
    <?php include_once '../includes/admin_header.php'; ?>
    
    <div id="layoutSidenav">
        <?php include_once '../includes/admin_sidebar.php'; ?>
        
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4"><?= $pageTitle ?></h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active"><?= $pageTitle ?></li>
                    </ol>
                    
                    <!-- Log Navigation Tabs -->
                    <ul class="nav nav-tabs mb-4">
                        <li class="nav-item">
                            <a class="nav-link <?= $type === 'activity' ? 'active' : '' ?>" href="logs.php?type=activity">
                                <i class="fas fa-history me-1"></i> Activity Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $type === 'errors' ? 'active' : '' ?>" href="logs.php?type=errors">
                                <i class="fas fa-exclamation-triangle me-1"></i> Error Logs
                            </a>
                        </li>
                    </ul>
                    
                    <!-- Logs Card -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <i class="<?= $type === 'errors' ? 'fas fa-exclamation-circle' : 'fas fa-clipboard-list' ?> me-1"></i>
                                <?= $type === 'errors' ? 'System Errors' : 'Activity Logs' ?>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                    <i class="fas fa-download me-1"></i> Export
                                </button>
                                <?php if (count($logs) > 0 && isAdmin()): ?>
                                    <button class="btn btn-sm btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                                        <i class="fas fa-trash me-1"></i> Clear Logs
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body">
                            <!-- Filter and Search Form -->
                            <form method="get" class="row mb-4 align-items-end">
                                <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                                
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <label for="search" class="form-label">Search</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Search logs..." value="<?= htmlspecialchars($search) ?>">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if ($type === 'activity'): ?>
                                <div class="col-md-3 mb-3 mb-md-0">
                                    <label for="filter" class="form-label">Filter by Action</label>
                                    <select class="form-select" id="filter" name="filter">
                                        <option value="">All Actions</option>
                                        <?php foreach ($actionTypes as $action): ?>
                                            <option value="<?= htmlspecialchars($action['action']) ?>" 
                                                <?= $filter === $action['action'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(ucfirst($action['action'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-2 mb-3 mb-md-0">
                                    <label for="limit" class="form-label">Show Entries</label>
                                    <select class="form-select" id="limit" name="limit" onchange="this.form.submit()">
                                        <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                                        <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                                        <option value="200" <?= $limit === 200 ? 'selected' : '' ?>>200</option>
                                        <option value="500" <?= $limit === 500 ? 'selected' : '' ?>>500</option>
                                    </select>
                                </div>
                                
                                <?php if (!empty($search) || !empty($filter)): ?>
                                <div class="col-md-2 mb-3 mb-md-0">
                                    <a href="logs.php?type=<?= $type ?>" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-times me-1"></i> Clear Filters
                                    </a>
                                </div>
                                <?php endif; ?>
                            </form>
                            
                            <!-- Logs Table -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="logsTable">
                                    <thead class="table-light">
                                        <?php if ($type === 'errors'): ?>
                                        <tr>
                                            <th>Time</th>
                                            <th width="15%">Type</th>
                                            <th>Message</th>
                                            <th width="10%">Actions</th>
                                        </tr>
                                        <?php else: ?>
                                        <tr>
                                            <th width="15%">Time</th>
                                            <th width="15%">User</th>
                                            <th width="15%">Action</th>
                                            <th>Details</th>
                                            <th width="10%">IP Address</th>
                                        </tr>
                                        <?php endif; ?>
                                    </thead>
                                    <tbody>
                                        <?php if (count($logs) > 0): ?>
                                            <?php foreach ($logs as $log): ?>
                                                <?php if ($type === 'errors'): ?>
                                                <tr>
                                                    <td><?= formatDateTime($log['timestamp'], 'M d, Y - H:i') ?></td>
                                                    <td>
                                                        <span class="badge <?= getErrorBadgeClass($log['error_type']) ?>">
                                                            <?= htmlspecialchars($log['error_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($log['error_message']) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-outline-info viewDetails" 
                                                                data-details="<?= htmlspecialchars($log['error_details'] ?? '') ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                <tr>
                                                    <td><?= formatDateTime($log['timestamp'], 'M d, Y - H:i') ?></td>
                                                    <td>
                                                        <?php if (!empty($log['user_name'])): ?>
                                                            <a href="users.php?action=view&id=<?= $log['user_id'] ?? 0 ?>">
                                                                <?= htmlspecialchars($log['user_name']) ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">System</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?= getActionBadgeClass($log['action']) ?>">
                                                            <?= htmlspecialchars(ucfirst($log['action'])) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($log['details']) ?></td>
                                                    <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="<?= $type === 'errors' ? '4' : '5' ?>" class="text-center py-4">
                                                    <?php if (!empty($search) || !empty($filter)): ?>
                                                        <div class="alert alert-info mb-0">
                                                            <i class="fas fa-info-circle me-2"></i> No logs match your search criteria
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="alert alert-success mb-0">
                                                            <i class="fas fa-check-circle me-2"></i> No <?= $type === 'errors' ? 'errors' : 'activity' ?> logs to display
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (count($logs) > 10): ?>
                        <div class="card-footer">
                            <div class="small text-muted">
                                Showing <?= count($logs) ?> of <?= $limit ?> entries | 
                                <a href="logs.php?type=<?= $type ?>&limit=<?= $limit + 50 ?>">Show more</a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </main>
            
            <?php include_once '../includes/admin_footer.php'; ?>
        </div>
    </div>

    <!-- Error Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">Error Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <pre id="errorDetails" class="bg-light p-3" style="max-height: 400px; overflow-y: auto;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="exportForm" action="export_logs.php" method="post">
                        <input type="hidden" name="type" value="<?= $type ?>">
                        
                        <div class="mb-3">
                            <label for="exportFormat" class="form-label">Export Format</label>
                            <select class="form-select" id="exportFormat" name="format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dateRange" class="form-label">Date Range</label>
                            <select class="form-select" id="dateRange" name="date_range">
                                <option value="all">All Logs</option>
                                <option value="today">Today</option>
                                <option value="week">Last 7 Days</option>
                                <option value="month">Last 30 Days</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <div id="customDateRange" class="row mb-3" style="display: none;">
                            <div class="col-md-6">
                                <label for="startDate" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDate" name="start_date">
                            </div>
                            <div class="col-md-6">
                                <label for="endDate" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDate" name="end_date">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('exportForm').submit();">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Logs Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="clearLogsModalLabel">Clear Logs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Warning: This action cannot be undone!
                    </div>
                    <p>Are you sure you want to clear all <?= $type === 'errors' ? 'error' : 'activity' ?> logs?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="clear_logs.php?type=<?= $type ?>&csrf=<?= $_SESSION['csrf_token'] ?>" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i> Clear Logs
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // View error details
            document.querySelectorAll('.viewDetails').forEach(function(button) {
                button.addEventListener('click', function() {
                    const details = this.getAttribute('data-details');
                    document.getElementById('errorDetails').textContent = details || 'No additional details available.';
                    const detailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));
                    detailsModal.show();
                });
            });
            
            // Custom date range toggle
            document.getElementById('dateRange').addEventListener('change', function() {
                document.getElementById('customDateRange').style.display = 
                    this.value === 'custom' ? 'flex' : 'none';
            });
        });
        
        // Add these helper functions at the bottom of the file
        <?php
        function getActionBadgeClass($action) {
            switch (strtolower($action)) {
                case 'login':
                    return 'bg-success';
                case 'logout':
                    return 'bg-secondary';
                case 'create':
                    return 'bg-primary';
                case 'update':
                    return 'bg-info';
                case 'delete':
                    return 'bg-danger';
                default:
                    return 'bg-secondary';
            }
        }

        function getErrorBadgeClass($errorType) {
            switch (strtolower($errorType)) {
                case 'fatal':
                    return 'bg-danger';
                case 'warning':
                    return 'bg-warning text-dark';
                case 'notice':
                    return 'bg-info';
                case 'deprecated':
                    return 'bg-secondary';
                default:
                    return 'bg-danger';
            }
        }
        ?>
    </script>
</body>
</html>