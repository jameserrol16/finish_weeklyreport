<?php
session_name('admin_session');
session_start();
require "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$reportId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($reportId === 0) die("No report specified.");

$stmt = $conn->prepare("
    SELECT w.*, u.full_name 
    FROM weekly_reports w 
    JOIN users u ON w.user_id = u.id 
    WHERE w.id = ?
");
$stmt->bind_param("i", $reportId);
$stmt->execute();
$editingReport = $stmt->get_result()->fetch_assoc();
if (!$editingReport) die("Report not found.");

$editingContent = json_decode($editingReport['content'], true) ?? [];

include "header.php";
?>

<link rel="stylesheet" href="style.css">
<style>
  body { margin: 0; padding: 0; background: #f4f6f9; }
  .main { margin-left: 250px; padding: 30px; transition: margin-left 0.3s ease; box-sizing: border-box; }
  .main.collapsed { margin-left: 70px; }

  /* Make all inputs/textareas readonly-looking without breaking layout */
  .page input, .page textarea {
    pointer-events: none !important;
    cursor: default !important;
    background: #f9f9f9 !important;
    border-color: #ddd !important;
  }
/* Hide all placeholders in admin view */
.page input::placeholder,
.page textarea::placeholder {
    color: transparent !important;
}
  @media print {
    .sidebar, .header { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; }
  }
</style>

<div id="main" class="main">
  <div class="page" id="reportPage">

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
      <input name="week_range" readonly style="text-align:center;"
        value="<?= htmlspecialchars($editingReport['week_range'] ?? '') ?>">
    </div>
    <br><br>

    <table>
      <tr>
        <td class="label">Employee :</td>
        <td><input readonly value="<?= htmlspecialchars($editingContent['employee'] ?? '') ?>"></td>
        <td class="label">Division :</td>
        <td><input readonly value="<?= htmlspecialchars($editingContent['division'] ?? '') ?>"></td>
      </tr>
      <tr>
        <td class="label">Position :</td>
        <td><input readonly value="<?= htmlspecialchars($editingContent['position'] ?? '') ?>"></td>
        <td class="label">Branch :</td>
        <td><input readonly value="<?= htmlspecialchars($editingContent['branch'] ?? '') ?>"></td>
      </tr>
    </table>
    <br>

    <table>
      <tr><td class="label">Work Task:</td></tr>
      <tr><td><textarea readonly><?= htmlspecialchars($editingContent['work_task'] ?? '') ?></textarea></td></tr>
    </table>
    <br>

    <table id="accomplishmentTable">
      <thead>
        <tr id="headerRow">
          <th>Day (Onsite/WFH)</th>
          <th>Accomplishment</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <br>

    <table>
      <tr>
        <td class="center signature">
          <strong>Submitted by:</strong><br><br>
          <div class="sig-container" style="position:relative; height:80px;"></div>
          <br>
          <input class="sig-text" placeholder="Name" readonly>
          <input class="sig-text" placeholder="Position" readonly>
        </td>
        <td class="center signature">
          <strong>Verified and Validated by:</strong><br><br>
          <div class="sig-container" style="position:relative; height:80px;"></div>
          <br>
          <input class="sig-text" placeholder="Name" readonly>
          <input class="sig-text" placeholder="Position" readonly>
        </td>
        <td class="center signature">
          <strong>Approved by:</strong><br><br>
          <div class="sig-container" style="position:relative; height:80px;"></div>
          <br>
          <input class="sig-text" placeholder="Name" readonly>
          <input class="sig-text" placeholder="Position" readonly>
        </td>
      </tr>
    </table>

  </div><!-- close .page -->
</div><!-- close #main -->

<script>
  window.editingReportId = <?= $editingReport['id'] ?>;
  window.isAdminView = true;
  window.savedAccomplishments = <?= json_encode($editingContent['accomplishments'] ?? []) ?>;
</script>

<?php include "footer.php"; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.isAdminView) return;

    const tbody = document.querySelector("#accomplishmentTable tbody");
    tbody.innerHTML = "";

    if (window.savedAccomplishments && window.savedAccomplishments.length > 0) {
        window.savedAccomplishments.forEach(row => {
            const tr = document.createElement("tr");

            // Column 1: Day + Mode (matches JO addRow() structure)
            const dayTd = document.createElement("td");
            dayTd.style.textAlign = "center";

          const dateInput = document.createElement("input");
dateInput.type = "text";  // plain text — no browser date UI
dateInput.style.width = "100%";
dateInput.style.textAlign = "center";
dateInput.value = row.day || '';
dateInput.readOnly = true;

            const modeInput = document.createElement("input");
            modeInput.type = "text";
            modeInput.className = "work-mode";
            modeInput.placeholder = "On-site / WFH";
            modeInput.style.textAlign = "center";
            modeInput.style.width = "100%";
            modeInput.value = row.mode || '';
            modeInput.readOnly = true;

            dayTd.appendChild(dateInput);
            dayTd.appendChild(modeInput);
            tr.appendChild(dayTd);

            // Column 2: Accomplishment
            const accTd = document.createElement("td");
            const accTextarea = document.createElement("textarea");
            accTextarea.value = row.accomplishment || '';
            accTextarea.readOnly = true;
            accTd.appendChild(accTextarea);
            tr.appendChild(accTd);

            // Column 3: Description
            const descTd = document.createElement("td");
            const descTextarea = document.createElement("textarea");
            descTextarea.value = row.description || '';
            descTextarea.readOnly = true;
            descTd.appendChild(descTextarea);
            tr.appendChild(descTd);

            tbody.appendChild(tr);

            // Auto-expand textareas to match content height
            [accTextarea, descTextarea].forEach(t => {
                t.style.height = 'auto';
                t.style.height = t.scrollHeight + 'px';
            });
        });
    }

    // Show sig-text inputs (don't hide them — admin is just viewing)
    document.querySelectorAll('.sig-text').forEach(el => {
        el.style.display = 'block';
    });
});
</script>