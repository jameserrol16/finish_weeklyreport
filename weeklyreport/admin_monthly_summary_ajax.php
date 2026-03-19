<?php
/**
 * admin_monthly_summary_ajax.php
 * Returns JSON { html: "..." } for the report content section.
 * Called by the instant-search JS in admin_monthly_summary.php
 */
session_name('admin_session');
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['html' => '<p class="no-reports">Access denied.</p>']);
    exit;
}

header('Content-Type: application/json');

$filterYear   = isset($_GET['year'])   ? intval($_GET['year'])   : date('Y');
$filterMonth  = isset($_GET['month'])  ? intval($_GET['month'])  : 0;
$filterSearch = isset($_GET['search']) ? trim($_GET['search'])   : '';

$whereMonth  = $filterMonth  > 0  ? "AND MONTH(w.created_at) = ?"          : "";
$whereSearch = $filterSearch !== '' ? "AND (u.full_name LIKE ? OR u.employee_id LIKE ?)" : "";

$sql = "
    SELECT
        u.id              AS user_id,
        u.full_name,
        u.employee_id,
        MONTH(w.created_at)     AS month_num,
        MONTHNAME(w.created_at) AS month_name,
        YEAR(w.created_at)      AS yr,
        COUNT(w.id)             AS total_reports,
        SUM(CASE WHEN w.eval_status = 'Approved' THEN 1 ELSE 0 END) AS approved,
        SUM(CASE WHEN w.eval_status = 'Pending'  THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN w.eval_status = 'Returned' THEN 1 ELSE 0 END) AS returned
    FROM weekly_reports w
    JOIN users u ON w.user_id = u.id
    WHERE u.role = 'jo'
      AND YEAR(w.created_at) = ?
      $whereMonth
      $whereSearch
    GROUP BY u.id, u.full_name, u.employee_id, YEAR(w.created_at), MONTH(w.created_at)
    ORDER BY MONTH(w.created_at) ASC, u.full_name ASC
";

$stmt   = $conn->prepare($sql);
$types  = "i";
$params = [$filterYear];

if ($filterMonth > 0) {
    $types   .= "i";
    $params[] = $filterMonth;
}
if ($filterSearch !== '') {
    $like     = "%{$filterSearch}%";
    $types   .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$summaryRows = $stmt->get_result();

$grouped = [];
$totalsPerMonth = [];
while ($row = $summaryRows->fetch_assoc()) {
    $key = $row['month_num'] . '|' . $row['month_name'];
    $grouped[$key][] = $row;
    if (!isset($totalsPerMonth[$key])) {
        $totalsPerMonth[$key] = ['total' => 0, 'approved' => 0, 'pending' => 0, 'returned' => 0];
    }
    $totalsPerMonth[$key]['total']    += $row['total_reports'];
    $totalsPerMonth[$key]['approved'] += $row['approved'];
    $totalsPerMonth[$key]['pending']  += $row['pending'];
    $totalsPerMonth[$key]['returned'] += $row['returned'];
}

// ── Build HTML ─────────────────────────────────────────────────────────────
ob_start();

if (empty($grouped)) {
    echo '<div class="report-card"><p class="no-reports">No reports found for the selected period.</p></div>';
} else {
    foreach ($grouped as $monthKey => $joRows) {
        $monthTotals = $totalsPerMonth[$monthKey];
        $parts       = explode('|', $monthKey);
        $monthLabel  = $parts[1] . ' ' . $filterYear;
        ?>
        <div class="month-block">
            <div class="month-header">
                <div class="month-title">
                    <span class="month-badge">📅 <?= htmlspecialchars($monthLabel) ?></span>
                </div>
                <div class="month-totals">
                    <span class="badge-total"><?= $monthTotals['total'] ?> Reports</span>
                    <span class="badge-approved">✅ <?= $monthTotals['approved'] ?> Approved</span>
                    <span class="badge-pending">⏳ <?= $monthTotals['pending'] ?> Pending</span>
                    <?php if ($monthTotals['returned'] > 0): ?>
                    <span class="badge-returned">🔄 <?= $monthTotals['returned'] ?> Returned</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="report-card">
                <div class="report-table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>JO Name</th>
                                <th>Total Submitted</th>
                                <th>Approved</th>
                                <th>Pending</th>
                                <th>Returned</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($joRows as $jr): ?>
                            <tr>
                                <td><span class="emp-id"><?= htmlspecialchars($jr['employee_id'] ?: '—') ?></span></td>
                                <td><strong><?= htmlspecialchars($jr['full_name']) ?></strong></td>
                                <td><span class="count-pill"><?= $jr['total_reports'] ?></span></td>
                                <td><?= $jr['approved'] > 0 ? "<span class='badge-sm approved'>{$jr['approved']}</span>" : "<span class='muted'>—</span>" ?></td>
                                <td><?= $jr['pending']  > 0 ? "<span class='badge-sm pending'>{$jr['pending']}</span>"   : "<span class='muted'>—</span>" ?></td>
                                <td><?= $jr['returned'] > 0 ? "<span class='badge-sm returned'>{$jr['returned']}</span>" : "<span class='muted'>—</span>" ?></td>
                                <td>
                                    <a class="action-btn view"
                                       href="admin_evaluate_report.php?user_id=<?= $jr['user_id'] ?>&month=<?= $parts[0] ?>&year=<?= $filterYear ?>">
                                       📝 Evaluate
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

$html = ob_get_clean();
echo json_encode(['html' => $html]);