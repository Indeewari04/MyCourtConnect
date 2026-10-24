<?php
session_start();
require_once __DIR__ . "/classes/Database.php";
require_once "client_register.php";
require_once "lawyer_register.php";
require_once "student_register.php";

// Initialize database and create objects with proper error handling
$db = new Database();

// Make sure we have a valid database connection before proceeding
$connection = $db->getConnection();

// Initialize registration classes with proper error handling
$client = new ClientRegister($connection);
$lawyer = new LawyerRegister($connection);  
$student = new StudentRegister($connection);

// Process form submissions if any
$client->handlePost();
$lawyer->handlePost();
$student->handlePost();

// Handle lawyer profile image safely
$lawyerProfileImage = '';
// Check if lawyer object exists and has fields property before accessing profile_image
if (isset($lawyer) && isset($lawyer->fields) && isset($lawyer->fields['profile_image']) && !empty($lawyer->fields['profile_image'])) {
  $lawyerProfileImage = $lawyer->fields['profile_image'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Register | MyCourtConnect</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
  <style>
    /* ...existing CSS... */
    body { min-height: 100vh; background: url('https://images.squarespace-cdn.com/content/v1/5a219a67ccc5c57d4d56d145/1679953047237-N2ZODZBSB3RC3Y00CL4H/Screen+Shot+2023-03-27+at+4.37.16+PM.png?format=1500w.jpg') no-repeat center center fixed; background-size: cover; font-family: 'Inter', Arial, sans-serif; display: flex; align-items: center; justify-content: center; }
    .register-card { background: rgba(255, 245, 235, 0.97); border-radius: 2.2rem; box-shadow: 0 12px 40px 0 rgba(120, 72, 15, 0.13), 0 2px 4px 0 rgba(180, 140, 90, 0.08); padding: 2.7rem 2.2rem 2.2rem 2.2rem; max-width: 700px; width: 100%; margin: 2rem 0; animation: fadeIn 1s; backdrop-filter: blur(2px); border: 1.5px solid #e6d3c2; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(30px);} to { opacity: 1; transform: translateY(0);} }
    .role-toggle { display: flex; gap: 1.2rem; justify-content: center; margin-bottom: 2.2rem; }
    .role-btn { padding: 0.8rem 2.5rem; border-radius: 999px; border: 0; background: #f7e7d7; font-weight: 700; font-size: 1.13rem; cursor: pointer; transition: background 0.2s, color 0.2s, box-shadow 0.2s, transform 0.2s; color: #7a4a1a; box-shadow: 0 2px 8px rgba(120, 72, 15, 0.04); letter-spacing: 0.02em; }
    .role-btn.active { background: linear-gradient(90deg,#c49b6e,#e6c3a1 80%); color: #fff; box-shadow: 0 6px 20px rgba(120, 72, 15, 0.13); transform: scale(1.07); border: 1.5px solid #b88a5a; }
    .form-section { display: none; }
    .form-section.active { display: block; }
    .form-label { font-weight: 700; color: #7a4a1a; letter-spacing: 0.01em; }
    .form-control, .form-select { border-radius: 1.1rem; background: #fdf6f0; border: 1.5px solid #e6d3c2; color: #7a4a1a; font-size: 1rem; padding: 0.8rem 1.1rem; margin-bottom: 1rem; transition: border-color 0.2s, box-shadow 0.2s; }
    .form-control:focus, .form-select:focus { border-color: #c49b6e; box-shadow: 0 0 0 2px rgba(196,155,110,0.13); background: #fff8f2; }
    .btn-gradient { background: linear-gradient(90deg, #b88a5a 0%, #e6c3a1 100%); color: #fff; font-weight: 700; border: none; border-radius: 2rem; padding: 0.8rem 2.2rem; font-size: 1.13rem; box-shadow: 0 4px 16px 0 rgba(120, 72, 15, 0.08); transition: background 0.2s, transform 0.2s; letter-spacing: 0.03em; }
    .btn-gradient:hover { background: linear-gradient(90deg, #e6c3a1 0%, #b88a5a 100%); transform: translateY(-2px) scale(1.04); color: #fff; }
    .divider { text-align: center; color: #b88a5a; margin: 2rem 0 1rem 0; font-size: 1rem; font-weight: 600; letter-spacing: 0.1em; }
    .alert { border-radius: 1rem; font-size: 1rem; border: 1.5px solid #e6d3c2; background: #fff8f2; color: #7a4a1a; }
    .alert-success { background: #f7e7d7; color: #7a4a1a; border-color: #c49b6e; }
    .alert-danger { background: #fff0e6; color: #a13d1a; border-color: #e6b3a1; }
    .form-check-label { font-weight: 500; margin-right: 1.2rem; color: #7a4a1a; }
    .form-check-input:checked { background-color: #b88a5a; border-color: #b88a5a; }
    .register-card h2 { font-size: 2.2rem; letter-spacing: 0.03em; margin-bottom: 1.5rem; background: linear-gradient(90deg, #b88a5a 0%, #e6c3a1 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .btn-link { color: #b88a5a !important; font-weight: 700; letter-spacing: 0.02em; text-decoration: underline; transition: color 0.2s; }
    .btn-link:hover { color: #7a4a1a !important; text-decoration: none; }
    .lawyer-profile-img { display: block; margin: 0 auto 1.2rem auto; width: 110px; height: 110px; object-fit: cover; border-radius: 50%; border: 3px solid #b88a5a; background: #fff; }
    @media (max-width: 700px) { .register-card { padding: 1.2rem 0.5rem; } .register-card h2 { font-size: 1.5rem; } }
  </style>
</head>
<body>
  <div class="register-card shadow-lg">
    <h2 class="mb-3 fw-bold text-center" style="background: linear-gradient(90deg, #0066FF 0%, #FF6B35 100%); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">
      <i class="fa-solid fa-user-plus"></i> Create Account
    </h2>
    <div class="role-toggle mb-4">
      <button class="role-btn active" id="clientBtn" type="button"><i class="fa fa-user"></i> Client</button>
      <button class="role-btn" id="lawyerBtn" type="button"><i class="fa fa-gavel"></i> Lawyer</button>
      <button class="role-btn" id="studentBtn" type="button"><i class="fa fa-graduation-cap"></i> Law Apprentice</button>
    </div>
    <!-- Lawyer Registration Form -->
    <div class="form-section" id="lawyerForm" style="position:relative;">
      <?php if ($lawyer->success): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($lawyer->success) ?></div>
      <?php elseif ($lawyer->errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($lawyer->errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <?php 
      // Fix the profile image display logic
      if (!empty($lawyerProfileImage) && file_exists($lawyerProfileImage)): ?>
        <img src="<?= htmlspecialchars($lawyerProfileImage) ?>" alt="Profile Image" class="lawyer-profile-img mb-3">
      <?php else: ?>
        <img src="https://cdn-icons-png.flaticon.com/512/3135/3135715.png" alt="Profile Image" class="lawyer-profile-img mb-3">
      <?php endif; ?>

      <?php if (!$lawyer->success): ?>
      <form method="POST" enctype="multipart/form-data" data-validate="true" autocomplete="off" onsubmit="return validateLawyerForm()">
        <input type="hidden" name="role" value="lawyer">
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required 
                   data-validate="required|name" placeholder="John Doe" 
                   value="<?= htmlspecialchars($lawyer->fields['full_name']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select" required data-validate="required">
              <option value="">Select Gender</option>
              <option value="Male" <?= ($lawyer->fields['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
              <option value="Female" <?= ($lawyer->fields['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
              <option value="Other" <?= ($lawyer->fields['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">NIC Number</label>
            <input type="text" name="nic" class="form-control" required 
                   data-validate="required|nic" placeholder="123456789V or 12345678901"
                   value="<?= htmlspecialchars($lawyer->fields['nic']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-control" required 
                   data-validate="required" value="<?= htmlspecialchars($lawyer->fields['dob']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <input type="tel" name="phone" class="form-control" required 
                   data-validate="required|phone" placeholder="0771234567"
                   value="<?= htmlspecialchars($lawyer->fields['phone']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required 
                   data-validate="required|email" data-realtime="true" placeholder="you@example.com"
                   value="<?= htmlspecialchars($lawyer->fields['email']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Sri Lanka Bar Association Membership Number</label>
            <input type="text" name="bar_membership_no" class="form-control" required 
                   data-validate="required|barNumber" placeholder="BAR12345"
                   value="<?= htmlspecialchars($lawyer->fields['bar_membership_no']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Scanned copy of Bar Association ID Card</label>
            <input type="file" name="bar_id_scan" class="form-control" accept="image/*,application/pdf" <?= $lawyer->success ? '' : 'required' ?>>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Profile Image</label>
            <input type="file" name="profile_image" class="form-control" accept="image/*" <?= $lawyer->success ? '' : 'required' ?>>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Year of Admission to the Bar</label>
            <input type="number" name="admission_year" class="form-control" min="1900" max="<?= date('Y') ?>" 
                   required data-validate="required|year" placeholder="e.g., 2015"
                   value="<?= htmlspecialchars($lawyer->fields['admission_year']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label class="form-label">Type of Practice</label>
            <div class="mb-2" id="practice-type-group">
              <!-- Row 1 -->
              <div class="row mb-2">
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="Criminal Law" id="practiceCriminal">
                    <label class="form-check-label" for="practiceCriminal">Criminal Law</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="Civil Law" id="practiceCivil">
                    <label class="form-check-label" for="practiceCivil">Civil Law</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="Business Law" id="practiceBusiness">
                    <label class="form-check-label" for="practiceBusiness">Business Law</label>
                  </div>
                </div>
              </div>
              <!-- Row 2 -->
              <div class="row mb-2">
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="Administrative Law" id="practiceAdministrative">
                    <label class="form-check-label" for="practiceAdministrative">Administrative Law</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="International Law" id="practiceInternational">
                    <label class="form-check-label" for="practiceInternational">International Law</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="IPR Law" id="practiceIPR">
                    <label class="form-check-label" for="practiceIPR">IPR Law</label>
                  </div>
                </div>
              </div>
              <!-- Row 3 - Other option -->
              <div class="row">
                <div class="col-md-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="practice_type[]" value="Other" id="practiceOther" onclick="toggleOtherPractice()">
                    <label class="form-check-label" for="practiceOther">Other</label>
                  </div>
                  <input type="text" name="other_practice_type" class="form-control mt-2" id="otherPracticeArea" placeholder="If Other, please specify">
                </div>
              </div>
            </div>
          </div>
          <!-- Removed JS for toggling 'Other' text area visibility since it is now always visible -->
        </div>
        <div class="mb-3">
          <label class="form-label">Primary Court of Practice <span class="text-danger">*</span></label>
          <div class="mb-2" id="primary-courts-group">
            <?php
            $courts = [
              "Supreme Court","Court of Appeal","High Court","District Court","Magistrate’s Court",
              "Labour Tribunal","Industrial Court","Commercial High Court"
            ];
            foreach ($courts as $c) {
              echo '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="primary_courts[]" value="'.htmlspecialchars($c).'"> <label class="form-check-label">'.htmlspecialchars($c).'</label></div>';
            }
            ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="primary_courts[]" value="Other"> <label class="form-check-label">Other</label>
            </div>
            <input type="text" name="other_court" class="form-control mt-2" placeholder="If Other, please specify">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferred Geographical Jurisdiction (Districts/Areas of Practice) <span class="text-danger">*</span></label>
          <div class="mb-2" id="jurisdictions-group" style="max-height:110px;overflow-y:auto;">
            <?php
            $districts = [
              "Colombo","Gampaha","Kalutara","Kandy","Matale","Nuwara Eliya","Galle","Matara","Hambantota",
              "Jaffna","Kilinochchi","Mannar","Vavuniya","Mullaitivu","Batticaloa","Ampara","Trincomalee",
              "Kurunegala","Puttalam","Anuradhapura","Polonnaruwa","Badulla","Monaragala","Ratnapura","Kegalle"
            ];
            foreach ($districts as $d) {
              echo '<div class="form-check form-check-inline" style="min-width:140px;"><input class="form-check-input" type="checkbox" name="jurisdictions[]" value="'.htmlspecialchars($d).'"> <label class="form-check-label">'.htmlspecialchars($d).'</label></div>';
            }
            ?>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="lawyer_password" class="form-label">Password</label>
              <input type="password" name="password" id="lawyer_password" class="form-control" 
                     minlength="6" required data-validate="required|password" data-strength="true" 
                     data-realtime="true" placeholder="At least 6 characters">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="lawyer_confirm_password" class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" id="lawyer_confirm_password" 
                     class="form-control" required data-validate="required|confirmPassword:#lawyer_password" 
                     data-realtime="true" placeholder="Re-enter password">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-gradient w-100 mt-2"><i class="fa-solid fa-user-check"></i> Register as Lawyer</button>
      </form>
      <?php endif; ?>
    </div>
    <!-- Client Registration Form -->
    <div class="form-section active" id="customerForm">
      <?php if ($client->success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($client->success) ?></div>
      <?php elseif ($client->errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($client->errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <?php if (!$client->success): ?>
      <form method="POST" data-validate="true" autocomplete="off">
        <input type="hidden" name="role" value="client">
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="full_name" class="form-label">Full Name</label>
              <input type="text" name="full_name" id="full_name" class="form-control" required 
                     data-validate="required|name" placeholder="John Doe" 
                     value="<?= htmlspecialchars($client->fields['full_name']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="gender" class="form-label">Gender</label>
              <select name="gender" id="gender" class="form-select" required data-validate="required">
                <option value="">Select Gender</option>
                <option value="Male" <?= ($client->fields['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($client->fields['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($client->fields['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="nic" class="form-label">NIC Number</label>
              <input type="text" name="nic" id="nic" class="form-control" required 
                     data-validate="required|nic" placeholder="123456789V or 12345678901" 
                     value="<?= htmlspecialchars($client->fields['nic']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="dob" class="form-label">Date of Birth</label>
              <input type="date" name="dob" id="dob" class="form-control" required 
                     data-validate="required" value="<?= htmlspecialchars($client->fields['dob']) ?>">
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="phone" class="form-label">Phone Number</label>
              <input type="tel" name="phone" id="phone" class="form-control" required 
                     data-validate="required|phone" placeholder="0771234567" 
                     value="<?= htmlspecialchars($client->fields['phone']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="email" class="form-label">Email Address</label>
              <input type="email" name="email" id="email" class="form-control" required 
                     data-validate="required|email" data-realtime="true" placeholder="you@example.com" 
                     value="<?= htmlspecialchars($client->fields['email']) ?>">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="address" class="form-label">Address</label>
            <input type="text" name="address" id="address" class="form-control" 
                   data-validate="required" placeholder="123 Main St, City" 
                   value="<?= htmlspecialchars($client->fields['address']) ?>">
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="city" class="form-label">City</label>
            <input type="text" name="city" id="city" class="form-control" 
                   data-validate="required|name" placeholder="Colombo" 
                   value="<?= htmlspecialchars($client->fields['city']) ?>">
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="password" class="form-label">Password</label>
              <input type="password" name="password" id="customer_password" class="form-control" 
                     required data-validate="required|password" data-strength="true" 
                     data-realtime="true" placeholder="At least 6 characters">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="confirm_password" class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" id="customer_confirm_password" 
                     class="form-control" required data-validate="required|confirmPassword:#customer_password" 
                     data-realtime="true" placeholder="Re-enter password">
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-gradient w-100 mt-2">
          <i class="fa-solid fa-user-check"></i> Register as Client
        </button>
      </form>
      <?php endif; ?>
    </div>
    <!-- Law Student Registration Form -->
    <div class="form-section" id="studentForm">
      <?php if ($student->success): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($student->success) ?></div>
      <?php elseif ($student->errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($student->errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <?php if (!$student->success): ?>
      <form method="POST" enctype="multipart/form-data" data-validate="true" autocomplete="off">
        <input type="hidden" name="role" value="student">
        
        <!-- Basic Information -->
        <h5 class="mb-3 text-primary"><i class="fa fa-user me-2"></i>Basic Information</h5>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_full_name" class="form-label">Full Name</label>
              <input type="text" name="full_name" id="student_full_name" class="form-control" 
                     required data-validate="required|name" placeholder="John Doe" 
                     value="<?= htmlspecialchars($student->fields['full_name']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_gender" class="form-label">Gender</label>
              <select name="gender" id="student_gender" class="form-select" required data-validate="required">
                <option value="">Select Gender</option>
                <option value="Male" <?= ($student->fields['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= ($student->fields['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                <option value="Other" <?= ($student->fields['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_nic" class="form-label">NIC Number</label>
              <input type="text" name="nic" id="student_nic" class="form-control" 
                     required data-validate="required|nic" placeholder="123456789V or 12345678901" 
                     value="<?= htmlspecialchars($student->fields['nic']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_dob" class="form-label">Date of Birth</label>
              <input type="date" name="dob" id="student_dob" class="form-control" 
                     required data-validate="required" value="<?= htmlspecialchars($student->fields['dob']) ?>">
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_phone" class="form-label">Phone Number</label>
              <input type="text" name="phone" id="student_phone" class="form-control" 
                     required data-validate="required|phone" placeholder="0771234567" 
                     value="<?= htmlspecialchars($student->fields['phone']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_email" class="form-label">Email Address</label>
              <input type="email" name="email" id="student_email" class="form-control" 
                     required data-validate="required|email" data-realtime="true" placeholder="john@email.com" 
                     value="<?= htmlspecialchars($student->fields['email']) ?>">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="student_address" class="form-label">Address</label>
            <textarea name="address" id="student_address" class="form-control" rows="2" 
                      required data-validate="required" placeholder="123 Main Street"><?= htmlspecialchars($student->fields['address']) ?></textarea>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="student_city" class="form-label">City</label>
            <input type="text" name="city" id="student_city" class="form-control" 
                   required data-validate="required|name" placeholder="Colombo" 
                   value="<?= htmlspecialchars($student->fields['city']) ?>">
          </div>
        </div>

        <!-- Academic Information -->
        <h5 class="mb-3 text-primary mt-4"><i class="fa fa-graduation-cap me-2"></i>Academic Information</h5>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_id" class="form-label">College ID</label>
              <input type="text" name="student_id" id="student_id" class="form-control" 
                     required data-validate="required" placeholder="STU2025001" 
                     value="<?= htmlspecialchars($student->fields['student_id']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="university" class="form-label">University</label>
              <input type="text" name="university" id="university" class="form-control" 
                     required data-validate="required|name" placeholder="University of Colombo" 
                     value="<?= htmlspecialchars($student->fields['university']) ?>">
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="faculty" class="form-label">Faculty</label>
              <input type="text" name="faculty" id="faculty" class="form-control" 
                     data-validate="name" placeholder="Faculty of Law" 
                     value="<?= htmlspecialchars($student->fields['faculty']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="year_of_study" class="form-label">Year of Study</label>
              <select name="year_of_study" id="year_of_study" class="form-select" required data-validate="required">
                <option value="">Select Year</option>
                <option value="1" <?= ($student->fields['year_of_study'] ?? '') === '1' ? 'selected' : '' ?>>1st Year</option>
                <option value="2" <?= ($student->fields['year_of_study'] ?? '') === '2' ? 'selected' : '' ?>>2nd Year</option>
                <option value="3" <?= ($student->fields['year_of_study'] ?? '') === '3' ? 'selected' : '' ?>>3rd Year</option>
                <option value="4" <?= ($student->fields['year_of_study'] ?? '') === '4' ? 'selected' : '' ?>>4th Year</option>
                <option value="5" <?= ($student->fields['year_of_study'] ?? '') === '5' ? 'selected' : '' ?>>5th Year</option>
              </select>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="degree_program" class="form-label">Degree Program</label>
              <input type="text" name="degree_program" id="degree_program" class="form-control" 
                     data-validate="name" placeholder="LL.B (Hons)" 
                     value="<?= htmlspecialchars($student->fields['degree_program']) ?>">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="expected_graduation_year" class="form-label">Expected Graduation Year</label>
              <input type="number" name="expected_graduation_year" id="expected_graduation_year" class="form-control" 
                     min="<?= date('Y') ?>" max="<?= date('Y') + 10 ?>" required data-validate="required|year" 
                     value="<?= htmlspecialchars($student->fields['expected_graduation_year']) ?>">
            </div>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="gpa" class="form-label">Current GPA (Optional)</label>
            <input type="number" name="gpa" id="gpa" class="form-control" step="0.01" min="0" max="4" 
                   placeholder="3.75" value="<?= htmlspecialchars($student->fields['gpa']) ?>">
          </div>
        </div>

        <!-- Documents -->
        <h5 class="mb-3 text-primary mt-4"><i class="fa fa-file me-2"></i>Documents</h5>
        <div class="mb-3">
          <div class="form-group">
            <label for="student_id_card" class="form-label">College ID Card</label>
            <input type="file" name="student_id_card" id="student_id_card" class="form-control" 
                   accept="image/*,application/pdf" required data-validate="required">
            <small class="form-text text-muted">Upload scanned copy of your college ID card (Image or PDF)</small>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="profile_image" class="form-label">Profile Image</label>
            <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
            <small class="form-text text-muted">Upload your profile photo (Optional)</small>
          </div>
        </div>

        <!-- Additional Information -->
        <h5 class="mb-3 text-primary mt-4"><i class="fa fa-info-circle me-2"></i>Additional Information</h5>
        <div class="mb-3">
          <div class="form-group">
            <label for="areas_of_interest" class="form-label">Areas of Legal Interest (Optional)</label>
            <textarea name="areas_of_interest" id="areas_of_interest" class="form-control" rows="2" 
                      placeholder="Corporate Law, Criminal Law, Human Rights, etc."><?= htmlspecialchars($student->fields['areas_of_interest']) ?></textarea>
          </div>
        </div>
        <div class="mb-3">
          <div class="form-group">
            <label for="academic_achievements" class="form-label">Academic Achievements (Optional)</label>
            <textarea name="academic_achievements" id="academic_achievements" class="form-control" rows="2" 
                      placeholder="Dean's List, Scholarships, Competitions, etc."><?= htmlspecialchars($student->fields['academic_achievements']) ?></textarea>
          </div>
        </div>

        <!-- Password Section -->
        
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_password" class="form-label">Password</label>
              <input type="password" name="password" id="student_password" class="form-control" 
                     required data-validate="required|password" data-strength="true" data-realtime="true" 
                     placeholder="At least 6 characters">
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="form-group">
              <label for="student_confirm_password" class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" id="student_confirm_password" class="form-control" 
                     required data-validate="required|confirmPassword:#student_password" data-realtime="true" 
                     placeholder="Re-enter password">
            </div>
          </div>
        </div>

       
        <script>
        // Show/hide textarea for 'Other' option
        const otherCheckbox = document.getElementById('otherPracticeCheckbox');
        const otherText = document.getElementById('otherPracticeText');
        if (otherCheckbox) {
            otherCheckbox.addEventListener('change', function() {
                otherText.style.display = this.checked ? 'block' : 'none';
            });
        }
        </script>

        <button type="submit" class="btn btn-gradient w-100 mt-2">
          <i class="fa-solid fa-graduation-cap"></i> Register as Law Student
        </button>
      </form>
      <?php endif; ?>
    </div>
    <div class="divider">Already have an account?</div>
    <div class="text-center">
      <a href="login.php" class="btn btn-link fw-bold" style="color:#0066FF;">Login</a>
    </div>
  </div>
  <script>
    // Role toggle logic
    const clientBtn = document.getElementById('clientBtn');
    const lawyerBtn = document.getElementById('lawyerBtn');
    const studentBtn = document.getElementById('studentBtn');
    const customerForm = document.getElementById('customerForm'); // This is the client form with ID 'customerForm'
    const lawyerForm = document.getElementById('lawyerForm');
    const studentForm = document.getElementById('studentForm');
    
    // Fix potential null references by checking elements exist before adding event listeners
    if (clientBtn && customerForm) {
      clientBtn.addEventListener('click', function() {
        clientBtn.classList.add('active');
        if (lawyerBtn) lawyerBtn.classList.remove('active');
        if (studentBtn) studentBtn.classList.remove('active');
        customerForm.classList.add('active');
        if (lawyerForm) lawyerForm.classList.remove('active');
        if (studentForm) studentForm.classList.remove('active');
      });
    }
    
    if (lawyerBtn && lawyerForm) {
      lawyerBtn.addEventListener('click', function() {
        lawyerBtn.classList.add('active');
        if (clientBtn) clientBtn.classList.remove('active');
        if (studentBtn) studentBtn.classList.remove('active');
        lawyerForm.classList.add('active');
        if (customerForm) customerForm.classList.remove('active');
        if (studentForm) studentForm.classList.remove('active');
      });
    }
    
    if (studentBtn && studentForm) {
      studentBtn.addEventListener('click', function() {
        studentBtn.classList.add('active');
        if (clientBtn) clientBtn.classList.remove('active');
        if (lawyerBtn) lawyerBtn.classList.remove('active');
        studentForm.classList.add('active');
        if (customerForm) customerForm.classList.remove('active');
        if (lawyerForm) lawyerForm.classList.remove('active');
      });
    }

    function validateClientForm() {
      const password = document.getElementById('customer_password').value;
      const confirm = document.getElementById('customer_confirm_password').value;
      const phone = document.getElementById('phone').value;
      if (password !== confirm) {
        alert("Passwords do not match.");
        return false;
      }
      if (!/^\d{10}$/.test(phone)) {
        alert("Phone number must be exactly 10 digits.");
        return false;
      }
      return true;
    }
    
    function validateLawyerForm() {
      const password = document.getElementById('lawyer_password').value;
      const confirm = document.getElementById('lawyer_confirm_password').value;
      
      if (password !== confirm) {
        alert("Passwords do not match.");
        return false;
      }
      
      // Check if at least one primary court is selected
      const courts = document.querySelectorAll('input[name="primary_courts[]"]:checked');
      if (courts.length === 0) {
        alert("Please select at least one Primary Court of Practice.");
        return false;
      }
      
      // Check if at least one jurisdiction is selected
      const jurisdictions = document.querySelectorAll('input[name="jurisdictions[]"]:checked');
      if (jurisdictions.length === 0) {
        alert("Please select at least one Geographical Jurisdiction.");
        return false;
      }
      
      return true;
    }
    
    function validateStudentForm() {
      const password = document.getElementById('student_password').value;
      const confirm = document.getElementById('student_confirm_password').value;
      const phone = document.getElementById('student_phone').value;
      const gpa = document.getElementById('gpa').value;
      
      if (password !== confirm) {
        alert("Passwords do not match.");
        return false;
      }
      
      if (!/^\d{10}$/.test(phone)) {
        alert("Phone number must be exactly 10 digits.");
        return false;
      }
      
      if (gpa && (parseFloat(gpa) < 0 || parseFloat(gpa) > 4.0)) {
        alert("GPA must be between 0.00 and 4.00.");
        return false;
      }
      
      return true;
    }
    
    // Add real-time validation for checkbox groups in lawyer form
    document.addEventListener('DOMContentLoaded', function() {
      // Primary Courts validation
      const primaryCourts = document.querySelectorAll('input[name="primary_courts[]"]');
      const primaryGroup = document.getElementById('primary-courts-group');
      
      primaryCourts.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          validateCheckboxGroup('primary_courts[]', 'primary-courts-group', 'Please select at least one Primary Court of Practice.');
        });
      });
      
      // Jurisdictions validation
      const jurisdictions = document.querySelectorAll('input[name="jurisdictions[]"]');
      const jurisdictionGroup = document.getElementById('jurisdictions-group');
      
      jurisdictions.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          validateCheckboxGroup('jurisdictions[]', 'jurisdictions-group', 'Please select at least one Geographical Jurisdiction.');
        });
      });
    });
    
    function validateCheckboxGroup(name, containerId, errorMessage) {
      const checkboxes = document.querySelectorAll(`input[name="${name}"]:checked`);
      const container = document.getElementById(containerId);
      let errorDiv = container.parentNode.querySelector('.checkbox-error-message');
      
      if (checkboxes.length === 0) {
        // Invalid - no checkboxes selected
        container.classList.remove('checkbox-group-valid');
        container.classList.add('checkbox-group-error');
        
        if (!errorDiv) {
          errorDiv = document.createElement('div');
          errorDiv.className = 'checkbox-error-message';
          errorDiv.textContent = errorMessage;
          container.parentNode.appendChild(errorDiv);
        }
      } else {
        // Valid - at least one checkbox selected
        container.classList.remove('checkbox-group-error');
        container.classList.add('checkbox-group-valid');
        
        if (errorDiv) {
          errorDiv.remove();
        }
      }
    }
  </script>
  <script src="js/form-validation.js"></script>
</body>
</html>