<?php
session_name('admin_session');
session_start();
require_once "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$reports = $stmt->get_result();

// Create leave table if not exists, then fetch leave applications
$conn->query("CREATE TABLE IF NOT EXISTS leave_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    full_name VARCHAR(255),
    department VARCHAR(255),
    position VARCHAR(255),
    salary VARCHAR(100),
    date_filed VARCHAR(50),
    leave_type VARCHAR(255),
    leave_details TEXT,
    days_applied VARCHAR(50),
    inclusive_dates VARCHAR(255),
    commutation VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$stmtLeave = $conn->prepare("SELECT * FROM leave_applications WHERE user_id = ? ORDER BY created_at DESC");
$stmtLeave->bind_param("i", $userId);
$stmtLeave->execute();
$leaveApps = $stmtLeave->get_result();

include "header.php";
?>

<div id="main" class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="header-text">
            <h2 id="pageTitle">Weekly Accomplishment Reports</h2>
            <p id="pageSubtitle">Manage your reports: edit drafts or view submitted reports</p>
        </div>
    </div>

    <!-- Tab Switcher -->
    <div class="tab-switcher">
        <button class="tab-btn active" id="tabReports" onclick="switchTab('reports')">
            📄 Weekly Accomplishment Reports
        </button>
        <button class="tab-btn" id="tabLeave" onclick="switchTab('leave')">
            📋 My Leave Applications
        </button>
    </div>

    <!-- ── Weekly Reports Panel ── -->
    <div id="panelReports" class="tab-panel">
        <div class="report-card">
            <div class="report-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Week Range</th>
                            <th>Created At</th>
                            <th>Last Updated</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($reports->num_rows > 0): ?>
                            <?php while ($row = $reports->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['report_name']) ?></td>
                                <td><?= htmlspecialchars($row['week_range']) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($row['created_at'])) ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($row['updated_at'])) ?></td>
                                <td>
                                    <a class="action-btn edit" href="index.php?id=<?= $row['id'] ?>">✏ Edit</a>
                                    <a class="action-btn delete"
                                       href="delete_report.php?id=<?= $row['id'] ?>"
                                       onclick="return confirm('Are you sure you want to delete this report?')">🗑 Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="no-reports">No reports found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── Leave Applications Panel ── -->
    <div id="panelLeave" class="tab-panel" style="display:none;">
        <div class="report-card">
            <div style="display:flex;align-items:center;justify-content:flex-end;margin-bottom:14px;">
                <a href="admin_leave.php" class="action-btn"
                   style="background:#1a7a3c;color:#fff;text-decoration:none;padding:7px 16px;border-radius:4px;font-size:13px;font-weight:600;">
                   + New Leave Application
                </a>
            </div>
            <div class="report-table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date Filed</th>
                            <th>Leave Type</th>
                            <th>Days Applied</th>
                            <th>Inclusive Dates</th>
                            <th>Saved On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($leaveApps->num_rows > 0): ?>
                            <?php while ($lrow = $leaveApps->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($lrow['date_filed'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($lrow['leave_type'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($lrow['days_applied'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($lrow['inclusive_dates'] ?: '—') ?></td>
                                <td><?= date("M d, Y h:i A", strtotime($lrow['created_at'])) ?></td>
                                <td>
                                    <a class="action-btn edit" href="edit_leave.php?id=<?= $lrow['id'] ?>">✏ Edit</a>
                                    <a class="action-btn delete"
                                       href="delete_leave.php?id=<?= $lrow['id'] ?>"
                                       onclick="return confirm('Delete this leave application?')">🗑 Delete</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="no-reports">No leave applications found.
                                <a href="admin_leave.php" style="color:#003366;">File one now →</a>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
function switchTab(tab) {
    const isReports = tab === 'reports';

    document.getElementById('panelReports').style.display = isReports ? 'block' : 'none';
    document.getElementById('panelLeave').style.display   = isReports ? 'none'  : 'block';

    document.getElementById('tabReports').classList.toggle('active', isReports);
    document.getElementById('tabLeave').classList.toggle('active', !isReports);

    document.getElementById('pageTitle').textContent    = isReports
        ? 'Weekly Accomplishment Reports'
        : 'My Leave Applications';
    document.getElementById('pageSubtitle').textContent = isReports
        ? 'Manage your reports: edit drafts or view submitted reports'
        : 'View, edit or delete your filed leave applications';

    // Persist active tab across page load
    localStorage.setItem('activeTab', tab);
}

// Restore last active tab
(function () {
    const saved = localStorage.getItem('activeTab');
    if (saved === 'leave') switchTab('leave');
})();
</script>

<style>
/* Tab Switcher */
.tab-switcher {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
}

.tab-btn {
    padding: 9px 20px;
    border-radius: 6px;
    border: 2px solid #003366;
    background: #fff;
    color: #003366;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-family: Arial, sans-serif;
}

.tab-btn:hover {
    background: #e8f0fb;
}

.tab-btn.active {
    background: #003366;
    color: #fff;
}

body.dark .tab-btn {
    background: #2a2a2a;
    color: #a0b8d8;
    border-color: #a0b8d8;
}

body.dark .tab-btn:hover {
    background: #333;
}

body.dark .tab-btn.active {
    background: #003366;
    color: #fff;
    border-color: #003366;
}

/* Main layout */
.main-content {
    margin-left: 250px;
    padding: 40px;
    min-height: 100vh;
    background-color: #f8f9fa;
    font-family: Arial, sans-serif;
    transition: margin-left 0.3s ease; /* ADD THIS */
}

.main-content.collapsed {
    margin-left: 70px; /* ADD THIS */
}

/* Page header with logo */
.page-header {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
}
body.dark .main-content {
    background-color: #1e1e1e; /* match body dark background */
    color: #f5f5f5; /* ensure text is visible */
}
body.dark .main-content p,
body.dark .main-content h2,
body.dark .main-content h3,
body.dark .main-content span,
body.dark .main-content strong {
    color: #f5f5f5;
}
.gov-logo {
    width: 60px;
    margin-right: 20px;
}

.header-text h2 {
    margin: 0;
    font-size: 28px;
    color: #1a1a1a;
}

.header-text p {
    margin: 4px 0 0 0;
    color: #555;
    font-size: 14px;
}

/* Card container */
.report-card {
    background-color: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

/* Table styling */
.report-table-container {
    overflow-x: auto;
}

/* Base table styling */
table {
    border-collapse: collapse;
    width: 100%;
    min-width: 700px;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
    color: #1a1a1a; /* default text color for light mode */
}

th {
    background-color: #003366; /* navy header */
    color: #fff;
    font-weight: 600;
    font-size: 14px;
}

tr:nth-child(even) {
    background-color: #f2f4f7;
}

/* Status badges */
.status-draft {
    background-color: #fef3e6;
    color: #e67e22;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 13px;
}

.status-submitted {
    background-color: #e6f7ef;
    color: #27ae60;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: bold;
    font-size: 13px;
}

/* Action buttons */
.action-btn {
    text-decoration: none;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 13px;
    margin-right: 4px;
    font-weight: 600;
    transition: 0.2s;
}

.action-btn.edit { background-color: #f0ad4e; color: #fff; }
.action-btn.edit:hover { background-color: #ec971f; }

.action-btn.finalize { background-color: #5cb85c; color: #fff; }
.action-btn.finalize:hover { background-color: #4cae4c; }

.action-btn.view { background-color: #337ab7; color: #fff; }
.action-btn.view:hover { background-color: #286090; }

/* “No reports found” row */
.no-reports {
    text-align: center;
    font-weight: bold;
    color: #333; /* default light mode color */
}

/* Dark mode styling */
body.dark {
    background-color: #1e1e1e;
    color: #f5f5f5;
}

body.dark table th {
    background-color: #002244;
    color: #f5f5f5;
}

body.dark table tr:nth-child(even) {
    background-color: #2a2a2a;
}

body.dark table td {
    color: #f5f5f5;
}

body.dark .status-draft {
    background-color: #663d1f;
    color: #f9d6b0;
}

body.dark .status-submitted {
    background-color: #1e3b23;
    color: #b9f0d0;
}

body.dark .action-btn.edit { background-color: #e6954e; color: #fff; }
body.dark .action-btn.finalize { background-color: #4cae4c; color: #fff; }
body.dark .action-btn.view { background-color: #286090; color: #fff; }

body.dark .no-reports {
    color: #f5f5f5; /* now visible in dark mode */
}
body.dark {
    background-color: #1e1e1e;
    color: #f5f5f5;
}

body.dark .page-header h2,
body.dark .page-header p {
    color: #f5f5f5;
}

body.dark .report-card {
    background-color: #2a2a2a;
    color: #f5f5f5;
}

body.dark table th {
    background-color: #003366;
    color: #fff;
}

body.dark table tr:nth-child(even) {
    background-color: #333;
}

body.dark table td {
    color: #f5f5f5;
}

body.dark .status-draft {
    background-color: #663d1f;
    color: #f9d6b0;
}

body.dark .status-submitted {
    background-color: #1e3b23;
    color: #b9f0d0;
}

body.dark .action-btn.edit { background-color: #e6954e; color: #fff; }
body.dark .action-btn.finalize { background-color: #4cae4c; color: #fff; }
body.dark .action-btn.view { background-color: #286090; color: #fff; }

body.dark .no-reports {
    color: #f5f5f5;
}

body.dark table th,
body.dark table td {
    border-bottom: 1px solid #555;
}
/* Responsive design */
@media screen and (max-width: 768px) {
    .main-content { margin-left: 0; padding: 20px; }
    table { min-width: 100%; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .gov-logo { margin-bottom: 10px; }
}
</style>

<?php include "footer.php"; ?>