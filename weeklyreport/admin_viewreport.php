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

  .page {
    width: 900px !important;
    max-width: 100% !important;
    margin: 0 auto !important;
    background: #fff !important;
    padding: 25px !important;
    border: 1px solid #000 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    border-radius: 4px !important;
    position: relative !important;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 12px !important;
  }
  .page .letterhead {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 14px !important;
    margin-bottom: 10px !important;
  }
  .page .letterhead img { width: 65px !important; height: auto !important; }
  .page .letterhead-text { text-align: center !important; font-size: 12px !important; line-height: 1.4 !important; max-width: 520px !important; }
  .page h1 { text-align: center !important; font-size: 18px !important; font-weight: bold !important; }
  .page table { width: 100% !important; border-collapse: collapse !important; font-size: 12px !important; }
  .page th, .page td { border: 1px solid #000 !important; padding: 4px !important; vertical-align: top !important; }
  .page input, .page textarea {
    width: 100% !important;
    box-sizing: border-box !important;
    font-size: 12px !important;
    font-family: inherit !important;
    padding: 4px 6px !important;
    border: none !important;
    background: transparent !important;
    resize: none !important;
    pointer-events: none !important;
    cursor: default !important;
  }
  .page input::placeholder, .page textarea::placeholder { color: transparent !important; }

  @media print {
    .sidebar, .header { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; }
    .page { box-shadow: none !important; border: none !important; }
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
<script src="script1.js?v=2"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.isAdminView) return;

    const tbody = document.querySelector("#accomplishmentTable tbody");
    tbody.innerHTML = "";

    if (window.savedAccomplishments && window.savedAccomplishments.length > 0) {
        window.savedAccomplishments.forEach(row => {
            const tr = document.createElement("tr");

            const dayTd = document.createElement("td");
            dayTd.style.textAlign = "center";
            dayTd.style.fontSize = "12px";
            dayTd.innerHTML = `${row.day || ''}<br>${row.mode || ''}`;
            tr.appendChild(dayTd);

            const accTd = document.createElement("td");
            accTd.textContent = row.accomplishment || '';
            tr.appendChild(accTd);

            const descTd = document.createElement("td");
            descTd.textContent = row.description || '';
            tr.appendChild(descTd);

            tbody.appendChild(tr);
        });
    }

    // Hide empty sig-text inputs
    document.querySelectorAll('.sig-text').forEach(el => {
        if (!el.value || !el.value.trim()) el.style.display = 'none';
    });
});
</script>