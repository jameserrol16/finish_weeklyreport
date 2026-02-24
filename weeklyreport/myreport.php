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

include "header.php";
?>

<div id="main" class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-text">
            <h2>Weekly Accomplishment Reports</h2>
            <p>Manage your reports: edit drafts or view submitted reports</p>
        </div>
    </div>

    <!-- Reports Card -->
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
        <?php if($reports->num_rows > 0): ?>
            <?php while($row = $reports->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['report_name']) ?></td>
                <td><?= htmlspecialchars($row['week_range']) ?></td>
                <td><?= date("M d, Y H:i", strtotime($row['created_at'])) ?></td>
                <td><?= date("M d, Y H:i", strtotime($row['updated_at'])) ?></td>
              <td>
    <a class="action-btn edit" href="index.php?id=<?= $row['id'] ?>">✏ Edit</a>
    <a class="action-btn delete" 
       href="delete_report.php?id=<?= $row['id'] ?>" 
       onclick="return confirm('Are you sure you want to delete this report?')">
       🗑 Delete
    </a>
</td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="no-reports">No reports found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
        </div>
    </div>
</div>

<style>
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