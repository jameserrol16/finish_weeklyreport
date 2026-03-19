<?php
session_name('admin_session');
session_start();
require_once "db.php";

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header("Location: login.php"); exit; }

$isAdmin  = true;
$reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$readonly = isset($_GET['readonly']) && $_GET['readonly'] == 1;

// Fetch logged-in user's profile for autofill
$sessionUser = [];
$stmtUser = $conn->prepare("SELECT full_name, position, branch, division FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$sessionUser = $stmtUser->get_result()->fetch_assoc() ?? [];

$editingReport  = null;
$editingContent = [];

if ($reportId > 0) {
    $stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ?");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $editingReport = $stmt->get_result()->fetch_assoc();
    if (!$editingReport) die("Report not found or you do not have access.");
    $editingContent = json_decode($editingReport['content'], true) ?? [];
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_report'
) {
    $reportId        = $_POST['id'] ?? '';
    $reportName      = trim($_POST['report_name'] ?? 'Untitled Report');
    $weekRange       = trim($_POST['week_range'] ?? '');
    $rangeStart      = trim($_POST['range_start'] ?? '');
    $rangeEnd        = trim($_POST['range_end'] ?? '');
    $employee        = trim($_POST['employee'] ?? '');
    $division        = trim($_POST['division'] ?? '');
    $position        = trim($_POST['position'] ?? '');
    $branch          = trim($_POST['branch'] ?? '');
    $workTask        = trim($_POST['work_task'] ?? '');
    $accomplishments = json_decode($_POST['accomplishments'] ?? '[]', true);

    $contentJson = json_encode([
        'employee'        => $employee,
        'division'        => $division,
        'position'        => $position,
        'branch'          => $branch,
        'work_task'       => $workTask,
        'accomplishments' => $accomplishments,
        'range_start'     => $rangeStart,
        'range_end'       => $rangeEnd,
        'sig_texts'       => json_decode($_POST['sig_texts']  ?? '[]', true),
        'sig_images'      => json_decode($_POST['sig_images'] ?? '[]', true),
    ]);

    if (!empty($reportId)) {
        $stmt = $conn->prepare("
            UPDATE weekly_reports
            SET report_name=?, week_range=?, content=?, updated_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("sssi", $reportName, $weekRange, $contentJson, $reportId);
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

include "header.php";
?>

<link rel="stylesheet" href="/weeklyreport/style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
  .main { margin-left: 250px; padding: 30px; transition: margin-left 0.3s ease; }
  .main.collapsed { margin-left: 70px; }
  body { margin: 0; padding: 0; }
  .page { margin: 0 auto; }

  /* Hide native date picker calendar icon in preview mode */
  .preview-mode input[type="date"]::-webkit-calendar-picker-indicator { display: none !important; }
  .preview-mode input[type="date"] { -webkit-appearance: none; appearance: none; }

  /* ── Signature Icon Button ── */
  .sig-icon-btn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 5px; width: 72px; height: 72px; margin: 6px auto;
    border: 2px dashed #bbb; border-radius: 10px; background: #fafafa;
    cursor: pointer; color: #555; font-size: 11px;
    transition: border-color .2s, background .2s, color .2s; user-select: none;
  }
  .sig-icon-btn:hover { border-color: #333; background: #f0f0f0; color: #111; }
  .sig-icon-btn .sig-icon-svg { font-size: 22px; line-height: 1; }
  .sig-icon-btn[disabled] { opacity: 0.4; pointer-events: none; }

  /* ── Signature Modal ── */
  .sig-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 2000; }
  .sig-modal-box {
    position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
    background: #fff; border-radius: 10px; box-shadow: 0 8px 40px rgba(0,0,0,.28);
    width: 460px; max-width: 96vw; z-index: 2001; font-family: Arial, sans-serif; overflow: hidden;
  }
  .sig-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid #e0e0e0; font-weight: bold; font-size: 14px;
  }
  .sig-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; line-height: 1; color: #555; }
  .sig-modal-close:hover { color: #000; }
  .sig-modal-tabs { display: flex; border-bottom: 1px solid #e0e0e0; }
  .sig-tab {
    flex: 1; padding: 10px; border: none; background: #f8f8f8; cursor: pointer;
    font-size: 12px; border-bottom: 3px solid transparent; transition: background .15s;
  }
  .sig-tab:hover { background: #efefef; }
  .sig-tab.active { background: #fff; border-bottom-color: #000; font-weight: bold; }
  .sig-tab-content { display: none; padding: 16px 18px 0; }
  .sig-tab-content.active { display: block; }
  .sig-tab-hint { font-size: 11px; color: #777; margin: 0 0 10px; }
  #sigTypeInput { width: 100%; box-sizing: border-box; font-size: 13px; padding: 7px 10px; border: 1px solid #ccc; border-radius: 4px; margin-bottom: 10px; }
  .sig-font-options { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
  .sig-font-opt { border: 2px solid #ddd; border-radius: 6px; padding: 5px 10px; font-size: 20px; cursor: pointer; background: #fafafa; transition: border-color .15s; white-space: nowrap; }
  .sig-font-opt:hover { border-color: #888; }
  .sig-font-opt.selected { border-color: #000; background: #f0f0f0; }
  #sigTypeCanvas { display: block; width: 100%; height: 80px; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; }
  .sig-canvas-wrap { border: 1px solid #ccc; border-radius: 4px; background: #fff; overflow: hidden; }
  #sigDrawCanvas { display: block; width: 100%; height: 130px; cursor: crosshair; touch-action: none; }
  .sig-upload-drop {
    border: 2px dashed #bbb; border-radius: 8px; padding: 28px 20px; text-align: center;
    color: #888; font-size: 13px; cursor: pointer; transition: border-color .15s, background .15s; position: relative;
  }
  .sig-upload-drop:hover, .sig-upload-drop.drag-over { border-color: #333; background: #f5f5f5; color: #333; }
  .sig-upload-drop input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; }
  #sigUploadCanvas { width: 100%; border: 1px solid #e0e0e0; border-radius: 4px; background: #fff; }
  .sig-color-row { display: flex; align-items: center; gap: 8px; margin-top: 8px; font-size: 12px; }
  .sig-color-row input[type="color"] { width: 32px; height: 28px; border: 1px solid #ccc; border-radius: 4px; padding: 2px; cursor: pointer; }
  .sig-clear-btn { margin-left: auto; border: 1px solid #ccc; background: #fff; padding: 4px 10px; font-size: 11px; cursor: pointer; border-radius: 4px; }
  .sig-clear-btn:hover { background: #f0f0f0; }
  .sig-modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 18px; border-top: 1px solid #e0e0e0; margin-top: 14px; }
  .sig-cancel-btn { border: 1px solid #ccc; background: #fff; padding: 7px 16px; font-size: 12px; cursor: pointer; border-radius: 4px; }
  .sig-cancel-btn:hover { background: #f0f0f0; }
  .sig-apply-btn { border: none; background: #111; color: #fff; padding: 7px 18px; font-size: 12px; cursor: pointer; border-radius: 4px; }
  .sig-apply-btn:hover { background: #333; }

  /* Dark mode */
  body.dark { background: #1a1d27; color: #e2e8f0; }
  .main.dark .page { background: #1e2133; color: #e2e8f0; }
  .main.dark input, .main.dark textarea, .main.dark .sig-text { background: #252836 !important; color: #e2e8f0 !important; border-color: #3d4263 !important; }
  .main.dark input[readonly], .main.dark textarea[readonly] { background: #1e2133 !important; color: #a0aec0 !important; }
  .main.dark table { background: #1e2133; color: #e2e8f0; }
  .main.dark table th { background: #252836; color: #90cdf4; border-color: #3d4263; }
  .main.dark table td { background: #1e2133; color: #e2e8f0; border-color: #2d3148; }
  .main.dark .top-right button, .main.dark .table-actions button { background: #252836; color: #e2e8f0; border: 1px solid #3d4263; }
  .main.dark .sig-icon-btn { background: #252836; border-color: #4a5080; color: #a0aec0; }
  .main.dark #reportNamePrompt { background: #1a1d27; color: #e2e8f0; border: 1px solid #2d3148; }
  .main.dark #newReportName { background: #252836; color: #e2e8f0; border-color: #3d4263; }
  .sig-modal-dark .sig-modal-box { background: #1a1d27 !important; color: #e2e8f0; }
  .sig-modal-dark .sig-modal-header { border-color: #2d3148; color: #e2e8f0; }
  .sig-modal-dark .sig-tab { background: #252836; color: #a0aec0; }
  .sig-modal-dark .sig-tab.active { background: #1a1d27; color: #e2e8f0; border-bottom-color: #90cdf4; }
  .sig-modal-dark #sigTypeInput { background: #252836 !important; color: #e2e8f0 !important; border-color: #3d4263 !important; }
  .sig-modal-dark .sig-font-opt { background: #252836; border-color: #3d4263; color: #e2e8f0; }
  .sig-modal-dark .sig-upload-drop { background: #252836; border-color: #4a5080; color: #a0aec0; }
  .sig-modal-dark .sig-cancel-btn { background: #252836; border-color: #3d4263; color: #e2e8f0; }
  .sig-modal-dark .sig-apply-btn { background: #90cdf4; color: #001f3f; }
</style>

<div id="main" class="main">
<div class="page" id="reportPage">
  <form id="reportForm" method="POST" onsubmit="return validateReportForm()">
    <input type="hidden" name="report_name"
           value="<?= htmlspecialchars($editingReport['report_name'] ?? '') ?>">
    <input type="hidden" name="id" value="<?= $editingReport['id'] ?? '' ?>">

    <!-- REPORT NAME MODAL -->
    <div id="modalOverlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;"></div>
    <div id="reportNamePrompt" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; z-index:1001; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,.2);">
        <h3 style="margin:0 0 12px;">Enter Report Name</h3>
        <input type="text" id="newReportName" placeholder="Report Name" style="width:100%; margin-bottom:10px;">
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

    <!-- DATE RANGE — restored from content JSON -->
    <div class="date-range" style="text-align:center; margin-bottom:4px;">
      <div id="datePickerRow" style="display:flex; align-items:center; justify-content:center; gap:8px; font-size:12px; padding:4px 0;">
        <label style="font-weight:bold;">From:</label>
        <input type="date" id="rangeStart" name="range_start"
               style="width:140px; text-align:center;"
               value="<?= htmlspecialchars($editingContent['range_start'] ?? '') ?>"
               <?= $readonly ? 'readonly' : '' ?>>
        <label style="font-weight:bold;">To:</label>
        <input type="date" id="rangeEnd" name="range_end"
               style="width:140px; text-align:center;"
               value="<?= htmlspecialchars($editingContent['range_end'] ?? '') ?>"
               <?= $readonly ? 'readonly' : '' ?>>
      </div>
      <div id="dateRangeError"
           style="display:none; color:#dc3545; font-size:11px; margin-top:3px;">
        ⚠ "From" date cannot be later than "To" date.
      </div>
      <div id="weekRangeDisplay"
           style="display:none; font-size:12px; text-align:center; padding:4px 0;">
        <?= htmlspecialchars($editingReport['week_range'] ?? '') ?>
      </div>
      <input type="hidden" name="week_range" id="weekRangeHidden"
             value="<?= htmlspecialchars($editingReport['week_range'] ?? '') ?>">
    </div>
    <br>

    <?php
    $autoEmployee = $editingReport ? ($editingContent['employee'] ?? '') : ($sessionUser['full_name'] ?? '');
    $autoDivision = $editingReport ? ($editingContent['division'] ?? '') : ($sessionUser['division'] ?? '');
    $autoPosition = $editingReport ? ($editingContent['position'] ?? '') : ($sessionUser['position'] ?? '');
    $autoBranch   = $editingReport ? ($editingContent['branch']   ?? '') : ($sessionUser['branch']    ?? '');
    ?>
    <table>
      <tr>
        <td class="label">Employee :</td>
        <td><input name="employee" value="<?= htmlspecialchars($autoEmployee) ?>" readonly style="background:#f5f5f5; cursor:not-allowed;"></td>
        <td class="label">Division :</td>
        <td><input name="division" value="<?= htmlspecialchars($autoDivision) ?>" readonly style="background:#f5f5f5; cursor:not-allowed;"></td>
      </tr>
      <tr>
        <td class="label">Position :</td>
        <td><input name="position" value="<?= htmlspecialchars($autoPosition) ?>" readonly style="background:#f5f5f5; cursor:not-allowed;"></td>
        <td class="label">Branch :</td>
        <td><input name="branch" value="<?= htmlspecialchars($autoBranch) ?>" readonly style="background:#f5f5f5; cursor:not-allowed;"></td>
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
        <?php
        $sigLabels = ['Submitted by:', 'Verified and Validated by:', 'Approved by:'];
        foreach ($sigLabels as $idx => $label): ?>
        <td class="center signature">
          <strong><?= $label ?></strong><br><br>
          <div class="sig-container" style="position: relative; min-height: 90px; overflow: visible;">
            <?php if (!$readonly): ?>
            <button type="button"
                    class="sig-icon-btn"
                    onclick="openSignatureModal(this)"
                    title="Add Signature">
              <span class="sig-icon-svg">✍️</span>
              <span>Sign here</span>
            </button>
            <?php endif; ?>
            <div class="sig-box" style="display:none;">
              <span class="sig-delete">✖</span>
              <img class="signature-img">
              <div class="sig-resizer"></div>
            </div>
          </div>
          <input class="sig-text" placeholder="Name"
                 value="<?= $idx === 0 ? htmlspecialchars($sessionUser['full_name'] ?? '') : '' ?>"
                 <?= $readonly ? 'readonly' : '' ?>>
          <input class="sig-text" placeholder="Position"
                 value="<?= $idx === 0 ? htmlspecialchars($sessionUser['position'] ?? '') : '' ?>"
                 <?= $readonly ? 'readonly' : '' ?>>
        </td>
        <?php endforeach; ?>
      </tr>
    </table>

  </form>

  <script>
    window.editingReportId      = <?= $editingReport ? $editingReport['id'] : 'null' ?>;
    window.savedAccomplishments = <?= json_encode($editingContent['accomplishments'] ?? []) ?>;
    window.savedSigTexts        = <?= json_encode($editingContent['sig_texts']       ?? []) ?>;
    window.savedSigImages       = <?= json_encode($editingContent['sig_images']      ?? []) ?>;
    const reportPage = document.getElementById('reportPage');
    const previewBtn = document.getElementById('previewBtn');
  </script>
</div>
</div>

<script src="script1.js?v=6"></script>
<script>
// Mirror dark mode onto signature modal
(function () {
    const main = document.getElementById('main');
    if (!main) return;
    function syncModal() {
        const isDark = main.classList.contains('dark');
        document.querySelectorAll('.sig-modal-box, .sig-modal-overlay').forEach(function(el) {
            el.classList.toggle('sig-modal-dark', isDark);
        });
    }
    new MutationObserver(syncModal).observe(main, { attributes: true, attributeFilter: ['class'] });
    syncModal();
})();
</script>
<?php include "footer.php"; ?>