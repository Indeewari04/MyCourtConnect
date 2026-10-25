<?php
// admin_clients.php
session_start();
require_once __DIR__ . "/classes/Database.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Handle actions (delete)
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['client_id'])) {
        $client_id = intval($_POST['client_id']);
        $action = $_POST['action'];
        
        if ($action === 'delete') {
            // Delete client
            $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->bind_param("i", $client_id);
            
            if ($stmt->execute()) {
                $message = "Client deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting client: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Get all clients with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where_conditions = [];
$query_params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR c.nic LIKE ?)";
    $search_term = "%$search%";
    $query_params = array_merge($query_params, [$search_term, $search_term, $search_term]);
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM clients c 
              JOIN users u ON c.id = u.id AND u.role = 'client' $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($query_params)) {
    $count_stmt->bind_param(str_repeat('s', count($query_params)), ...$query_params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_clients = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_clients / $limit);
$count_stmt->close();

// Get clients with filters
$sql = "SELECT c.*, u.full_name, u.email FROM clients c 
        JOIN users u ON c.id = u.id AND u.role = 'client'
        $where_clause ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$query_params[] = $limit;
$query_params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($query_params)) {
    $stmt->bind_param(str_repeat('s', count($query_params)), ...$query_params);
}
$stmt->execute();
$clients = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management | MyCourtConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-light: #8b5cf6;
            --primary-dark: #6d28d9;
            --secondary: #64748b;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-border: #334155;
            --dark-text: #e2e8f0;
            --dark-hover: #2d3748;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --sidebar-width: 280px;
            --border-radius: 12px;
            --transition: all 0.3s ease;
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--dark-bg);
            color: var(--dark-text);
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
            background: linear-gradient(180deg, var(--dark-card) 0%, #131c2f 100%);
            color: white;
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
            background: rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h3 {
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-item {
            margin: 6px 12px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            color: var(--dark-text);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-link:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--primary);
            opacity: 0;
            transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(124, 58, 237, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active:before {
            opacity: 1;
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
            color: var(--primary-light);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 0;
            background: var(--dark-bg);
        }

        .header {
            background: var(--dark-card);
            height: 80px;
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
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(124, 58, 237, 0.3);
        }

        .content {
            padding: 30px;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            background: var(--dark-card);
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px -10px rgba(0, 0, 0, 0.4);
        }

        .card-header {
            background: linear-gradient(90deg, var(--dark-card) 0%, #232f47 100%);
            border-bottom: 1px solid var(--dark-border);
            padding: 20px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-light);
        }

        .card-body {
            padding: 24px;
        }

        /* Table Styles */
        .table-container {
            background: var(--dark-card);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .table {
            margin: 0;
            color: var(--dark-text);
            border-color: var(--dark-border);
        }

        .table th {
            background-color: rgba(30, 41, 59, 0.8);
            border-bottom: 1px solid var(--dark-border);
            font-weight: 600;
            color: var(--primary-light);
            padding: 16px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 1px;
        }

        .table td {
            padding: 16px;
            vertical-align: middle;
            border-color: var(--dark-border);
        }

        .table-hover tbody tr {
            transition: var(--transition);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(124, 58, 237, 0.1);
            transform: scale(1.01);
        }

        /* Action Buttons */
        .btn-action {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn-view {
            background: linear-gradient(135deg, var(--info) 0%, #0b83b8 100%);
            color: white;
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(14, 165, 233, 0.3);
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }

        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }

        /* Search and Filter */
        .search-filter {
            background: var(--dark-card);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
            border: 1px solid var(--dark-border);
        }

        .form-control {
            background-color: rgba(30, 41, 59, 0.5);
            border: 1px solid var(--dark-border);
            color: var(--dark-text);
            padding: 12px 16px;
            border-radius: 8px;
            transition: var(--transition);
        }

        .form-control:focus {
            background-color: rgba(30, 41, 59, 0.8);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
            color: var(--dark-text);
        }

        .btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(124, 58, 237, 0.3);
        }

        .btn-outline-secondary {
            background: transparent;
            border: 1px solid var(--dark-border);
            color: var(--dark-text);
        }

        .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: var(--primary);
            color: var(--primary-light);
        }

        /* Pagination */
        .pagination {
            margin: 20px 0 0 0;
        }

        .page-link {
            border-radius: 8px;
            margin: 0 4px;
            background-color: var(--dark-card);
            border: 1px solid var(--dark-border);
            color: var(--dark-text);
            transition: var(--transition);
        }

        .page-link:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-color: var(--primary);
        }

        /* Badge */
        .badge {
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 500;
        }

        .bg-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
        }

        /* Alert */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 16px 20px;
            box-shadow: var(--card-shadow);
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);
            color: white;
        }

        /* Modal */
        .modal-content {
            background: var(--dark-card);
            border-radius: var(--border-radius);
            border: 1px solid var(--dark-border);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            border-bottom: 1px solid var(--dark-border);
            padding: 20px 24px;
        }

        .modal-title {
            color: var(--primary-light);
            font-weight: 600;
        }

        .modal-body {
            padding: 24px;
        }

        .modal-footer {
            border-top: 1px solid var(--dark-border);
            padding: 20px 24px;
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

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--dark-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card, .search-filter {
            animation: fadeIn 0.5s ease-out;
        }

        /* No results styling */
        .no-results {
            padding: 40px 20px;
            text-align: center;
            color: var(--secondary);
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
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
                    <a href="admin_lawyers.php" class="nav-link">
                        <i class="fas fa-gavel"></i> Lawyer Management
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_clients.php" class="nav-link active">
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
                <h4>Client Management</h4>
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

                <!-- Search Section -->
                <div class="search-filter">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" name="search" placeholder="Search clients by name, email, or NIC..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="admin_clients.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-refresh"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Clients Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-users"></i> Clients List</span>
                        <span class="badge bg-primary"><?= $total_clients ?> Total</span>
                    </div>
                    <div class="card-body">
                        <?php if ($clients->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>NIC</th>
                                            <th>Gender</th>
                                            <th>City</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($client = $clients->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                            <?= strtoupper(substr($client['full_name'], 0, 1)) ?>
                                                        </div>
                                                        <div class="fw-semibold"><?= htmlspecialchars($client['full_name']) ?></div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($client['email']) ?></td>
                                                <td><?= htmlspecialchars($client['phone'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($client['nic'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($client['gender'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($client['city'] ?? 'N/A') ?></td>
                                                <td><?= date('M j, Y', strtotime($client['created_at'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-action btn-view me-2" 
                                                                onclick="viewClient(<?= $client['id'] ?>)" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this client? This action cannot be undone.')">
                                                            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
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
                                <nav aria-label="Clients pagination">
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
                            <div class="no-results">
                                <i class="fas fa-users-slash"></i>
                                <h5>No clients found</h5>
                                <p class="text-muted">
                                    <?= !empty($search) 
                                        ? 'Try adjusting your search criteria' 
                                        : 'No clients have registered yet' 
                                    ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Client Details Modal -->
    <div class="modal fade" id="clientDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Client Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="clientDetailsContent">
                    <!-- Client details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to view client details
        function viewClient(clientId) {
            fetch('get_client_details.php?id=' + clientId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('clientDetailsContent').innerHTML = data;
                    var modal = new bootstrap.Modal(document.getElementById('clientDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('clientDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Error loading client details</div>';
                    var modal = new bootstrap.Modal(document.getElementById('clientDetailsModal'));
                    modal.show();
                });
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