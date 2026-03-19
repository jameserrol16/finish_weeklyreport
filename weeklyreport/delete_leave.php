<?php
session_name('admin_session');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$id     = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    // Only delete if it belongs to this user
    $stmt = $conn->prepare("DELETE FROM leave_applications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
}

header("Location: myreport.php");
exit;