<?php
session_name('jo_session');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $reportId = intval($_GET['id']);

    // Delete the report if it belongs to the user
    $stmt = $conn->prepare("DELETE FROM weekly_reports WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $reportId, $userId);
    $stmt->execute();

    // Reset IDs sequentially (optional, if no other tables depend on id)
    $conn->query("SET @count = 0;");
    $conn->query("UPDATE weekly_reports SET id = (@count:=@count+1) ORDER BY id;");

    // Reset auto_increment to next value
    $conn->query("ALTER TABLE weekly_reports AUTO_INCREMENT = 1;");

    header("Location: jo_vreport.php");
    exit;
}
?>