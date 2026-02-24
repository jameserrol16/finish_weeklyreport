<?php
session_name('admin_session');
session_start();
include "db.php";

// Make sure only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Fetch all reports with JO names
$stmt = $conn->prepare("
    SELECT w.*, u.full_name
    FROM weekly_reports w
    JOIN users u ON w.user_id = u.id
    WHERE u.role = 'jo'
    ORDER BY w.created_at DESC
");
$stmt->execute();
$reports = $stmt->get_result();

include "header.php";
?>


<div id="main" class="main-content">
    <div class="page-header">
        <h2>All JO Reports</h2>
        <p>View JO Reports.</p>
    </div>

    <div class="report-card">
        <div class="report-table-container">
            <table>
                <thead>
                    <tr>
                        <th>JO Name</th>
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
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['report_name']) ?></td>
                            <td><?= htmlspecialchars($row['week_range']) ?></td>
                            <td><?= date("M d, Y H:i", strtotime($row['created_at'])) ?></td>
                            <td><?= date("M d, Y H:i", strtotime($row['updated_at'])) ?></td>
                            <td>
                               <a class="action-btn view" href="admin_viewreport.php?id=<?= $row['id'] ?>">👁 View</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-reports">No reports found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Base Light Mode */
body { background-color: #f8f9fa; color: #1a1a1a; font-family: Arial, sans-serif; }
.main-content { 
    margin-left: 250px; 
    padding: 40px; 
    min-height: 100vh; 
    transition: margin-left 0.3s ease;
}

#main.collapsed {
    margin-left: 70px;
}
.page-header h2 { margin: 0; font-size: 28px; color: #1a1a1a; }
.page-header p { margin: 4px 0 0 0; color: #555; font-size: 14px; }
.report-card { background-color: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
.report-table-container { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; min-width: 700px; }
th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e0e0e0; color: #1a1a1a; }
th { background-color: #003366; color: #fff; font-weight: 600; font-size: 14px; }
tr:nth-child(even) { background-color: #f2f4f7; }
.action-btn.view { background-color: #337ab7; color: #fff; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 13px; }
.action-btn.view:hover { background-color: #286090; }
.no-reports { text-align: center; font-weight: bold; color: #333; }

/* Dark Mode Overrides */
body.dark { background-color: #1e1e1e; color: #f5f5f5; }
body.dark .main-content { background-color: #1e1e1e; color: #f5f5f5; }
body.dark .page-header h2,
body.dark .page-header p { color: #f5f5f5; }
body.dark .report-card { background-color: #2a2a2a; color: #f5f5f5; }
body.dark table th { background-color: #002244; color: #f5f5f5; }
body.dark table td { color: #f5f5f5; }
body.dark tr:nth-child(even) { background-color: #333; }
body.dark .action-btn.view { background-color: #286090; color: #fff; }
body.dark .no-reports { color: #f5f5f5; }

/* Responsive */
@media screen and (max-width: 768px) {
    .main-content { margin-left: 0; padding: 20px; }
    table { min-width: 100%; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>

<?php include "footer.php"; ?> 