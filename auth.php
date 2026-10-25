<?php
// auth.php
require_once __DIR__ . "/classes/Database.php";

class Auth {
    private $db;
    public $error = "";
    public $success = "";

    public function __construct($db) {
        $this->db = $db->getConnection();
    }

    public function login($email, $password) {
        $email = strtolower(trim($email));

        // Check in users table for all user types
        $stmt = $this->db->prepare("SELECT id, full_name, email, password, role FROM users WHERE LOWER(email) = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row["password"])) {
                $_SESSION["user_id"] = $row["id"];
                $_SESSION["role"] = $row["role"];
                $_SESSION["name"] = $row["full_name"];
                
                // Check if lawyer needs approval
                if ($row["role"] === 'lawyer') {
                    $lawyer_stmt = $this->db->prepare("SELECT approved FROM lawyers WHERE id = ?");
                    $lawyer_stmt->bind_param("i", $row["id"]);
                    $lawyer_stmt->execute();
                    $lawyer_result = $lawyer_stmt->get_result();
                    if ($lawyer_result && $lawyer_result->num_rows === 1) {
                        $lawyer_row = $lawyer_result->fetch_assoc();
                        if ($lawyer_row["approved"] !== 'approved') {
                            $this->error = "Your account is pending approval. Please wait for administrator approval.";
                            return false;
                        }
                    }
                    $lawyer_stmt->close();
                }

                // Check if student needs verification
                if ($row["role"] === 'student') {
                    error_log("Checking student verification for ID: " . $row["id"]);
                    $student_stmt = $this->db->prepare("SELECT verification_status FROM students WHERE id = ?");
                    $student_stmt->bind_param("i", $row["id"]);
                    $student_stmt->execute();
                    $student_result = $student_stmt->get_result();
                    if ($student_result && $student_result->num_rows === 1) {
                        $student_row = $student_result->fetch_assoc();
                        error_log("Student verification status: " . $student_row["verification_status"]);
                        if ($student_row["verification_status"] === 'rejected') {
                            $this->error = "Your student account has been rejected. Please contact administrator.";
                            error_log("Student login blocked: Account rejected");
                            return false;
                        }
                        // Note: Allow pending students to login but show status in dashboard
                    } else {
                        error_log("WARNING: Student not found in students table, but allowing login");
                    }
                    $student_stmt->close();
                }
                
                // Redirect based on role
                error_log("Redirecting user role '" . $row["role"] . "' to appropriate dashboard");
                switch($row["role"]) {
                    case 'admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'client':
                        header("Location: client_dashboard.php");
                        break;
                    case 'lawyer':
                        header("Location: lawyer_dashboard.php");
                        break;
                    case 'student':
                        error_log("Redirecting to student_dashboard.php");
                        header("Location: student_dashboard.php");
                        break;
                }
                exit;
            } else {
                $this->error = "Invalid password.";
                return false;
            }
        } else {
            $this->error = "No account found with that email.";
            return false;
        }
        
        return false;
    }
}
?>