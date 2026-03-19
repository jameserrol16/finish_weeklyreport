<?php
// Determine which session to use based on which portal is calling
$sessionName = $_POST['session_type'] ?? 'admin_session';
session_name($sessionName);
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

$userId = $_SESSION['user_id'];

// Sanitize helper
function s($key) {
    return isset($_POST[$key]) ? trim($_POST[$key]) : '';
}

$date_filed    = s('f_date');
$full_name     = trim(s('f_last') . ', ' . s('f_first') . ' ' . s('f_mid'));
$department    = s('f_dept');
$position      = s('f_pos');
$salary        = s('f_sal');
$leave_type    = s('leave_type');       // computed on client from checked boxes
$leave_details = s('leave_details');   // computed on client from 6B
$days_applied  = s('f_days');
$inclusive_dates = trim(s('f_dates1') . ' ' . s('f_dates2'));
$commutation   = s('commutation');     // "Not Requested" | "Requested"
$status        = 'Pending';

// Create table if it doesn't exist yet
$conn->query("CREATE TABLE IF NOT EXISTS leave_applications (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NOT NULL,
    full_name     VARCHAR(255),
    department    VARCHAR(255),
    position      VARCHAR(255),
    salary        VARCHAR(100),
    date_filed    VARCHAR(50),
    leave_type    VARCHAR(255),
    leave_details TEXT,
    days_applied  VARCHAR(50),
    inclusive_dates VARCHAR(255),
    commutation   VARCHAR(50),
    status        VARCHAR(50) DEFAULT 'Pending',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$stmt = $conn->prepare("INSERT INTO leave_applications
    (user_id, full_name, department, position, salary, date_filed,
     leave_type, leave_details, days_applied, inclusive_dates, commutation, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");

$stmt->bind_param("isssssssssss",
    $userId, $full_name, $department, $position, $salary, $date_filed,
    $leave_type, $leave_details, $days_applied, $inclusive_dates, $commutation, $status
);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Leave application saved successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}