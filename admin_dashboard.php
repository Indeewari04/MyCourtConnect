<?php
// admin_dashboard.php
session_start();
require_once __DIR__ . "/classes/Database.php";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Initialize messages
$success_message = '';
$error_message = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['lawyer_id'])) {
        $lawyer_id = intval($_POST['lawyer_id']); // Ensure it's an integer
        $action = $_POST['action'];
        
        if ($action === 'approve' || $action === 'reject') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            
            $stmt = $conn->prepare("UPDATE lawyers SET approved = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $lawyer_id);
            
            if ($stmt->execute()) {
                // Get lawyer info for email
                $lawyer_stmt = $conn->prepare("
                    SELECT u.full_name, u.email
                    FROM users u 
                    JOIN lawyers l ON l.id = u.id 
                    WHERE l.id = ?
                ");
                $lawyer_stmt->bind_param("i", $lawyer_id);
                $lawyer_stmt->execute();
                $lawyer_result = $lawyer_stmt->get_result();
                
                $email_sent = false;
                $email_error = '';
                
                if ($lawyer_result->num_rows === 1) {
                    $lawyer = $lawyer_result->fetch_assoc();
                    
                    // Send email notification
                    try {
                        require_once __DIR__ . "/classes/EmailService.php";
                        $emailService = new EmailService();
                        $email_sent = $emailService->sendApprovalEmail(
                            $lawyer['email'], 
                            $lawyer['full_name'], 
                            $action === 'approve'
                        );
                        
                        if (!$email_sent) {
                            $email_error = "Email notification failed, but lawyer was " . $action . "d.";
                        }
                    } catch (Exception $e) {
                        $email_error = "Email service error: " . $e->getMessage();
                        error_log($email_error);
                    }
                }
                
                $lawyer_stmt->close();
                
                // Remove the notification
                $notif_stmt = $conn->prepare("DELETE FROM notifications WHERE type = 'lawyer_approval' AND related_id = ?");
                $notif_stmt->bind_param("i", $lawyer_id);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                if ($email_sent) {
                    $success_message = "Lawyer " . $action . "d successfully and email notification sent!";
                } else {
                    $success_message = "Lawyer " . $action . "d successfully. Email notification failed: " . $email_error . 
                                     " <br><small><strong>Note:</strong> The lawyer's status has been updated in the database. " .
                                     "You may need to manually inform them at: " . htmlspecialchars($lawyer['email'] ?? 'email not found') . 
                                     " <br>To fix email issues, run <a href='test_email.php' target='_blank'>email diagnostics</a></small>";
                }
            } else {
                $error_message = "Failed to update lawyer status: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Handle lawyer details request
$lawyer_details = null;
if (isset($_GET['view_lawyer'])) {
    $lawyer_id = intval($_GET['view_lawyer']);
    $stmt = $conn->prepare("
        SELECT l.*, u.full_name, u.email, u.created_at as registration_date
        FROM lawyers l 
        JOIN users u ON l.id = u.id AND u.role = 'lawyer' 
        WHERE l.id = ?
    ");
    $stmt->bind_param("i", $lawyer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $lawyer_details = $result->fetch_assoc();
        
        // Format the courts and jurisdictions as arrays
        if (!empty($lawyer_details['primary_courts'])) {
            $lawyer_details['primary_courts'] = explode(',', $lawyer_details['primary_courts']);
        }
        if (!empty($lawyer_details['geographical_jurisdictions'])) {
            $lawyer_details['geographical_jurisdictions'] = explode(',', $lawyer_details['geographical_jurisdictions']);
        }
    }
    $stmt->close();
}

// Get pending lawyers
$pending_lawyers = [];
$result = $conn->query("
    SELECT l.id, u.full_name, u.email 
    FROM lawyers l 
    JOIN users u ON l.id = u.id 
    WHERE l.approved = 'pending' 
    ORDER BY l.id DESC
");

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_lawyers[] = $row;
    }
}

// Get notifications
$notifications = [];
$notif_result = $conn->query("SELECT * FROM notifications WHERE type = 'lawyer_approval' ORDER BY created_at DESC");

if ($notif_result && $notif_result->num_rows > 0) {
    while ($row = $notif_result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Get stats for dashboard
$stats = [
    'total_lawyers' => $conn->query("SELECT COUNT(*) as count FROM lawyers")->fetch_assoc()['count'],
    'pending_approvals' => $conn->query("SELECT COUNT(*) as count FROM lawyers WHERE approved = 'pending'")->fetch_assoc()['count'],
    'total_clients' => $conn->query("SELECT COUNT(*) as count FROM clients")->fetch_assoc()['count'],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MyCourtConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6c5ce7;
            --primary-light: #7c6ce9;
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
            --header-height: 70px;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
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
            color: var(--primary-light);
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
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            color: white;
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
            height: var(--header-height);
            padding: 0 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--dark-border);
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
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content {
            padding: 30px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--dark-card);
            padding: 24px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 1px solid var(--dark-border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .stat-icon.blue { background: rgba(108, 92, 231, 0.2); color: var(--primary); }
        .stat-icon.green { background: rgba(0, 184, 148, 0.2); color: var(--success); }
        .stat-icon.orange { background: rgba(253, 203, 110, 0.2); color: var(--warning); }
        .stat-icon.purple { background: rgba(162, 155, 254, 0.2); color: var(--secondary); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Cards */
        .card {
            background: var(--dark-card);
            border: 1px solid var(--dark-border);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 24px;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.35);
        }

        .card-header {
            background: var(--dark-card);
            border-bottom: 1px solid var(--dark-border);
            padding: 20px 24px;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header i {
            margin-right: 8px;
            color: var(--primary);
        }

        .card-body {
            padding: 24px;
        }

        /* Lawyer Item */
        .lawyer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--dark-border);
        }

        .lawyer-item:last-child {
            border-bottom: none;
        }

        .lawyer-info h6 {
            font-weight: 600;
            margin-bottom: 4px;
            cursor: pointer;
            color: var(--primary-light);
            transition: var(--transition);
        }

        .lawyer-info h6:hover,
        .lawyer-name:hover {
            color: var(--secondary);
            text-decoration: underline;
        }
        
        .lawyer-name {
            cursor: pointer;
            transition: var(--transition);
        }

        .lawyer-info p {
            color: var(--text-secondary);
            margin: 0;
            font-size: 0.9rem;
        }

        .lawyer-actions {
            display: flex;
            gap: 8px;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: var(--transition);
            border: none;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        .btn-success { 
            background: var(--success);
            color: white;
        }
        
        .btn-danger { 
            background: var(--danger);
            color: white;
        }
        
        .btn-success:hover { 
            background: #00a382;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 184, 148, 0.3);
        }
        
        .btn-danger:hover { 
            background: #c02a2a;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(214, 48, 49, 0.3);
        }

        /* Notification Item */
        .notification-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--dark-border);
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item p {
            margin: 0;
            line-height: 1.5;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Alert */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.15);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-warning {
            background: rgba(253, 203, 110, 0.15);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }

        .alert-danger {
            background: rgba(214, 48, 49, 0.15);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        /* Badge */
        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Modal Styles */
        .modal-content {
            background: var(--dark-card);
            border-radius: var(--border-radius);
            border: 1px solid var(--dark-border);
            box-shadow: var(--box-shadow);
            color: var(--text-primary);
        }

        .modal-header {
            background: var(--dark-card);
            color: var(--text-primary);
            border-bottom: 1px solid var(--dark-border);
        }

        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .modal-body {
            padding: 24px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--dark-border);
            padding-bottom: 16px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-secondary);
            min-width: 180px;
        }

        .detail-value {
            flex: 1;
            color: var(--text-primary);
        }

        .file-link {
            display: inline-block;
            padding: 6px 12px;
            background: rgba(108, 92, 231, 0.2);
            color: var(--primary-light);
            border-radius: 6px;
            text-decoration: none;
            transition: var(--transition);
        }

        .file-link:hover {
            background: rgba(108, 92, 231, 0.3);
            color: var(--primary-light);
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .badge-list .badge {
            background: rgba(108, 92, 231, 0.2);
            color: var(--primary-light);
        }

        /* Image and document preview styles */
        .image-preview,
        .document-preview {
            text-align: center;
            margin-top: 8px;
        }
        
        .image-preview img,
        .document-preview img {
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }
        
        .image-preview img:hover,
        .document-preview img:hover {
            transform: scale(1.05);
        }
        
        .pdf-placeholder {
            transition: all 0.3s ease;
        }
        
        .pdf-placeholder:hover {
            background: #e9ecef !important;
            transform: scale(1.02);
        }

        /* Custom Scrollbar */
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
            background: var(--primary-light);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease forwards;
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
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                min-width: auto;
                margin-bottom: 8px;
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
                    <a href="admin_dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="admin_lawyers.php" class="nav-link">
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
                <h4>Admin Dashboard</h4>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                    </div>
                    <span>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success animate-fade-in">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger animate-fade-in">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card animate-fade-in" style="animation-delay: 0.1s">
                        <div class="stat-icon blue">
                            <i class="fas fa-gavel"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total_lawyers'] ?></div>
                        <div class="stat-label">Total Lawyers</div>
                    </div>
                    
                    <div class="stat-card animate-fade-in" style="animation-delay: 0.2s">
                        <div class="stat-icon orange">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= $stats['pending_approvals'] ?></div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                    
                    <div class="stat-card animate-fade-in" style="animation-delay: 0.3s">
                        <div class="stat-icon green">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?= $stats['total_clients'] ?></div>
                        <div class="stat-label">Total Clients</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <!-- Pending Approvals Card -->
                        <div class="card animate-fade-in" style="animation-delay: 0.4s">
                            <div class="card-header">
                                <span><i class="fas fa-user-clock"></i> Pending Lawyer Approvals</span>
                                <?php if (count($pending_lawyers) > 0): ?>
                                    <span class="badge bg-danger"><?= count($pending_lawyers) ?> Pending</span>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (count($pending_lawyers) > 0): ?>
                                    <?php foreach ($pending_lawyers as $lawyer): ?>
                                        <div class="lawyer-item">
                                            <div class="lawyer-info">
                                                <h6 class="lawyer-name" onclick="viewLawyerDetails(<?= $lawyer['id'] ?>)">
                                                    <?= htmlspecialchars($lawyer['full_name']) ?>
                                                </h6>
                                                <p><?= htmlspecialchars($lawyer['email']) ?></p>
                                                <small class="text-muted">ID: <?= $lawyer['id'] ?></small>
                                            </div>
                                            <div class="lawyer-actions">
                                                <button type="button" class="btn btn-info btn-sm" onclick="viewLawyerDetails(<?= $lawyer['id'] ?>)">
                                                    <i class="fas fa-eye"></i> View Details
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="lawyer_id" value="<?= $lawyer['id'] ?>">
                                                    <button type="submit" name="action" value="approve" class="btn btn-success btn-sm"
                                                            onclick="return confirm('Are you sure you want to approve this lawyer?')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm"
                                                            onclick="return confirm('Are you sure you want to reject this lawyer?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <p>No pending approvals</p>
                                        <small class="text-muted">All lawyers have been processed</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <!-- Notifications Card -->
                        <div class="card animate-fade-in" style="animation-delay: 0.5s">
                            <div class="card-header">
                                <span><i class="fas fa-bell"></i> Recent Notifications</span>
                            </div>
                            <div class="card-body">
                                <?php if (count($notifications) > 0): ?>
                                    <?php foreach ($notifications as $notif): ?>
                                        <div class="notification-item">
                                            <p class="mb-1"><?= htmlspecialchars($notif['message']) ?></p>
                                            <small class="notification-time">
                                                <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No notifications</p>
                                        <small class="text-muted">Notifications will appear here</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Lawyer Details Modal -->
    <div class="modal fade" id="lawyerDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-tie"></i> Lawyer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($lawyer_details): ?>
                        <div class="detail-row">
                            <div class="detail-label">Full Name:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['full_name']) ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Email:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['email']) ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Phone:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['phone'] ?? 'Not provided') ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">NIC Number:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['nic'] ?? 'Not provided') ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Date of Birth:</div>
                            <div class="detail-value"><?= !empty($lawyer_details['dob']) ? date('M j, Y', strtotime($lawyer_details['dob'])) : 'Not provided' ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Gender:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['gender'] ?? 'Not provided') ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Bar Membership No:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['bar_membership_no']) ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Admission Year:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['admission_year']) ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Practice Type:</div>
                            <div class="detail-value"><?= htmlspecialchars($lawyer_details['practice_type'] ?? 'Not specified') ?></div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Primary Courts:</div>
                            <div class="detail-value">
                                <?php if (!empty($lawyer_details['primary_courts']) && is_array($lawyer_details['primary_courts'])): ?>
                                    <div class="badge-list">
                                        <?php foreach ($lawyer_details['primary_courts'] as $court): ?>
                                            <span class="badge"><?= htmlspecialchars(trim($court)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Jurisdictions:</div>
                            <div class="detail-value">
                                <?php if (!empty($lawyer_details['geographical_jurisdictions']) && is_array($lawyer_details['geographical_jurisdictions'])): ?>
                                    <div class="badge-list">
                                        <?php foreach ($lawyer_details['geographical_jurisdictions'] as $jurisdiction): ?>
                                            <span class="badge"><?= htmlspecialchars(trim($jurisdiction)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    Not specified
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Profile Image:</div>
                            <div class="detail-value">
                                <?php if (!empty($lawyer_details['profile_image'])): ?>
                                    <div class="image-preview">
                                        <img src="<?= htmlspecialchars($lawyer_details['profile_image']) ?>" 
                                             alt="Profile Image" 
                                             style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6;">
                                        <br><small>
                                            <a href="<?= htmlspecialchars($lawyer_details['profile_image']) ?>" class="file-link" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> View Full Size
                                            </a>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Bar ID Document:</div>
                            <div class="detail-value">
                                <?php if (!empty($lawyer_details['bar_id_scan'])): ?>
                                    <?php
                                    $file_extension = pathinfo($lawyer_details['bar_id_scan'], PATHINFO_EXTENSION);
                                    $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                    ?>
                                    
                                    <?php if ($is_image): ?>
                                        <div class="document-preview">
                                            <img src="<?= htmlspecialchars($lawyer_details['bar_id_scan']) ?>" 
                                                 alt="Bar ID Document" 
                                                 style="max-width: 300px; max-height: 200px; border-radius: 8px; border: 2px solid #dee2e6;">
                                            <br><small>
                                                <a href="<?= htmlspecialchars($lawyer_details['bar_id_scan']) ?>" class="file-link" target="_blank">
                                                    <i class="fas fa-external-link-alt"></i> View Full Size
                                                </a>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-preview">
                                            <div class="pdf-placeholder" style="display: inline-block; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 2px solid #dee2e6; text-align: center;">
                                                <i class="fas fa-file-pdf fa-3x text-danger"></i>
                                                <br><strong>PDF Document</strong>
                                                <br><small><?= strtoupper($file_extension) ?> File</small>
                                            </div>
                                            <br><small>
                                                <a href="<?= htmlspecialchars($lawyer_details['bar_id_scan']) ?>" class="file-link" target="_blank">
                                                    <i class="fas fa-download"></i> Download & View Document
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Not provided</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Registration Date:</div>
                            <div class="detail-value">
                                <?= !empty($lawyer_details['registration_date']) ? date('M j, Y g:i A', strtotime($lawyer_details['registration_date'])) : 'Not available' ?>
                            </div>
                        </div>
                        
                        <div class="detail-row">
                            <div class="detail-label">Status:</div>
                            <div class="detail-value">
                                <span class="badge bg-<?= $lawyer_details['approved'] === 'pending' ? 'warning' : ($lawyer_details['approved'] === 'approved' ? 'success' : 'danger') ?>">
                                    <?= ucfirst($lawyer_details['approved']) ?>
                                </span>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-exclamation-circle"></i>
                            <p>Lawyer details not found</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth transitions to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('mouseenter', () => {
                    btn.style.transform = 'translateY(-2px)';
                });
                btn.addEventListener('mouseleave', () => {
                    btn.style.transform = 'translateY(0)';
                });
            });

            // Show modal if lawyer details are requested
            <?php if ($lawyer_details): ?>
                var lawyerModal = new bootstrap.Modal(document.getElementById('lawyerDetailsModal'));
                lawyerModal.show();
            <?php endif; ?>
        });

        // Function to view lawyer details
        function viewLawyerDetails(lawyerId) {
            window.location.href = 'admin_dashboard.php?view_lawyer=' + lawyerId;
        }
    </script>
</body>
</html>