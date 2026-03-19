<?php
session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$activeNowJO = 0;
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE role = 'jo' 
      AND last_activity IS NOT NULL
      AND last_activity >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
");
if ($result) {
    $row = $result->fetch_assoc();
    $activeNowJO = (int) $row['total'];
}

// Fetch the actual online users
$onlineUsers = [];
$result2 = $conn->query("
    SELECT COALESCE(full_name, username) AS name
    FROM users 
    WHERE role = 'jo' 
      AND last_activity IS NOT NULL
      AND last_activity >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ORDER BY last_activity DESC
");
if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $onlineUsers[] = $row['name'];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'activeNowJO' => $activeNowJO,
    'onlineUsers' => $onlineUsers
]);