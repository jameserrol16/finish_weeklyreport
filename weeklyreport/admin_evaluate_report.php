<?php
session_name('admin_session');
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$userId  = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$month   = isset($_GET['month'])   ? intval($_GET['month'])   : date('n');
$year    = isset($_GET['year'])    ? intval($_GET['year'])    : date('Y');

if (!$userId) { die("Invalid user."); }

// Handle save evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_eval'])) {
    $reportId   = intval($_POST['report_id']);
    $evalStatus  = $conn->real_escape_string($_POST['eval_status']);
    $evalRating  = $conn->real_escape_string($_POST['eval_rating']);
    $evalRemarks = $conn->real_escape_string($_POST['eval_remarks']);

    $upd = $conn->prepare("
        UPDATE weekly_reports
        SET eval_status = ?, eval_rating = ?, eval_remarks = ?, evaluated_at = NOW()
        WHERE id = ?
    ");
    $upd->bind_param("sssi", $evalStatus, $evalRating, $evalRemarks, $reportId);
    $upd->execute();

    // Redirect back to same page to avoid re-post
    header("Location: admin_evaluate_report.php?user_id=$userId&month=$month&year=$year&saved=1");
    exit;
}

// Fetch JO info
$joStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$joStmt->bind_param("i", $userId);
$joStmt->execute();
$joInfo = $joStmt->get_result()->fetch_assoc();
$joName = $joInfo ? $joInfo['full_name'] : 'Unknown';

// Fetch reports for that JO in the selected month/year
$repStmt = $conn->prepare("
    SELECT id, report_name, week_range, created_at, updated_at,
           eval_status, eval_rating, eval_remarks, evaluated_at
    FROM weekly_reports
    WHERE user_id = ?
      AND MONTH(created_at) = ?
      AND YEAR(created_at)  = ?
    ORDER BY created_at ASC
");
$repStmt->bind_param("iii", $userId, $month, $year);
$repStmt->execute();
$reports = $repStmt->get_result();

$months = ['','January','February','March','April','May','June',
           'July','August','September','October','November','December'];
$monthLabel = ($months[$month] ?? '') . ' ' . $year;

$saved = isset($_GET['saved']);

include "header.php";
?>

<div id="main" class="main-content">

    <!-- Breadcrumb -->
    <div class="breadcrumb">
        <a href="admin_monthly_summary.php?year=<?= $year ?>&month=<?= $month ?>">← Back to Monthly Summary</a>
    </div>

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-text">
            <h2>📝 Evaluate Reports — <?= htmlspecialchars($joName) ?></h2>
            <p><?= htmlspecialchars($monthLabel) ?> &nbsp;·&nbsp; Rate and add remarks for each weekly report</p>
        </div>
    </div>

    <?php if ($saved): ?>
    <div class="alert-success">✅ Evaluation saved successfully.</div>
    <?php endif; ?>

    <?php if ($reports->num_rows === 0): ?>
    <div class="report-card">
        <p class="no-reports">No reports found for <?= htmlspecialchars($joName) ?> in <?= htmlspecialchars($monthLabel) ?>.</p>
    </div>
    <?php else: ?>

    <!-- Legend -->
    <div class="legend-row">
        <span class="legend-item"><span class="dot approved"></span> Approved</span>
        <span class="legend-item"><span class="dot pending"></span> Pending</span>
        <span class="legend-item"><span class="dot returned"></span> Returned</span>
    </div>

    <?php $idx = 0; while ($row = $reports->fetch_assoc()): $idx++; ?>
    <div class="eval-card <?= strtolower($row['eval_status'] ?? 'pending') ?>-border" id="card-<?= $row['id'] ?>">

        <div class="eval-card-header">
            <div class="eval-card-title">
                <span class="report-num">Report #<?= $idx ?></span>
                <strong><?= htmlspecialchars($row['report_name']) ?></strong>
                <span class="week-range">📅 <?= htmlspecialchars($row['week_range']) ?></span>
            </div>
            <div class="eval-card-meta">
                <span class="created-date">Submitted: <?= date("M d, Y", strtotime($row['created_at'])) ?></span>
                <?php
                $status = $row['eval_status'] ?? 'Pending';
                $statusClass = strtolower($status);
                ?>
                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
            </div>
        </div>

        <form method="POST" class="eval-form">
            <input type="hidden" name="report_id"  value="<?= $row['id'] ?>">
            <input type="hidden" name="save_eval"  value="1">

            <div class="eval-fields">

                <!-- Rating -->
                <div class="field-group">
                    <label>⭐ Rating</label>
                    <div class="star-rating" id="stars-<?= $row['id'] ?>">
                        <?php
                        $currentRating = intval($row['eval_rating'] ?? 0);
                        for ($s = 1; $s <= 5; $s++):
                        ?>
                        <span class="star <?= $s <= $currentRating ? 'filled' : '' ?>"
                              data-val="<?= $s ?>"
                              onclick="setRating(<?= $row['id'] ?>, <?= $s ?>)">★</span>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="eval_rating" id="rating-val-<?= $row['id'] ?>" value="<?= $currentRating ?>">
                    <span class="rating-label" id="rating-label-<?= $row['id'] ?>">
                        <?= $currentRating > 0 ? ratingLabel($currentRating) : 'Not yet rated' ?>
                    </span>
                </div>

                <!-- Status -->
                <div class="field-group">
                    <label>📋 Evaluation Status</label>
                    <select name="eval_status" class="eval-select">
                        <option value="Pending"  <?= ($row['eval_status'] ?? 'Pending') === 'Pending'  ? 'selected' : '' ?>>⏳ Pending</option>
                        <option value="Approved" <?= ($row['eval_status'] ?? '')         === 'Approved' ? 'selected' : '' ?>>✅ Approved</option>
                        <option value="Returned" <?= ($row['eval_status'] ?? '')         === 'Returned' ? 'selected' : '' ?>>🔄 Returned</option>
                    </select>
                </div>

            </div>

            <!-- Remarks -->
            <div class="field-group full-width">
                <label>💬 Remarks / Feedback</label>
                <textarea name="eval_remarks" class="eval-textarea" rows="3"
                          placeholder="Write your evaluation remarks here..."><?= htmlspecialchars($row['eval_remarks'] ?? '') ?></textarea>
            </div>

            <?php if ($row['evaluated_at']): ?>
            <p class="last-evaluated">Last evaluated: <?= date("M d, Y h:i A", strtotime($row['evaluated_at'])) ?></p>
            <?php endif; ?>

            <div class="eval-actions">
                <button type="submit" class="btn-save">💾 Save Evaluation</button>
                <a href="admin_viewreport.php?id=<?= $row['id'] ?>" class="btn-view" target="_blank">👁 View Full Report</a>
            </div>
        </form>
    </div>
    <?php endwhile; ?>

    <?php endif; ?>

</div>

<?php
function ratingLabel($val) {
    $labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
    return $labels[$val] ?? '';
}
?>

<script>
const ratingLabels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];

function setRating(reportId, val) {
    document.getElementById('rating-val-' + reportId).value = val;
    document.getElementById('rating-label-' + reportId).textContent = ratingLabels[val] || '';

    const stars = document.querySelectorAll('#stars-' + reportId + ' .star');
    stars.forEach((s, i) => {
        s.classList.toggle('filled', i < val);
    });
}

// Hover preview
document.querySelectorAll('.star').forEach(star => {
    const container = star.closest('.star-rating');
    const id = container.id.replace('stars-', '');

    star.addEventListener('mouseenter', function () {
        const val = parseInt(this.dataset.val);
        container.querySelectorAll('.star').forEach((s, i) => {
            s.classList.toggle('hovered', i < val);
        });
    });
    star.addEventListener('mouseleave', function () {
        container.querySelectorAll('.star').forEach(s => s.classList.remove('hovered'));
    });
});
</script>

<style>
.main-content {
    margin-left: 250px; padding: 40px; min-height: 100vh;
    background: #f0f3f8; font-family: 'Segoe UI', Arial, sans-serif;
    transition: margin-left 0.3s ease;
}
.main-content.collapsed { margin-left: 70px; }

.breadcrumb { margin-bottom: 16px; }
.breadcrumb a { color: #003366; text-decoration: none; font-size: 13px; font-weight: 600; }
.breadcrumb a:hover { text-decoration: underline; }

.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0; font-size: 24px; color: #0d2a52; font-weight: 700; }
.page-header p  { margin: 4px 0 0; color: #5a6a80; font-size: 14px; }

.alert-success {
    background: #e6f7ef; color: #1a7a3c; border: 1px solid #b2dfcc;
    border-radius: 8px; padding: 12px 18px; margin-bottom: 20px;
    font-weight: 600; font-size: 14px;
}

/* Legend */
.legend-row { display: flex; gap: 16px; margin-bottom: 20px; flex-wrap: wrap; }
.legend-item { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #555; }
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot.approved { background: #27ae60; }
.dot.pending  { background: #f39c12; }
.dot.returned { background: #e74c3c; }

/* Eval Card */
.eval-card {
    background: #fff; border-radius: 10px; padding: 22px 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07); margin-bottom: 20px;
    border-left: 5px solid #ccc; transition: box-shadow 0.2s;
}
.eval-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.12); }
.eval-card.approved-border { border-left-color: #27ae60; }
.eval-card.pending-border  { border-left-color: #f39c12; }
.eval-card.returned-border { border-left-color: #e74c3c; }

.eval-card-header {
    display: flex; justify-content: space-between; align-items: flex-start;
    flex-wrap: wrap; gap: 10px; margin-bottom: 18px; padding-bottom: 14px;
    border-bottom: 1px solid #eef0f4;
}
.eval-card-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.report-num { background: #003366; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 10px; }
.eval-card-title strong { font-size: 15px; color: #0d2a52; }
.week-range { font-size: 13px; color: #7a8a9a; }
.eval-card-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.created-date { font-size: 12px; color: #999; }

/* Status badges */
.status-badge { padding: 3px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; }
.status-badge.approved { background: #e6f7ef; color: #1a7a3c; }
.status-badge.pending  { background: #fef3cd; color: #c87000; }
.status-badge.returned { background: #fde8e6; color: #c0392b; }

/* Form */
.eval-form { display: flex; flex-direction: column; gap: 16px; }
.eval-fields { display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; }
.field-group { display: flex; flex-direction: column; gap: 6px; }
.field-group.full-width { width: 100%; }
.field-group label { font-size: 13px; font-weight: 600; color: #444; }

/* Star Rating */
.star-rating { display: flex; gap: 4px; }
.star {
    font-size: 26px; color: #ccc; cursor: pointer;
    transition: color 0.15s, transform 0.1s;
    user-select: none;
}
.star.filled  { color: #f39c12; }
.star.hovered { color: #f5c842; transform: scale(1.15); }
.star:hover   { transform: scale(1.15); }
.rating-label { font-size: 12px; color: #888; font-style: italic; margin-top: 2px; }

/* Select */
.eval-select {
    padding: 8px 12px; border: 1px solid #ccd3dd; border-radius: 6px;
    font-size: 13px; background: #f8f9fb; color: #333;
    cursor: pointer; min-width: 170px;
}

/* Textarea */
.eval-textarea {
    width: 100%; padding: 10px 12px; border: 1px solid #ccd3dd;
    border-radius: 6px; font-size: 13px; font-family: inherit;
    background: #f8f9fb; color: #333; resize: vertical;
    transition: border-color 0.2s; box-sizing: border-box;
}
.eval-textarea:focus { border-color: #003366; outline: none; }

.last-evaluated { font-size: 12px; color: #aaa; margin: 0; }

/* Action Buttons */
.eval-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.btn-save {
    padding: 9px 22px; background: #003366; color: #fff; border: none;
    border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer;
    transition: background 0.2s;
}
.btn-save:hover { background: #002244; }
.btn-view {
    padding: 9px 18px; background: #f0f3f8; color: #003366; border: 1px solid #003366;
    border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;
    transition: background 0.2s;
}
.btn-view:hover { background: #dce8f8; }

.no-reports { text-align: center; color: #888; padding: 20px; font-size: 15px; }
.report-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }

/* Dark Mode */
body.dark .main-content       { background: #1a1a2e; color: #e0e0e0; }
body.dark .page-header h2     { color: #a0c4ff; }
body.dark .page-header p      { color: #8892a4; }
body.dark .eval-card          { background: #252540; border-color: #3a3a5a; }
body.dark .eval-card.approved-border { border-left-color: #2ecc71; }
body.dark .eval-card.pending-border  { border-left-color: #f39c12; }
body.dark .eval-card.returned-border { border-left-color: #e74c3c; }
body.dark .eval-card-header   { border-bottom-color: #3a3a5a; }
body.dark .eval-card-title strong { color: #a0c4ff; }
body.dark .field-group label  { color: #bbc; }
body.dark .eval-select,
body.dark .eval-textarea      { background: #1a1a2e; color: #ddd; border-color: #444; }
body.dark .btn-view           { background: #1a1a2e; color: #7eb5f5; border-color: #7eb5f5; }
body.dark .breadcrumb a       { color: #7eb5f5; }
body.dark .rating-label       { color: #778; }
body.dark .last-evaluated     { color: #556; }
body.dark .report-card        { background: #252540; }

@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 20px; }
    .eval-fields  { flex-direction: column; }
}
</style>

<?php include "footer.php"; ?>