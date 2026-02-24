<?php
session_name('admin_session');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'];

$stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $id, $_SESSION['user_id']);
$stmt->execute();
$report = $stmt->get_result()->fetch_assoc();

if (!$report) {
    die("Report not found.");
}

include "header.php";
?>

<h2>Edit Report</h2>

<form method="POST" action="update_report.php">
    <input type="hidden" name="id" value="<?= $report['id'] ?>">

    <label>Week Range:</label>
    <input type="text" name="week_range" value="<?= htmlspecialchars($report['week_range']) ?>">

    <label>Content:</label>
    <textarea name="content" rows="10"><?= htmlspecialchars($report['content']) ?></textarea>

    <button type="submit">💾 Save Changes</button>
</form>

<?php include "footer.php"; ?>