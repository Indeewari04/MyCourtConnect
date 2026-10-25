<?php
// Add a new apprenticeship request for testing
require_once 'classes/Database.php';
$db = new Database();
$conn = $db->getConnection();

echo "=== Adding New Test Apprenticeship Request ===\n";

// Get student and lawyer IDs
$student_id = 31; // oshan
$lawyer_id = 25;  // sarangi Umeshika
$message = "I am very interested in learning family law and would appreciate the opportunity to work as your apprentice.";

$stmt = $conn->prepare("INSERT INTO apprenticeship_requests (student_id, lawyer_id, message, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
$stmt->bind_param("iis", $student_id, $lawyer_id, $message);

if ($stmt->execute()) {
    echo "✓ New apprenticeship request added successfully!\n";
    $new_request_id = $conn->insert_id;
    echo "Request ID: $new_request_id\n";
    
    // Get details
    $details_stmt = $conn->prepare("SELECT ar.*, s.full_name as student_name, l.full_name as lawyer_name 
                                    FROM apprenticeship_requests ar 
                                    JOIN users s ON ar.student_id = s.id 
                                    JOIN users l ON ar.lawyer_id = l.id 
                                    WHERE ar.id = ?");
    $details_stmt->bind_param("i", $new_request_id);
    $details_stmt->execute();
    $details_result = $details_stmt->get_result();
    $details = $details_result->fetch_assoc();
    $details_stmt->close();
    
    echo "Student: {$details['student_name']}\n";
    echo "Lawyer: {$details['lawyer_name']}\n";
    echo "Status: {$details['status']}\n";
    echo "Created: {$details['created_at']}\n";
} else {
    echo "✗ Failed to add request: " . $stmt->error . "\n";
}
$stmt->close();

echo "\nNow you can test the approval/rejection workflow!\n";
echo "1. Login as lawyer (sarangi Umeshika, ID: 25)\n";
echo "2. Go to lawyer dashboard\n";
echo "3. You should see the pending request\n";
echo "4. Approve or reject it\n";
echo "5. Check student dashboard to see updated status\n";
?>