<?php
// admin_lawyers.php
session_start();
require_once __DIR__ . "/classes/Database.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Handle actions (delete, update status)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['lawyer_id'])) {
        $lawyer_id = intval($_POST['lawyer_id']);
        $action = $_POST['action'];
        
        if ($action === 'delete') {
            // Delete lawyer
            $stmt = $conn->prepare("DELETE FROM lawyers WHERE id = ?");
            $stmt->bind_param("i", $lawyer_id);
            
            if ($stmt->execute()) {
                $message = "Lawyer deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting lawyer: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        } 
        elseif ($action === 'update_status') {
            $new_status = $_POST['status'];
            
            // Get lawyer details for email
            $lawyer_stmt = $conn->prepare("SELECT u.full_name, u.email FROM users u JOIN lawyers l ON u.id = l.id WHERE l.id = ?");
            $lawyer_stmt->bind_param("i", $lawyer_id);
            $lawyer_stmt->execute();
            $lawyer_result = $lawyer_stmt->get_result();
            $lawyer_data = $lawyer_result->fetch_assoc();
            $lawyer_stmt->close();
            
            $stmt = $conn->prepare("UPDATE lawyers SET approved = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $lawyer_id);
            
            if ($stmt->execute()) {
                // Send email notification if lawyer data exists
                if ($lawyer_data && ($new_status === 'approved' || $new_status === 'rejected')) {
                    require_once __DIR__ . "/classes/EmailService.php";
                    $emailService = new EmailService();
                    
                    $isApproved = ($new_status === 'approved');
                    $emailSent = $emailService->sendApprovalEmail(
                        $lawyer_data['email'], 
                        $lawyer_data['full_name'], 
                        $isApproved
                    );
                    
                    if ($emailSent) {
                        $message = "Lawyer status updated successfully and email notification sent!";
                    } else {
                        $message = "Lawyer status updated successfully, but email notification failed to send. " .
                                 "<br><small><strong>Note:</strong> The lawyer's status has been updated in the database. " .
                                 "You may need to manually inform " . htmlspecialchars($lawyer_data['full_name']) . 
                                 " at: " . htmlspecialchars($lawyer_data['email']) . 
                                 " <br>To fix email issues, run <a href='test_email.php' target='_blank'>email diagnostics</a></small>";
                    }
                } else {
                    $message = "Lawyer status updated successfully!";
                }
                $message_type = "success";
            } else {
                $message = "Error updating lawyer status: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Get all lawyers with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search and filter functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR l.bar_membership_no LIKE ?)";
    $search_term = "%$search%";
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "l.approved = ?";
    $query_params[] = $status_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM lawyers l 
              JOIN users u ON l.id = u.id AND u.role = 'lawyer' $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($query_params)) {
    $count_stmt->bind_param(str_repeat('s', count($query_params)), ...$query_params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_lawyers = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_lawyers / $limit);
$count_stmt->close();

// Get lawyers with filters
$sql = "SELECT l.*, u.full_name, u.email FROM lawyers l 
        JOIN users u ON l.id = u.id AND u.role = 'lawyer'
        $where_clause ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$query_params[] = $limit;
$query_params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($query_params)) {
    $stmt->bind_param(str_repeat('s', count($query_params)), ...$query_params);
}
$stmt->execute();
$lawyers = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lawyer Management | MyCourtConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-hover: #5649c9;
            --secondary: #a29bfe;
            --success: #00b894;
            --danger: #d63031;
            --warning: #fdcb6e;
            --info: #0984e3;
            --dark-bg: #121826;
            --dark-card: #1a2236;
            --dark-border: #2a3449;
            --text-primary: #f8f9fa;
            --text-secondary: #adb5bd;
            --sidebar-width: 280px;
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-card);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 1000;
            border-right: 1px solid var(--dark-border);
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid var(--dark-border);
        }

        .sidebar-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
            color: var(--primary);
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 4px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(108, 92, 231, 0.15);
            color: var(--primary);
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 0;
        }

        .header {
            background: var(--dark-card);
            height: 70px;
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--dark-border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content {
            padding: 30px;
        }

        /* Card Styles */
        .card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            background: rgba(26, 34, 54, 0.8);
            border-bottom: 1px solid var(--dark-border);
            padding: 20px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            backdrop-filter: blur(10px);
        }

        .card-body {
            padding: 24px;
        }

        /* Table Styles */
        .table-container {
            background: var(--dark-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--dark-border);
        }

        .table {
            margin: 0;
            color: var(--text-primary);
        }

        .table th {
            background-color: rgba(42, 52, 73, 0.5);
            border-bottom: 1px solid var(--dark-border);
            padding: 16px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--dark-border);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(108, 92, 231, 0.08);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .status-pending {
            background-color: rgba(253, 203, 110, 0.15);
            color: var(--warning);
        }

        .status-approved {
            background-color: rgba(0, 184, 148, 0.15);
            color: var(--success);
        }

        .status-rejected {
            background-color: rgba(214, 48, 49, 0.15);
            color: var(--danger);
        }

        /* Action Buttons */
        .btn-action {
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
        }

        .btn-view {
            background-color: rgba(9, 132, 227, 0.15);
            color: var(--info);
        }

        .btn-view:hover {
            background-color: rgba(9, 132, 227, 0.25);
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: rgba(253, 203, 110, 0.15);
            color: var(--warning);
        }

        .btn-edit:hover {
            background-color: rgba(253, 203, 110, 0.25);
            transform: translateY(-2px);
        }

        .btn-delete {
            background-color: rgba(214, 48, 49, 0.15);
            color: var(--danger);
        }

        .btn-delete:hover {
            background-color: rgba(214, 48, 49, 0.25);
            transform: translateY(-2px);
        }

        /* Search and Filter */
        .search-filter {
            background: var(--dark-card);
            padding: 20px;
            border-radius: var(--border-radius);
            border: 1px solid var(--dark-border);
            margin-bottom: 24px;
        }

        .form-control, .form-select {
            background-color: rgba(26, 34, 54, 0.5);
            border: 1px solid var(--dark-border);
            color: var(--text-primary);
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            background-color: rgba(26, 34, 54, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(108, 92, 231, 0.25);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
        }

        .btn-outline-secondary {
            color: var(--text-secondary);
            border-color: var(--dark-border);
        }

        .btn-outline-secondary:hover {
            background-color: var(--dark-border);
            border-color: var(--dark-border);
            color: var(--text-primary);
            transform: translateY(-2px);
        }

        /* Pagination */
        .pagination {
            margin: 20px 0 0 0;
        }

        .page-link {
            background-color: var(--dark-card);
            border: 1px solid var(--dark-border);
            color: var(--text-primary);
            border-radius: 8px;
            margin: 0 4px;
        }

        .page-link:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.active .page-link {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .page-item.disabled .page-link {
            background-color: rgba(26, 34, 54, 0.5);
            border-color: var(--dark-border);
            color: var(--text-secondary);
        }

        /* Alert */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            backdrop-filter: blur(10px);
        }

        .alert-success {
            background-color: rgba(0, 184, 148, 0.15);
            color: var(--success);
        }

        .alert-danger {
            background-color: rgba(214, 48, 49, 0.15);
            color: var(--danger);
        }

        /* Modal */
        .modal-content {
            background-color: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--border-radius);
        }

        .modal-header {
            border-bottom: 1px solid var(--dark-border);
        }

        .modal-footer {
            border-top: 1px solid var(--dark-border);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 0;
            }
            
            .sidebar.active {
                transform: translateX(0);
                width: var(--sidebar-width);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .table-responsive {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-scale-balanced"></i> MyCourtConnect</h3>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_lawyers.php" class="nav-link active">
                        <i class="fas fa-gavel"></i> Lawyer Management
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_clients.php" class="nav-link">
                        <i class="fas fa-users"></i> Client Management
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_students.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i> Student Management
                    </a>
                </div>
                <div class="nav-item mt-4">
                    <a href="logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <h4>Lawyer Management</h4>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                    </div>
                    <span>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <!-- Messages -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filter Section -->
                <div class="search-filter">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search lawyers..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="admin_lawyers.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Lawyers Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users"></i> Lawyers List</span>
                        <span class="badge bg-primary"><?= $total_lawyers ?> Total</span>
                    </div>
                    <div class="card-body">
                        <?php if ($lawyers->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Bar Number</th>
                                            <th>Status</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($lawyer = $lawyers->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                            <?= strtoupper(substr($lawyer['full_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($lawyer['full_name']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($lawyer['practice_type'] ?? 'N/A') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($lawyer['email']) ?></td>
                                                <td><?= htmlspecialchars($lawyer['phone'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($lawyer['bar_membership_no']) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= $lawyer['approved'] ?>">
                                                        <?= ucfirst($lawyer['approved']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($lawyer['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="admin_dashboard.php?view_lawyer=<?= $lawyer['id'] ?>" 
                                                           class="btn btn-action btn-view" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-action btn-edit" 
                                                                onclick="editLawyer(<?= $lawyer['id'] ?>)" title="Edit Status">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this lawyer? This action cannot be undone.')">
                                                            <input type="hidden" name="lawyer_id" value="<?= $lawyer['id'] ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <button type="submit" class="btn btn-action btn-delete" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Lawyers pagination">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                                    <?= $i ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>

                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                                <h5>No lawyers found</h5>
                                <p class="text-muted">
                                    <?= !empty($search) || !empty($status_filter) 
                                        ? 'Try adjusting your search or filter criteria' 
                                        : 'No lawyers have registered yet' 
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Status Modal -->
    <div class="modal fade" id="editLawyerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Lawyer Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="statusForm">
                    <div class="modal-body">
                        <input type="hidden" name="lawyer_id" id="lawyerId">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="mb-3">
                            <label class="form-label">Select Status</label>
                            <select class="form-select" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to open edit modal
        function editLawyer(lawyerId) {
            document.getElementById('lawyerId').value = lawyerId;
            var modal = new bootstrap.Modal(document.getElementById('editLawyerModal'));
            modal.show();
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>