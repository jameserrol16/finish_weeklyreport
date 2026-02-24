<?php
session_start();
include "db.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid report ID.");
}

$id = intval($_GET['id']);

// Fetch the report info along with JO name
$stmt = $conn->prepare("
    SELECT w.*, u.full_name 
    FROM weekly_reports w
    JOIN users u ON w.user_id = u.id
    WHERE w.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Report not found.");
}

include 'header.php';
?>

<div id="main" class="main page">
    <h2>Report Details</h2>

    <p><strong>JO:</strong> <?= htmlspecialchars($report['full_name']) ?></p>
    <p><strong>Report Name:</strong> <?= htmlspecialchars($report['report_name']) ?></p>
    <p><strong>Week:</strong> <?= htmlspecialchars($report['week_range']) ?></p>
    <p><strong>Created:</strong> <?= date("M d, Y H:i", strtotime($report['created_at'])) ?></p>
    <p><strong>Updated:</strong> <?= date("M d, Y H:i", strtotime($report['updated_at'])) ?></p>
</div>

<?php include 'footer.php'; ?>
