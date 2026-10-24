<?php
session_start();
require_once __DIR__ . "/classes/Database.php";
require_once "auth.php";

$db = new Database();
$auth = new Auth($db);

$login_error = "";
$login_success = "";

// Check for registration success message
if (isset($_SESSION['registration_success'])) {
    $login_success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Clear the message after displaying
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    error_log("Login attempt for email: $email");
    
    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password.";
        error_log("Login failed: Empty email or password");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = "Please enter a valid email address.";
        error_log("Login failed: Invalid email format");
    } else {
        // Auth::login() redirects on success, so no need for $login_success
        error_log("Attempting authentication for: $email");
        $result = $auth->login($email, $password);
        if (!$result) {
            $login_error = $auth->error;
            error_log("Login failed: " . $auth->error);
        } else {
            error_log("Login successful, should redirect");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login | MyCourtConnect</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-image: url('https://i.pinimg.com/736x/83/4c/66/834c66cd6b7b7b0b7213073ffa5681c2.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Segoe UI', sans-serif;
    }
    .login-card {
      background: rgba(0, 0, 0, 0.8);
      color: #fff;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
      width: 100%;
      max-width: 420px;
      animation: fadeIn 0.9s ease-in-out;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .form-control {
      border-radius: 12px;
      background-color: #1f1f1f;
      border: none;
      color: #fff;
    }
    .form-control::placeholder { color: #aaa; }
    .btn-dark-blue {
      background: linear-gradient(to right, #007bff, #0056b3);
      border: none;
      color: #fff;
      border-radius: 50px;
      padding: 10px;
      font-weight: bold;
      transition: 0.3s;
    }
    .btn-dark-blue:hover {
      background: linear-gradient(to right, #0056b3, #007bff);
    }
    .link-blue { color: #0d6efd; font-weight: 600; }
    .link-blue:hover { text-decoration: underline; }
    .error-message {
      color: #ff4d4d;
      font-weight: 600;
      margin-top: 15px;
      text-align: center;
      background: rgba(255, 77, 77, 0.1);
      padding: 10px;
      border-radius: 8px;
      border-left: 4px solid #ff4d4d;
    }
    .success-message {
      color: #28a745;
      font-weight: 600;
      margin-top: 15px;
      text-align: center;
      background: rgba(40, 167, 69, 0.1);
      padding: 10px;
      border-radius: 8px;
      border-left: 4px solid #28a745;
    }
    .position-relative .fa-eye {
      position: absolute;
      top: 38px;
      right: 16px;
      cursor: pointer;
      color: #ccc;
    }
  </style>
</head>
<body>
  <div class="login-card">
    <h3 class="text-center mb-4">🔐 Login to MyCourtConnect</h3>
    
    <?php if (!empty($login_error)): ?>
      <div class="error-message mb-3"><?= htmlspecialchars($login_error) ?></div>
    <?php endif; ?>
    
    <?php if (!empty($login_success)): ?>
      <div class="success-message mb-3"><?= htmlspecialchars($login_success) ?></div>
    <?php endif; ?>
    
    <form method="POST" data-validate="true">
      <div class="mb-3">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" id="email" 
                 data-validate="required|email" data-realtime="true"
                 placeholder="you@example.com" required 
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
      </div>
      <div class="mb-3 position-relative">
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" id="password" 
                 data-validate="required|password" placeholder="Enter your password" required>
          <i class="fa fa-eye" id="togglePassword" onclick="togglePasswordVisibility()"></i>
        </div>
      </div>
      <button type="submit" class="btn btn-dark-blue w-100">Login</button>
    </form>

    <div id="clientError" class="error-message mt-3" style="display: none;"></div>

    <div class="text-center mt-3">
      <p>Don't have an account? <a href="register.php" class="link-blue">Register here</a></p>
      <p><a href="index.php" class="link-blue">← Back to Home</a></p>
    </div>
  </div>

  <script src="js/form-validation.js"></script>
  <script>
    function togglePasswordVisibility() {
      const pw = document.getElementById("password");
      const icon = document.getElementById("togglePassword");
      const type = pw.type === "password" ? "text" : "password";
      pw.type = type;
      icon.classList.toggle("fa-eye");
      icon.classList.toggle("fa-eye-slash");
    }
  </script>
</body>
</html>