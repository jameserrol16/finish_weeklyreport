<?php
session_name('jo_session');
session_start();
require "db.php";

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$readonly = $isAdmin || (isset($_GET['readonly']) && $_GET['readonly'] == 1);

$editingReport = null;
$editingContent = [];

// Only fetch report if an ID was provided
if ($reportId > 0) {
    if ($isAdmin) {
        $stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ?");
        $stmt->bind_param("i", $reportId);
    } else {
        $stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $reportId, $userId);
    }

    $stmt->execute();
    $editingReport = $stmt->get_result()->fetch_assoc();

    if (!$editingReport) {
        die("Report not found or you do not have access.");
    }

    $editingContent = json_decode($editingReport['content'], true) ?? [];
}

// Handle POST save
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_report'
) {
    $reportId    = $_POST['id'] ?? '';
    $reportName  = trim($_POST['report_name'] ?? 'Untitled Report');
    $weekRange   = trim($_POST['week_range'] ?? '');
    $employee    = trim($_POST['employee'] ?? '');
    $division    = trim($_POST['division'] ?? '');
    $position    = trim($_POST['position'] ?? '');
    $branch      = trim($_POST['branch'] ?? '');
    $workTask    = trim($_POST['work_task'] ?? '');
    $accomplishments = json_decode($_POST['accomplishments'] ?? '[]', true);

    $contentJson = json_encode([
        'employee'        => $employee,
        'division'        => $division,
        'position'        => $position,
        'branch'          => $branch,
        'work_task'       => $workTask,
        'accomplishments' => $accomplishments
    ]);

    if (!empty($reportId)) {
        $stmt = $conn->prepare("
            UPDATE weekly_reports
            SET report_name=?, week_range=?, content=?, updated_at=NOW()
            WHERE id=? AND user_id=?
        ");
        $stmt->bind_param("sssii", $reportName, $weekRange, $contentJson, $reportId, $userId);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO weekly_reports
            (user_id, report_name, week_range, content, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'draft', NOW(), NOW())
        ");
        $stmt->bind_param("isss", $userId, $reportName, $weekRange, $contentJson);
        $stmt->execute();
        echo $stmt->insert_id;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Weekly Accomplishment Report</title>
<link rel="stylesheet" href="/weeklyreport/style.css?v=<?= filemtime('style.css') ?>">
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<style>
  .main { margin-left: 250px; padding: 30px; transition: margin-left 0.3s ease; }
  .main.collapsed { margin-left: 70px; }
  body { margin: 0; padding: 0; }
  .page { margin: 0 auto; }
</style>
<body>
    <?php include "jo_header.php"; ?>
<div class="page" id="reportPage">
  <form id="reportForm" method="POST" onsubmit="return validateReportForm()">
<input type="hidden" name="report_name" 
       value="<?= htmlspecialchars($editingReport['report_name'] ?? '') ?>">
    <!-- Hidden input for editing -->
    <input type="hidden" name="id" value="<?= $editingReport['id'] ?? '' ?>">

    <!-- REPORT NAME MODAL -->
    <div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"></div>

<div id="reportNamePrompt" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; z-index:1001;">
    <h3>Enter Report Name</h3>
    <input type="text" id="newReportName" placeholder="Report Name">
    <button type="button" onclick="confirmReportName()">Save</button>
</div>
    <!-- TOP BUTTONS -->
    <div class="top-right">
<?php if (!$readonly): ?>
  <button type="button" onclick="saveReport(event)">💾 Save</button>
  <button type="button" onclick="togglePreview()" id="previewBtn">👁 Preview</button>
<?php endif; ?>
  <button type="button" onclick="exportPDF()">📄 Export to PDF</button>
</div>


  <!-- LETTERHEAD -->
  <div class="letterhead">
    <img src="ntc-logo.png">
    <div class="letterhead-text">
      <strong>REPUBLIC OF THE PHILIPPINES</strong><br>
      <strong>NATIONAL TELECOMMUNICATIONS COMMISSION</strong><br>
      NTC Building, Sen. Miriam P. Defensor-Santiago Avenue<br>
      Brgy. Pinyahan, Diliman, Quezon City 1100<br>
      Email: ntc@ntc.gov.ph | https://www.ntc.gov.ph
    </div>
    <img src="bagong-pilipinas.jfif">
  </div>

  <hr>

  <h1>WEEKLY ACCOMPLISHMENT REPORT</h1>
  <div class="date-range">
  <input name="week_range" placeholder="Date Range" style="text-align:center;"
       value="<?= htmlspecialchars($editingReport['week_range'] ?? '') ?>">
</div>
<br><br>

  <table>
    <tr>
  <td class="label">Employee :</td>
  <td><input name="employee" value="<?= htmlspecialchars($editingContent['employee'] ?? '') ?>" <?= $readonly ? 'readonly' : '' ?>></td>
 <td class="label">Division :</td>
  <td><input name="division" value="<?= htmlspecialchars($editingContent['division'] ?? '') ?>" <?= $readonly ? 'readonly' : '' ?>></td>
</tr>
<tr>
  <td class="label">Position :</td>
  <td><input name="position" value="<?= htmlspecialchars($editingContent['position'] ?? '') ?>" <?= $readonly ? 'readonly' : '' ?>></td>
 <td class="label">Branch :</td>
  <td><input name="branch" value="<?= htmlspecialchars($editingContent['branch'] ?? '') ?>" <?= $readonly ? 'readonly' : '' ?>></td>
</tr>
  </table>

  <br>

  <table>
    <tr><td class="label">Work Task:</td></tr>
    <tr><td><textarea name="work_task" <?= $readonly ? 'readonly' : '' ?>><?= htmlspecialchars($editingContent['work_task'] ?? '') ?></textarea></td></tr>
  </table>

  <br>

  <!-- ACCOMPLISHMENT TABLE -->
  <table id="accomplishmentTable">
    <thead>
      <tr id="headerRow">
        <th>Day (Onsite/WFH)
           <?php if (!$readonly): ?>
  <div class="col-controls">
    <div class="control-btn" onclick="addColumn(this)">+</div>
    <div class="control-btn" onclick="removeColumn(this)">−</div>
  </div>
  <?php endif; ?>
        </th>
        <th>Accomplishment
           <?php if (!$readonly): ?>
  <div class="col-controls">
    <div class="control-btn" onclick="addColumn(this)">+</div>
    <div class="control-btn" onclick="removeColumn(this)">−</div>
  </div>
  <?php endif; ?>
        </th>
        <th>Description
           <?php if (!$readonly): ?>
  <div class="col-controls">
    <div class="control-btn" onclick="addColumn(this)">+</div>
    <div class="control-btn" onclick="removeColumn(this)">−</div>
  </div>
  <?php endif; ?>
        </th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>

<div class="table-actions">
<?php if (!$readonly): ?>
  <button type="button" onclick="addRow()">➕ Add Row</button>
  <button type="button" onclick="removeLastRow()">➖ Remove Last Row</button>
<?php endif; ?>
</div>


  <br>

  <!-- SIGNATURES -->
  <table class="signature-table">
    <tr>
      <td class="center signature">
  <strong>Submitted by:</strong><br><br>

  <div class="sig-container" style="position: relative; min-height: 90px; overflow: visible;">
    <input type="file" accept="image/*" class="signature-upload" onchange="loadSignature(this)" <?= $readonly ? 'disabled' : '' ?>>
    <div class="sig-box" style="display:none;">
      <span class="sig-delete">✖</span>
      <img class="signature-img">
      <div class="sig-resizer"></div>
    </div>
  </div>

  <input class="sig-text" placeholder="Name" <?= $readonly ? 'readonly' : '' ?>>
  <input class="sig-text" placeholder="Position" <?= $readonly ? 'readonly' : '' ?>>
</td>
      <td class="center signature">
  <strong>Verified and Validated by:</strong><br><br>

  <div class="sig-container" style="position: relative; min-height: 90px; overflow: visible;">
    <input type="file" accept="image/*" class="signature-upload" onchange="loadSignature(this)">
    <div class="sig-box" style="display:none;">
      <span class="sig-delete">✖</span>
      <img class="signature-img">
      <div class="sig-resizer"></div>
    </div>
  </div>

  <input class="sig-text" placeholder="Name">
  <input class="sig-text" placeholder="Position">
</td>
      <td class="center signature">
  <strong>Approved by:</strong><br><br>

  <div class="sig-container" style="position: relative; min-height: 90px; overflow: visible;">
    <input type="file" accept="image/*" class="signature-upload" onchange="loadSignature(this)">
    <div class="sig-box" style="display:none;">
      <span class="sig-delete">✖</span>
      <img class="signature-img">
      <div class="sig-resizer"></div>
    </div>
  </div>

  <input class="sig-text" placeholder="Name">
  <input class="sig-text" placeholder="Position">
</td>
    </tr>
  </table>

</div>
</form>
<script>
    // Tell JS whether this is an existing report
    window.editingReportId = <?= $editingReport ? $editingReport['id'] : 'null' ?>;
</script>
<script src="script1.js?v=4"></script>
<?php include "jo_footer.php"; ?>

</body>
</html>