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

if (!$id) { header("Location: myreport.php"); exit; }

// Fetch the leave record (must belong to this user)
$stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$leave = $stmt->get_result()->fetch_assoc();
if (!$leave) { header("Location: myreport.php"); exit; }

// Also fetch user profile for readonly fields
$stmtUser = $conn->prepare("SELECT full_name, position, branch, division, sg FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$sessionUser = $stmtUser->get_result()->fetch_assoc() ?? [];

$autoPosition = $sessionUser['position'] ?? '';
$autoSalary   = $sessionUser['sg'] ?? '';
$autoDept     = trim(($sessionUser['branch'] ?? '') . (!empty($sessionUser['branch']) && !empty($sessionUser['division']) ? ' – ' : '') . ($sessionUser['division'] ?? ''));

$autoFullName = $sessionUser['full_name'] ?? '';
$autoLast = $autoFirst = $autoMid = '';
if (!empty($autoFullName)) {
    if (strpos($autoFullName, ',') !== false) {
        [$last, $rest] = explode(',', $autoFullName, 2);
        $autoLast  = trim($last);
        $restParts = array_values(array_filter(explode(' ', trim($rest))));
        $autoFirst = $restParts[0] ?? '';
        $autoMid   = count($restParts) > 1 ? implode(' ', array_slice($restParts, 1)) : '';
    } else {
        $parts = array_values(array_filter(explode(' ', trim($autoFullName))));
        $count = count($parts);
        if ($count === 1) { $autoFirst = $parts[0]; }
        elseif ($count === 2) { $autoFirst = $parts[0]; $autoLast = $parts[1]; }
        else {
            $autoLast = $parts[$count - 1];
            if (substr($parts[$count - 2], -1) === '.') {
                $autoMid   = $parts[$count - 2];
                $autoFirst = implode(' ', array_slice($parts, 0, $count - 2));
            } else {
                $autoFirst = implode(' ', array_slice($parts, 0, $count - 1));
            }
        }
    }
}

// Handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function s($k){ return isset($_POST[$k]) ? trim($_POST[$k]) : ''; }
    $leaveTypeIds  = ['vl','mfl','sl','matl','patl','spl','solo','study','vawc','rehab','slbw','secl','adopt'];
    // These come pre-computed from JS (same as save_leave.php)
    $date_filed      = s('f_date');
    $leave_type      = s('leave_type');
    $leave_details   = s('leave_details');
    $days_applied    = s('f_days');
    $inclusive_dates = trim(s('f_dates1') . ' ' . s('f_dates2'));
    $commutation     = s('commutation');

    $upd = $conn->prepare("UPDATE leave_applications SET date_filed=?, leave_type=?, leave_details=?, days_applied=?, inclusive_dates=?, commutation=? WHERE id=? AND user_id=?");
    $upd->bind_param("ssssssii", $date_filed, $leave_type, $leave_details, $days_applied, $inclusive_dates, $commutation, $id, $userId);
    $upd->execute();
    header("Location: myreport.php");
    exit;
}

include "header.php";
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<style>
@page { size: auto; margin: 10mm; }
@media print {
  body { margin:0; padding:0; background:#fff; }
  .main { margin:0 !important; padding:0 !important; }
  .leave-top-bar { display:none !important; }
  .leave-page { width:100% !important; max-width:210mm; min-height:297mm; margin:0 auto; padding:10mm; box-shadow:none; border:none; page-break-after:always; }
  html,body { width:210mm; height:297mm; }
  table,tr,td { page-break-inside:avoid !important; }
  img { max-width:100%; height:auto; }
}
.main { margin-left:250px; padding:30px; transition:margin-left 0.3s ease; }
.main.collapsed { margin-left:70px; }
.leave-page { width:850px; margin:0 auto 40px; background:#fff; padding:28px 32px; border:1px solid #000; box-shadow:0 4px 12px rgba(0,0,0,0.12); border-radius:4px; font-family:Arial,Helvetica,sans-serif; font-size:11px; position:relative; }
.leave-top-bar { display:flex; justify-content:flex-end; gap:8px; margin-bottom:14px; width:850px; margin-left:auto; margin-right:auto; }
.leave-top-bar button { border:1px solid #001f3f; background:#001f3f; color:#fff; padding:6px 16px; font-size:12px; border-radius:4px; cursor:pointer; transition:background 0.2s; }
.leave-top-bar button:hover { background:#003366; }
.leave-top-bar button.secondary { background:#fff; color:#001f3f; }
.leave-top-bar button.secondary:hover { background:#f0f4f8; }
.leave-top-bar button.active-preview { background:#e8a000; border-color:#e8a000; }
.form-meta { display:flex; justify-content:space-between; font-size:10px; margin-bottom:4px; }
.leave-title { text-align:center; font-size:18px; font-weight:bold; letter-spacing:1px; margin:10px 0 8px; text-transform:uppercase; }
.leave-page table { width:100%; border-collapse:collapse; font-size:11px; }
.leave-page th,.leave-page td { border:1px solid #000; padding:3px 5px; vertical-align:top; }
.leave-page thead th { background:#f0f0f0; font-weight:bold; font-size:10px; }
.section-header { background:#e8e8e8; font-weight:bold; text-align:center; font-size:11px; letter-spacing:0.5px; }
.leave-page input[type="text"],.leave-page input[type="date"],.leave-page textarea,.leave-page select { width:100%; box-sizing:border-box; border:none; border-bottom:1px solid #999; background:transparent; font-size:11px; font-family:Arial,Helvetica,sans-serif; padding:2px 3px; outline:none; resize:none; }
.leave-page input[type="text"]:focus,.leave-page input[type="date"]:focus { border-bottom-color:#001f3f; background:#f5f9ff; }
.leave-page input[readonly] { background:#f5f5f5 !important; cursor:not-allowed; color:#333; }
.cb-row { display:flex; align-items:flex-start; gap:6px; padding:2px 0; line-height:1.4; }
.cb-row input[type="checkbox"] { width:13px; height:13px; min-width:13px; margin-top:2px; accent-color:#001f3f; cursor:pointer; border:none; border-bottom:none; background:transparent; flex-shrink:0; }
.cb-row label { cursor:pointer; line-height:1.4; }
.sig-line { border-top:1px solid #000; margin-top:36px; padding-top:3px; text-align:center; font-size:10px; }
.preview-val { display:none; }
.leave-page.preview-mode input[type="text"],.leave-page.preview-mode input[type="date"] { display:none !important; }
.leave-page.preview-mode .preview-val { display:inline-block; min-width:30px; border-bottom:1px solid #555; font-size:11px; padding:1px 2px; white-space:pre-wrap; color:#000; }
.leave-page.preview-mode span.preview-val[data-for="f_7b_officer_name"],.leave-page.preview-mode span.preview-val[data-for="f_7b_officer_pos"] { display:inline-block !important; }
.leave-page.preview-mode .cb-row input[type="checkbox"]:not(:checked) { opacity:0.2; }
body.dark .leave-page { background:#ffffff !important; color:#000000 !important; }
body.dark .leave-page label,body.dark .leave-page td,body.dark .leave-page th,body.dark .leave-page strong,body.dark .leave-page span,body.dark .leave-page div,body.dark .leave-page em,body.dark .leave-page .preview-val,body.dark .leave-page .leave-title,body.dark .leave-page .form-meta { color:#000000 !important; }
body.dark .leave-page input[type="text"],body.dark .leave-page input[type="date"] { background:transparent !important; color:#000000 !important; border-bottom-color:#999 !important; }
body.dark .leave-page input[readonly] { background:#f5f5f5 !important; }
body.dark .leave-page table,body.dark .leave-page th,body.dark .leave-page td { border-color:#000 !important; }
body.dark .leave-page .section-header { background:#e8e8e8 !important; }
body.dark .leave-page thead th { background:#f0f0f0 !important; }
body.dark .leave-page .sig-line { border-top-color:#000 !important; }
</style>

<div id="main" class="main">
  <div class="leave-top-bar">
    <button class="secondary" onclick="window.location='myreport.php'">← Back</button>
    <button id="leavePreviewBtn" onclick="toggleLeavePreview()">👁 Preview</button>
    <button onclick="exportLeavePDF()">📄 Export to PDF</button>
    <button onclick="submitEditForm()" style="background:#1a7a3c;border-color:#1a7a3c;">💾 Save Changes</button>
  </div>
  <div id="saveToast" style="display:none;position:fixed;bottom:30px;right:30px;background:#1a7a3c;color:#fff;padding:12px 22px;border-radius:6px;font-size:14px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,0.2);z-index:9999;"></div>

  <!-- Hidden form for POST submit -->
  <form id="editLeaveForm" method="POST" action="edit_leave.php?id=<?= $id ?>" style="display:none;">
    <input type="hidden" name="f_date"       id="hf_date">
    <input type="hidden" name="f_days"       id="hf_days">
    <input type="hidden" name="f_dates1"     id="hf_dates1">
    <input type="hidden" name="f_dates2"     id="hf_dates2">
    <input type="hidden" name="leave_type"   id="hf_leave_type">
    <input type="hidden" name="leave_details" id="hf_leave_details">
    <input type="hidden" name="commutation"  id="hf_commutation">
  </form>

  <div class="leave-page" id="leaveForm">
    <div class="form-meta">
      <div><strong>Civil Service Form No. 6</strong><br>Revised 2020</div>
      <div style="text-align:right;"><strong>ANNEX A</strong></div>
    </div>
    <div style="display:flex; align-items:center; margin-bottom:8px;">
      <div style="min-width:100px;"></div>
      <div style="flex:1; display:flex; align-items:center; justify-content:center; gap:10px;">
        <img src="ntc-logo.png" style="width:60px; flex-shrink:0;">
        <div style="font-size:11px; line-height:1.6; text-align:center;">
          Republic of the Philippines<br>
          <strong style="font-size:12px;">NATIONAL TELECOMMUNICATIONS COMMISSION</strong><br>
          NTC Building, BIR Road, East Triangle, Diliman, Quezon City<br>
          Email: ntc@ntc.gov.ph &nbsp;|&nbsp; https://www.ntc.gov.ph
        </div>
        <img src="bagong-pilipinas.png" style="width:60px; flex-shrink:0;">
      </div>
      <div style="min-width:100px; border:1px solid #000; text-align:center; font-size:9px; padding:6px; line-height:1.5; align-self:stretch; display:flex; align-items:center; justify-content:center; flex-direction:column;">Stamp of Date<br>of Receipt</div>
    </div>
    <div class="leave-title">Application for Leave</div>

    <!-- Section 1–5 -->
    <table>
      <tr>
        <td style="width:22%;">1. OFFICE/DEPARTMENT<br>
          <input type="text" id="f_dept" value="<?= htmlspecialchars($autoDept) ?>" readonly title="Auto-filled from your profile">
          <span class="preview-val" data-for="f_dept"></span>
        </td>
        <td style="width:18%;">2. NAME (Last)<br>
          <input type="text" id="f_last" value="<?= htmlspecialchars($autoLast) ?>" readonly>
          <span class="preview-val" data-for="f_last"></span>
        </td>
        <td style="width:22%;">&nbsp;(First)<br>
          <input type="text" id="f_first" value="<?= htmlspecialchars($autoFirst) ?>" readonly>
          <span class="preview-val" data-for="f_first"></span>
        </td>
        <td style="width:18%;">&nbsp;(Middle)<br>
          <input type="text" id="f_mid" value="<?= htmlspecialchars($autoMid) ?>" readonly>
          <span class="preview-val" data-for="f_mid"></span>
        </td>
      </tr>
      <tr>
        <td>3. DATE OF FILING<br>
          <input type="date" id="f_date" value="<?= htmlspecialchars($leave['date_filed']) ?>">
          <span class="preview-val" data-for="f_date"></span>
        </td>
        <td colspan="2">4. POSITION<br>
          <input type="text" id="f_pos" value="<?= htmlspecialchars($autoPosition) ?>" readonly>
          <span class="preview-val" data-for="f_pos"></span>
        </td>
        <td>5. SALARY<br>
          <input type="text" id="f_sal" value="<?= htmlspecialchars($autoSalary) ?>" readonly>
          <span class="preview-val" data-for="f_sal"></span>
        </td>
      </tr>
    </table>

    <!-- Section 6 -->
    <table style="margin-top:6px;">
      <tr><td colspan="2" class="section-header">6. DETAILS OF APPLICATION</td></tr>
      <tr>
        <td style="width:55%; vertical-align:top; padding:6px 8px;">
          <div style="font-size:10px;">6.A TYPE OF LEAVE TO BE AVAILED OF<br><br>
          <?php
          $savedType = $leave['leave_type'] ?? '';
          $leaveMap = [
            'vl'      => 'Vacation Leave',
            'mfl'     => 'Mandatory/Forced Leave',
            'sl'      => 'Sick Leave',
            'matl'    => 'Maternity Leave',
            'patl'    => 'Paternity Leave',
            'spl'     => 'Special Privilege Leave',
            'solo'    => 'Solo Parent Leave',
            'study'   => 'Study Leave',
            'vawc'    => '10-Day VAWC Leave',
            'rehab'   => 'Rehabilitation Privilege',
            'slbw'    => 'Special Leave Benefits for Women',
            'secl'    => 'Special Emergency (Calamity) Leave',
            'adopt'   => 'Adoption Leave',
          ];
          foreach ($leaveMap as $cbId => $label) {
            $checked = (strpos($savedType, $label) !== false) ? 'checked' : '';
            echo '<div class="cb-row"><input type="checkbox" id="' . $cbId . '" ' . $checked . '><label for="' . $cbId . '"><strong>' . $label . '</strong></label></div>';
          }
          $othersVal = '';
          if (strpos($savedType, 'Others') !== false) {
            preg_match('/Others(?:: ?(.+))?/', $savedType, $m);
            $othersVal = $m[1] ?? '';
          }
          $othersChecked = $othersVal !== '' || strpos($savedType, 'Others') !== false ? 'checked' : '';
          ?>
          <div class="cb-row" style="align-items:center;">
            <input type="checkbox" id="others_cb" <?= $othersChecked ?>>
            <label for="others_cb"><strong>Others:</strong></label>
            <input type="text" id="f_others" style="flex:1;margin-left:4px;" value="<?= htmlspecialchars($othersVal) ?>">
            <span class="preview-val" data-for="f_others" style="flex:1;margin-left:4px;"></span>
          </div>
          </div>
        </td>
        <td style="vertical-align:top; padding:6px 8px;">
          <div style="font-size:10px;">6.B DETAILS OF LEAVE<br><br>
          <?php $savedDet = $leave['leave_details'] ?? ''; ?>
          <div style="font-size:10px;margin-bottom:4px;"><em>In case of Vacation/Special Privilege Leave:</em></div>
          <div class="cb-row"><input type="checkbox" id="within" <?= strpos($savedDet,'Within the Philippines')!==false?'checked':'' ?>><label for="within">Within the Philippines</label></div>
          <div class="cb-row" style="align-items:center;">
            <input type="checkbox" id="abroad" <?= strpos($savedDet,'Abroad')!==false?'checked':'' ?>><label for="abroad">Abroad (Specify)</label>
            <input type="text" id="f_abroad" style="flex:1;margin-left:4px;">
            <span class="preview-val" data-for="f_abroad" style="flex:1;margin-left:4px;"></span>
          </div>
          <br>
          <div style="font-size:10px;margin-bottom:4px;"><em>In case of Sick Leave:</em></div>
          <div class="cb-row" style="align-items:center;">
            <input type="checkbox" id="inhosp" <?= strpos($savedDet,'In Hospital')!==false?'checked':'' ?>><label for="inhosp">In Hospital (Specify Illness)</label>
            <input type="text" id="f_inhosp" style="flex:1;margin-left:4px;">
            <span class="preview-val" data-for="f_inhosp" style="flex:1;margin-left:4px;"></span>
          </div>
          <div class="cb-row" style="align-items:center;">
            <input type="checkbox" id="outpat" <?= strpos($savedDet,'Out Patient')!==false?'checked':'' ?>><label for="outpat">Out Patient (Specify Illness)</label>
            <input type="text" id="f_outpat" style="flex:1;margin-left:4px;">
            <span class="preview-val" data-for="f_outpat" style="flex:1;margin-left:4px;"></span>
          </div>
          <br>
          <div style="font-size:10px;margin-bottom:4px;"><em>In case of Special Leave Benefits for Women:</em></div>
          <div class="cb-row" style="align-items:center;">
            <input type="checkbox" id="slbw2" <?= strpos($savedDet,'(Specify Illness)')!==false?'checked':'' ?>><label for="slbw2">(Specify Illness)</label>
            <input type="text" id="f_slbw2" style="flex:1;margin-left:4px;">
            <span class="preview-val" data-for="f_slbw2" style="flex:1;margin-left:4px;"></span>
          </div>
          <br>
          <div style="font-size:10px;margin-bottom:4px;"><em>In case of Study Leave:</em></div>
          <div class="cb-row"><input type="checkbox" id="masters" <?= strpos($savedDet,"Master's")!==false?'checked':'' ?>><label for="masters">Completion of Master's Degree</label></div>
          <div class="cb-row"><input type="checkbox" id="bar" <?= strpos($savedDet,'BAR/Board')!==false?'checked':'' ?>><label for="bar">BAR/Board Examination Review Other</label></div>
          <div class="cb-row" style="align-items:center;">
            <span style="padding-left:20px;white-space:nowrap;">purpose:</span>
            <input type="text" id="f_studypurp" style="flex:1;margin-left:4px;">
            <span class="preview-val" data-for="f_studypurp" style="flex:1;margin-left:4px;"></span>
          </div>
          <br>
          <div class="cb-row"><input type="checkbox" id="moneti" <?= strpos($savedDet,'Monetization')!==false?'checked':'' ?>><label for="moneti">Monetization of Leave Credits</label></div>
          <div class="cb-row"><input type="checkbox" id="terminal" <?= strpos($savedDet,'Terminal')!==false?'checked':'' ?>><label for="terminal">Terminal Leave</label></div>
          </div>
        </td>
      </tr>
    </table>

    <!-- Section 6C & 6D -->
    <table>
      <tr>
        <td style="width:55%;vertical-align:top;padding:6px 8px;">
          <div style="font-size:10px;">6.C NUMBER OF WORKING DAYS APPLIED FOR<br><br>
          <input type="text" id="f_days" value="<?= htmlspecialchars($leave['days_applied'] ?? '') ?>">
          <span class="preview-val" data-for="f_days"></span>
          <br><br>
          <div style="font-size:10px;">INCLUSIVE DATES<br>
          <?php
          $incDates = explode(' ', $leave['inclusive_dates'] ?? '', 2);
          $date1 = $incDates[0] ?? '';
          $date2 = $incDates[1] ?? '';
          ?>
          <input type="text" id="f_dates1" value="<?= htmlspecialchars($date1) ?>" placeholder="e.g. January 6–10, 2025">
          <span class="preview-val" data-for="f_dates1"></span>
          <input type="text" id="f_dates2" value="<?= htmlspecialchars($date2) ?>">
          <span class="preview-val" data-for="f_dates2"></span>
          </div>
        </td>
        <td style="vertical-align:top;padding:6px 8px;">
          <div style="font-size:10px;">6.D COMMUTATION<br><br>
          <?php $comm = $leave['commutation'] ?? ''; ?>
          <div class="cb-row"><input type="checkbox" id="notreq" <?= $comm==='Not Requested'?'checked':'' ?>><label for="notreq">Not Requested</label></div>
          <div class="cb-row"><input type="checkbox" id="req" <?= $comm==='Requested'?'checked':'' ?>><label for="req">Requested</label></div>
          <br><br><br>
          <div class="sig-line">Signature of Applicant</div>
        </td>
      </tr>
    </table>

    <!-- Section 7 -->
    <table style="margin-top:6px;">
      <tr><td colspan="2" class="section-header">7. DETAILS OF ACTION ON APPLICATION</td></tr>
      <tr>
        <td style="width:55%;vertical-align:top;padding:6px 8px;">
          <div style="font-size:10px;">7.A CERTIFICATION OF LEAVE CREDITS<br>
          <div style="margin:4px 0 8px;font-size:10px;display:flex;align-items:center;gap:4px;">
            As of &nbsp;<input type="text" id="f_asof" placeholder="date" style="width:100px;"><span class="preview-val" data-for="f_asof" style="width:100px;"></span>
          </div>
          <table class="leave-credits-bold">
            <thead><tr><th style="width:38%;"></th><th>Vacation Leave</th><th>Sick Leave</th></tr></thead>
            <tbody>
              <tr><td>Total Earned</td><td><input type="text" id="f_te_vl"><span class="preview-val" data-for="f_te_vl"></span></td><td><input type="text" id="f_te_sl"><span class="preview-val" data-for="f_te_sl"></span></td></tr>
              <tr><td>Less this application</td><td><input type="text" id="f_la_vl"><span class="preview-val" data-for="f_la_vl"></span></td><td><input type="text" id="f_la_sl"><span class="preview-val" data-for="f_la_sl"></span></td></tr>
              <tr><td>Balance</td><td><input type="text" id="f_bal_vl"><span class="preview-val" data-for="f_bal_vl"></span></td><td><input type="text" id="f_bal_sl"><span class="preview-val" data-for="f_bal_sl"></span></td></tr>
            </tbody>
          </table>
          <div style="margin-top:40px; text-align:center;">
            <div style="border-top:1px solid #000; padding-top:4px;">
              <strong style="font-size:11px;">FLORA R. RALAR</strong><br>
              <span style="font-size:10px;">Chief, Human Resource Division</span><br>
              <span style="font-size:10px; font-style:italic;">Authorized Officer</span>
            </div>
          </div>
        </td>
        <td style="vertical-align:top;padding:6px 8px;">
          <div style="font-size:10px;">7.B RECOMMENDATION<br><br>
          <div class="cb-row"><input type="checkbox" id="forapproval"><label for="forapproval">For approval</label></div>
          <div class="cb-row"><input type="checkbox" id="fordisapproval"><label for="fordisapproval">For disapproval due to</label></div>
          <br>
          <input type="text" id="f_rec_text" placeholder="Enter recommendation details"><span class="preview-val" data-for="f_rec_text"></span><br><br>
          <input type="text" id="f_rec_line2"><span class="preview-val" data-for="f_rec_line2"></span><br><br>
          <input type="text" id="f_rec_line3"><span class="preview-val" data-for="f_rec_line3"></span>
          <div style="margin-top:40px; text-align:center;">
            <div style="border-top:1px solid #000; padding-top:4px;">
              <input type="text" id="f_7b_officer_name" placeholder="Full Name" style="text-align:center;font-weight:bold;font-size:11px;border:none;border-bottom:1px solid #ccc;width:90%;"><span class="preview-val" data-for="f_7b_officer_name" style="font-weight:bold;font-size:11px;display:none;"></span><br>
              <input type="text" id="f_7b_officer_pos" placeholder="Position / Title" style="text-align:center;font-size:10px;border:none;border-bottom:1px solid #ccc;width:90%;margin-top:2px;"><span class="preview-val" data-for="f_7b_officer_pos" style="font-size:10px;display:none;"></span><br>
              <span style="font-size:10px;font-style:italic;">Authorized Officer</span>
            </div>
          </div>
        </td>
      </tr>
      <tr>
        <td colspan="2" style="padding:0; border:1px solid #000;">
          <table style="width:100%; border-collapse:collapse; border:none;">
            <tr>
              <td style="width:55%; vertical-align:top; padding:8px; border:none;">
                <div style="font-size:10px;">7.C APPROVED FOR<br><br>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;"><input type="text" id="f_days_pay" style="width:80px;"><span class="preview-val" data-for="f_days_pay" style="width:80px;"></span><span>days with pay</span></div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;"><input type="text" id="f_days_nopay" style="width:80px;"><span class="preview-val" data-for="f_days_nopay" style="width:80px;"></span><span>days without pay</span></div>
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:50px;"><input type="text" id="f_others7c" style="width:80px;"><span class="preview-val" data-for="f_others7c" style="width:80px;"></span><span>others (Specify)</span></div>
              </td>
              <td style="vertical-align:top; padding:8px; border:none;">
                <div style="font-size:10px;">7.D DISAPPROVED DUE TO:<br><br>
                <input type="text" id="f_dis1"><span class="preview-val" data-for="f_dis1"></span><br><br>
                <input type="text" id="f_dis2"><span class="preview-val" data-for="f_dis2"></span><br><br>
                <input type="text" id="f_dis3"><span class="preview-val" data-for="f_dis3"></span><br><br>
                <input type="text" id="f_dis4"><span class="preview-val" data-for="f_dis4"></span>
              </td>
            </tr>
            <tr>
              <td colspan="2" style="border:none; padding:10px 8px 16px; text-align:center;">
                <div style="width:340px; margin:0 auto; border-top:1px solid #000; padding-top:4px;">
                  <strong style="font-size:11px;">DIR. CANDIDO CESAR E. FAELDON</strong><br>
                  <span style="font-size:10px;">Director II, Administrative Branch</span><br>
                  <span style="font-size:10px; font-style:italic;">Authorized Officer</span>
                </div>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </div><!-- end .leave-page -->
</div><!-- end .main -->

<script>
function syncPreviewVals() {
  document.querySelectorAll('.preview-val[data-for]').forEach(span => {
    const el = document.getElementById(span.dataset.for);
    if (el) span.textContent = el.value || '\u00A0';
  });
}
function toggleLeavePreview() {
  const form = document.getElementById('leaveForm');
  const btn  = document.getElementById('leavePreviewBtn');
  const entering = !form.classList.contains('preview-mode');
  if (entering) { syncPreviewVals(); form.classList.add('preview-mode'); btn.textContent='✏️ Edit'; btn.classList.add('active-preview'); }
  else { form.classList.remove('preview-mode'); btn.textContent='👁 Preview'; btn.classList.remove('active-preview'); }
}
function getCheckedLabels(ids) {
  return ids.filter(id => { const el=document.getElementById(id); return el&&el.checked; })
            .map(id => document.querySelector('label[for="'+id+'"]')?.textContent?.trim()||id).join(', ');
}
function submitEditForm() {
  const leaveTypeIds = ['vl','mfl','sl','matl','patl','spl','solo','study','vawc','rehab','slbw','secl','adopt'];
  let leaveType = getCheckedLabels(leaveTypeIds);
  const othersEl = document.getElementById('others_cb');
  if (othersEl && othersEl.checked) {
    const spec = document.getElementById('f_others')?.value?.trim();
    leaveType += (leaveType?', ':'') + 'Others' + (spec?': '+spec:'');
  }
  const detailIds = ['within','abroad','inhosp','outpat','slbw2','masters','bar','moneti','terminal'];
  let leaveDetails = getCheckedLabels(detailIds);
  ['f_abroad','f_inhosp','f_outpat','f_slbw2','f_studypurp'].forEach(id => {
    const val = document.getElementById(id)?.value?.trim();
    if (val) leaveDetails += (leaveDetails?'; ':'')+val;
  });
  let commutation = '';
  if (document.getElementById('notreq')?.checked) commutation = 'Not Requested';
  if (document.getElementById('req')?.checked) commutation = 'Requested';
  document.getElementById('hf_date').value        = document.getElementById('f_date')?.value||'';
  document.getElementById('hf_days').value        = document.getElementById('f_days')?.value||'';
  document.getElementById('hf_dates1').value      = document.getElementById('f_dates1')?.value||'';
  document.getElementById('hf_dates2').value      = document.getElementById('f_dates2')?.value||'';
  document.getElementById('hf_leave_type').value  = leaveType;
  document.getElementById('hf_leave_details').value = leaveDetails;
  document.getElementById('hf_commutation').value = commutation;
  document.getElementById('editLeaveForm').submit();
}
async function exportLeavePDF() {
  const form = document.getElementById('leaveForm');
  const btn  = document.getElementById('leavePreviewBtn');
  const wasPreview = form.classList.contains('preview-mode');
  if (!wasPreview) { syncPreviewVals(); form.classList.add('preview-mode'); btn.textContent='✏️ Edit'; btn.classList.add('active-preview'); }
  await new Promise(r => setTimeout(r, 150));
  const canvas = await html2canvas(form, { scale:2, useCORS:true, backgroundColor:'#ffffff', logging:false });
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });
  const pW=pdf.internal.pageSize.getWidth(), pH=pdf.internal.pageSize.getHeight();
  const imgW=pW, imgH=(canvas.height*imgW)/canvas.width;
  const img=canvas.toDataURL('image/png');
  let y=0;
  while(y<imgH){ if(y>0) pdf.addPage(); pdf.addImage(img,'PNG',0,-y,imgW,imgH); y+=pH; }
  pdf.save('Application_for_Leave.pdf');
  if (!wasPreview) { form.classList.remove('preview-mode'); btn.textContent='👁 Preview'; btn.classList.remove('active-preview'); }
}
</script>

<?php include "footer.php"; ?>