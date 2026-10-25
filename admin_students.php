<?php
// admin_students.php
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
    if (isset($_POST['action']) && isset($_POST['student_id'])) {
        $student_id = intval($_POST['student_id']);
        $action = $_POST['action'];
        
        if ($action === 'delete') {
            // Delete student
            $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
            $stmt->bind_param("i", $student_id);
            
            if ($stmt->execute()) {
                // Also delete from users table
                $user_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
                $user_stmt->bind_param("i", $student_id);
                $user_stmt->execute();
                $user_stmt->close();
                
                $message = "Student deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting student: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        } 
        elseif ($action === 'update_status') {
            $new_status = $_POST['status'];
            
            // Get student details
            $student_stmt = $conn->prepare("SELECT u.full_name, u.email FROM users u JOIN students s ON u.id = s.id WHERE s.id = ?");
            $student_stmt->bind_param("i", $student_id);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            $student_data = $student_result->fetch_assoc();
            $student_stmt->close();
            
            $stmt = $conn->prepare("UPDATE students SET verification_status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $student_id);
            
            if ($stmt->execute()) {
                $message = "Student verification status updated to " . ucfirst($new_status) . "!";
                $message_type = "success";
                
                // You could add email notification here similar to lawyers
                // Send notification email to student about status change
                
            } else {
                $message = "Error updating student status: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
        elseif ($action === 'update_profile') {
            // Update student profile
            $university = $_POST['university'] ?? '';
            $faculty = $_POST['faculty'] ?? '';
            $degree_program = $_POST['degree_program'] ?? '';
            $year_of_study = $_POST['year_of_study'] ?? '';
            $gpa = floatval($_POST['gpa'] ?? 0);
            
            $stmt = $conn->prepare("UPDATE students SET university = ?, faculty = ?, degree_program = ?, year_of_study = ?, gpa = ? WHERE id = ?");
            $stmt->bind_param("ssssdi", $university, $faculty, $degree_program, $year_of_study, $gpa, $student_id);
            
            if ($stmt->execute()) {
                $message = "Student profile updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating student profile: " . $conn->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    }
}

// Get all students with their user information
$students_query = "
    SELECT 
        u.id, 
        u.full_name, 
        u.email, 
        u.created_at as registration_date,
        s.phone, 
        s.university, 
        s.faculty, 
        s.degree_program, 
        s.year_of_study, 
        s.expected_graduation_year,
        s.gpa, 
        s.areas_of_interest,
        s.verification_status,
        s.created_at as profile_created
    FROM users u 
    LEFT JOIN students s ON u.id = s.id 
    WHERE u.role = 'student' 
    ORDER BY u.created_at DESC
";

$students_result = $conn->query($students_query);

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN s.verification_status = 'approved' THEN 1 ELSE 0 END) as approved_students,
        SUM(CASE WHEN s.verification_status = 'pending' THEN 1 ELSE 0 END) as pending_students,
        SUM(CASE WHEN s.verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected_students
    FROM users u 
    LEFT JOIN students s ON u.id = s.id 
    WHERE u.role = 'student'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - MyCourtConnect Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-hover: #6d28d9;
            --secondary: #94a3b8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0ea5e9;
            --dark-bg: #0f172a;
            --dark-card: #1e293b;
            --dark-border: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --sidebar-width: 280px;
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
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark-card);
            color: var(--text-primary);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            border-right: 1px solid var(--dark-border);
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--dark-border);
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 8px;
            transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active {
            background-color: var(--primary);
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 0.75rem;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 0;
        }

        .header {
            background: var(--dark-card);
            padding: 1rem 2rem;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--dark-border);
        }

        .header h4 {
            margin: 0;
            color: var(--text-primary);
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content {
            padding: 2rem;
        }

        /* Cards */
        .card {
            border: 1px solid var(--dark-border);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 2rem;
            background: var(--dark-card);
            color: var(--text-primary);
        }

        .card-header {
            background: var(--dark-card);
            color: var(--text-primary);
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--dark-border);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--dark-card);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--dark-border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .stat-card p {
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Table Styles */
        .table-responsive {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            background: var(--dark-card);
        }

        .table {
            margin-bottom: 0;
            color: var(--text-primary);
        }

        .table thead {
            background: var(--dark-card);
            color: var(--text-primary);
        }

        .table thead th {
            border: none;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--dark-border);
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--dark-border);
            background: var(--dark-card);
        }

        .table tbody tr:hover {
            background-color: rgba(124, 58, 237, 0.1);
        }

        /* Badges */
        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        /* Modal Styles */
        .modal-content {
            background: var(--dark-card);
            color: var(--text-primary);
            border: 1px solid var(--dark-border);
        }

        .modal-header {
            background: var(--dark-card);
            color: var(--text-primary);
            border-bottom: 1px solid var(--dark-border);
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .form-control {
            background: var(--dark-bg);
            border: 1px solid var(--dark-border);
            color: var(--text-primary);
        }

        .form-control:focus {
            background: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
            box-shadow: 0 0 0 0.2rem rgba(124, 58, 237, 0.25);
        }

        .form-select {
            background: var(--dark-bg);
            border: 1px solid var(--dark-border);
            color: var(--text-primary);
        }

        .form-select:focus {
            background: var(--dark-bg);
            border-color: var(--primary);
            color: var(--text-primary);
        }

        /* Search and filter area */
        .search-filter-area {
            background: var(--dark-card);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid var(--dark-border);
        }

        /* Avatar circles for names */
        .name-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            margin-right: 10px;
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.active {
                transform: translateX(0);
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
                    <a href="admin_students.php" class="nav-link active">
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
                <h4><i class="fas fa-graduation-cap"></i> Student Management</h4>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                    </div>
                    <span>Welcome, <?= htmlspecialchars($_SESSION['name']) ?></span>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'danger' ?> animate-fade-in">
                        <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i> 
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-graduation-cap text-primary"></i>
                        <h3 class="text-primary"><?= $stats['total_students'] ?? 0 ?></h3>
                        <p>Total Students</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-check-circle text-success"></i>
                        <h3 class="text-success"><?= $stats['approved_students'] ?? 0 ?></h3>
                        <p>Approved Students</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-clock text-warning"></i>
                        <h3 class="text-warning"><?= $stats['pending_students'] ?? 0 ?></h3>
                        <p>Pending Verification</p>
                    </div>
                </div>

                <!-- Students Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list"></i> Student Management
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter Area -->
                        <div class="search-filter-area">
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" id="searchStudents" placeholder="Search students...">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" id="statusFilter">
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary" onclick="applyFilters()">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                        <button class="btn btn-secondary" onclick="resetFilters()">
                                            <i class="fas fa-refresh"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Total count -->
                        <div class="d-flex justify-content-end mb-3">
                            <span class="badge bg-primary fs-6">
                                <span id="totalCount"><?= $stats['total_students'] ?? 0 ?></span> Total
                            </span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>University</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($students_result && $students_result->num_rows > 0): ?>
                                        <?php while ($student = $students_result->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="name-avatar" style="background: var(--primary);">
                                                            <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($student['degree_program'] ?? 'Student') ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($student['email']) ?></td>
                                                <td><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($student['university'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php
                                                    $status = $student['verification_status'] ?? 'pending';
                                                    $badge_class = [
                                                        'approved' => 'success',
                                                        'pending' => 'warning', 
                                                        'rejected' => 'danger'
                                                    ][$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?= $badge_class ?>"><?= ucfirst($status) ?></span>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, Y', strtotime($student['registration_date'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" onclick="viewStudent(<?= $student['id'] ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" onclick="editStudent(<?= $student['id'] ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteStudent(<?= $student['id'] ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-5">
                                                <div class="text-center py-4">
                                                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                                    <h5>No students found</h5>
                                                    <p class="text-muted">No students are currently available</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Edit Student Profile
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editStudentForm" method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">University</label>
                                    <input type="text" class="form-control" name="university" id="edit_university">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Faculty</label>
                                    <input type="text" class="form-control" name="faculty" id="edit_faculty">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Degree Program</label>
                                    <input type="text" class="form-control" name="degree_program" id="edit_degree_program">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Year of Study</label>
                                    <select class="form-control" name="year_of_study" id="edit_year_of_study">
                                        <option value="">Select Year</option>
                                        <option value="1">1st Year</option>
                                        <option value="2">2nd Year</option>
                                        <option value="3">3rd Year</option>
                                        <option value="4">4th Year</option>
                                        <option value="5">5th Year</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">GPA</label>
                                    <input type="number" step="0.01" min="0" max="4" class="form-control" name="gpa" id="edit_gpa">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Status Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-check"></i> Change Verification Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="statusForm" method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="student_id" id="status_student_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Verification Status</label>
                            <select class="form-control" name="status" id="status_select" required>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Changing status will affect the student's access to certain features.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editStudent(studentId) {
            // Get student data via AJAX or from the table
            const row = event.target.closest('tr');
            const cells = row.cells;
            
            document.getElementById('edit_student_id').value = studentId;
            
            // You can populate the form with existing data here
            // For now, we'll open the modal empty
            
            const modal = new bootstrap.Modal(document.getElementById('editStudentModal'));
            modal.show();
        }

        function changeStatus(studentId) {
            document.getElementById('status_student_id').value = studentId;
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        function deleteStudent(studentId, studentName) {
            if (confirm(`Are you sure you want to delete student "${studentName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'student_id';
                idInput.value = studentId;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Search and Filter Functions
        function applyFilters() {
            const search = document.getElementById('searchStudents').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const name = row.cells[0]?.textContent.toLowerCase() || '';
                const email = row.cells[1]?.textContent.toLowerCase() || '';
                const statusText = row.cells[4]?.textContent.toLowerCase() || '';
                
                const matchesSearch = search === '' || name.includes(search) || email.includes(search);
                const matchesStatus = status === '' || statusText.includes(status);
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('totalCount').textContent = visibleCount;
        }
        
        function resetFilters() {
            document.getElementById('searchStudents').value = '';
            document.getElementById('statusFilter').value = '';
            applyFilters();
        }
        
        // Real-time search
        document.getElementById('searchStudents').addEventListener('input', applyFilters);
        document.getElementById('statusFilter').addEventListener('change', applyFilters);

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>