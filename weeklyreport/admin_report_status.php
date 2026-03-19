<?php
session_name('admin_session');
session_start();
require "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Ensure eval columns exist
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_status VARCHAR(20) DEFAULT 'Pending'");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_rating VARCHAR(20) DEFAULT NULL");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_remarks TEXT DEFAULT NULL");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS evaluated_at DATETIME DEFAULT NULL");

// Filters
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$filterSearch = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterMonth  = isset($_GET['month'])  ? intval($_GET['month'])  : 0;
$filterYear   = isset($_GET['year'])   ? intval($_GET['year'])   : date('Y');

$validStatuses = ['all', 'Approved', 'Pending', 'Returned'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = 'all';

// Counts per status
$counts = ['all' => 0, 'Approved' => 0, 'Pending' => 0, 'Returned' => 0];
$cr = $conn->query("
    SELECT eval_status, COUNT(*) as cnt
    FROM weekly_reports r
    JOIN users u ON r.user_id = u.id
    WHERE u.role = 'jo'
    GROUP BY eval_status
");
if ($cr) {
    while ($row = $cr->fetch_assoc()) {
        $s = $row['eval_status'] ?? 'Pending';
        if (isset($counts[$s])) $counts[$s] = (int)$row['cnt'];
    }
}
$counts['all'] = array_sum([$counts['Approved'], $counts['Pending'], $counts['Returned']]);

// Build main query
$where  = ["u.role = 'jo'", "YEAR(r.created_at) = ?"];
$params = [$filterYear];
$types  = 'i';

if ($filterStatus !== 'all') {
    $where[]  = "r.eval_status = ?";
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($filterMonth > 0) {
    $where[]  = "MONTH(r.created_at) = ?";
    $params[] = $filterMonth;
    $types   .= 'i';
}
if ($filterSearch !== '') {
    $like     = "%$filterSearch%";
    $where[]  = "(u.full_name LIKE ? OR u.employee_id LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$sql = "
    SELECT
        r.id, r.report_name, r.week_range,
        r.eval_status, r.eval_rating, r.eval_remarks, r.evaluated_at,
        r.created_at,
        u.full_name, u.username, u.employee_id, u.position, u.division,
        u.id AS user_id,
        MONTHNAME(r.created_at) AS month_name,
        MONTH(r.created_at) AS month_num
    FROM weekly_reports r
    JOIN users u ON r.user_id = u.id
    $whereSQL
    ORDER BY r.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Available years
$yearsResult = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS y FROM weekly_reports ORDER BY y DESC
");

include "header.php";
?>

<style>
.main { margin-left: 250px; padding: 28px 28px 40px; transition: margin .3s; min-height: calc(100vh - 49px); font-family: 'IBM Plex Sans', sans-serif; }
.main.collapsed { margin-left: 70px; }

.page-title { font-size: 20px; font-weight: 700; color: #001f3f; margin-bottom: 4px; }
.page-sub   { font-size: 13px; color: #718096; margin-bottom: 24px; }

/* ── STATUS TABS ── */
.status-tabs { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
.status-tab {
    flex: 1; min-width: 140px;
    display: flex; align-items: center; gap: 12px;
    background: #fff; border-radius: 10px; padding: 14px 18px;
    text-decoration: none; color: inherit;
    box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    border-top: 3px solid #e2e8f0;
    transition: transform .2s, box-shadow .2s;
}
.status-tab:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.status-tab.tab-all      { border-top-color: #001f3f; }
.status-tab.tab-approved { border-top-color: #28a745; }
.status-tab.tab-pending  { border-top-color: #ffc107; }
.status-tab.tab-returned { border-top-color: #dc3545; }
.status-tab .tab-icon  { font-size: 26px; }
.status-tab .tab-count { font-size: 26px; font-weight: 700; font-family: 'IBM Plex Mono', monospace; line-height: 1; }
.status-tab .tab-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #718096; margin-top: 2px; }
.tab-all      .tab-count { color: #001f3f; }
.tab-approved .tab-count { color: #28a745; }
.tab-pending  .tab-count { color: #b7791f; }
.tab-returned .tab-count { color: #dc3545; }
.status-tab.active-tab { box-shadow: 0 8px 24px rgba(0,0,0,.12); }
.status-tab.active-tab .tab-label { color: #1a202c; font-weight: 700; }

/* ── FILTER BAR ── */
.filter-bar {
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
    background: #fff; border-radius: 10px; padding: 12px 18px;
    margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08);
}
.filter-bar label { font-size: 13px; font-weight: 600; color: #444; display: flex; align-items: center; gap: 8px; }
.filter-bar select {
    padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 13px; background: #f8f9fb; color: #333; cursor: pointer;
}
.search-wrap { position: relative; display: flex; align-items: center; }
.search-wrap input {
    padding: 6px 30px 6px 32px; border: 1px solid #e2e8f0;
    border-radius: 6px; font-size: 13px; background: #f8f9fb;
    color: #333; width: 220px; transition: border-color .2s;
}
.search-wrap input:focus { outline: none; border-color: #001f3f; background: #fff; }
.search-icon  { position: absolute; left: 10px; font-size: 13px; pointer-events: none; }
.search-clear { position: absolute; right: 8px; background: none; border: none; font-size: 12px; color: #999; cursor: pointer; }
.search-clear:hover { color: #dc3545; }
.result-count { margin-left: auto; font-size: 12px; color: #718096; }

/* ── TABLE PANEL ── */
.table-panel { background: #fff; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06); overflow: hidden; }
.table-panel-head {
    padding: 14px 18px; border-bottom: 1px solid #e2e8f0;
    background: #fafbfc; display: flex; align-items: center; justify-content: space-between;
}
.table-panel-head h3 { font-size: 13.5px; font-weight: 700; margin: 0; }

.report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.report-table th {
    text-align: left; padding: 10px 14px;
    font-size: 11px; font-weight: 700; letter-spacing: .05em;
    text-transform: uppercase; color: #718096;
    border-bottom: 1px solid #e2e8f0; background: #fafbfc;
}
.report-table td { padding: 11px 14px; border-bottom: 1px solid #f0f2f7; vertical-align: middle; }
.report-table tr:last-child td { border-bottom: none; }
.report-table tbody tr:hover td { background: #f8faff; }

/* ── CELLS ── */
.emp-name { font-weight: 600; color: #1a202c; }
.emp-meta { font-size: 11px; color: #718096; margin-top: 2px; }
.emp-id   { font-size: 11px; font-family: monospace; background: #eef2fa; color: #001f3f; padding: 1px 6px; border-radius: 3px; }
.report-name { font-weight: 600; color: #1a202c; }
.week-range  { font-size: 11px; color: #718096; margin-top: 2px; }
.month-pill  { display: inline-block; background: #e8f0fe; color: #0046b8; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }

/* ── BADGES ── */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
.badge-approved { background: #d4edda; color: #155724; }
.badge-pending  { background: #fff3cd; color: #856404; }
.badge-returned { background: #f8d7da; color: #721c24; }

/* ── RATING PILL ── */
.rating-pill { display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 700; background: #e8f4ff; color: #0074d9; }

/* ── ACTION BUTTON ── */
.btn-view {
    padding: 4px 12px; background: #001f3f; color: #fff;
    border-radius: 5px; text-decoration: none; font-size: 12px;
    font-weight: 600; transition: opacity .2s; display: inline-block;
}
.btn-view:hover { opacity: .8; }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 52px 24px; color: #718096; font-size: 14px; }
.empty-state .empty-icon { font-size: 42px; margin-bottom: 10px; }

/* ── DARK MODE ── */
.main.dark { background: #0f1117; color: #e2e8f0; }
.main.dark .page-title { color: #90cdf4; }
.main.dark .page-sub   { color: #718096; }
.main.dark .status-tab,
.main.dark .table-panel,
.main.dark .filter-bar  { background: #1a1d27; color: #e2e8f0; }
.main.dark .status-tab .tab-label { color: #718096; }
.main.dark .status-tab.active-tab .tab-label { color: #e2e8f0; }
.main.dark .table-panel-head { background: #1e2133; border-color: #2d3148; }
.main.dark .report-table th  { background: #1e2133; color: #718096; border-color: #2d3148; }
.main.dark .report-table td  { border-color: #2d3148; }
.main.dark .report-table tbody tr:hover td { background: #252836; }
.main.dark .emp-name   { color: #e2e8f0; }
.main.dark .emp-meta   { color: #718096; }
.main.dark .emp-id     { background: #252836; color: #90cdf4; }
.main.dark .report-name { color: #e2e8f0; }
.main.dark .week-range  { color: #718096; }
.main.dark .month-pill  { background: #1a2d4a; color: #90cdf4; }
.main.dark .filter-bar select { background: #252836; color: #e2e8f0; border-color: #3d4263; }
.main.dark .filter-bar label  { color: #a0aec0; }
.main.dark .search-wrap input { background: #252836; color: #e2e8f0; border-color: #3d4263; }
.main.dark .result-count { color: #718096; }
</style>

<div class="main" id="main">

    <div class="page-title">📊 Weekly Report Status</div>
    <div class="page-sub">View all JO weekly reports filtered by evaluation status.</div>

    <!-- ── STATUS TAB CARDS ── -->
    <div class="status-tabs">
        <?php
        $tabs = [
            ['status' => 'all',      'icon' => '📋', 'label' => 'All Reports', 'cls' => 'tab-all'],
            ['status' => 'Approved', 'icon' => '✅', 'label' => 'Approved',    'cls' => 'tab-approved'],
            ['status' => 'Pending',  'icon' => '⏳', 'label' => 'Pending',     'cls' => 'tab-pending'],
            ['status' => 'Returned', 'icon' => '🔄', 'label' => 'Returned',    'cls' => 'tab-returned'],
        ];
        foreach ($tabs as $tab):
            $isActive = $filterStatus === $tab['status'];
            $qs = http_build_query(['status' => $tab['status'], 'year' => $filterYear, 'month' => $filterMonth, 'search' => $filterSearch]);
        ?>
        <a href="admin_report_status.php?<?= $qs ?>"
           class="status-tab <?= $tab['cls'] ?> <?= $isActive ? 'active-tab' : '' ?>">
            <div class="tab-icon"><?= $tab['icon'] ?></div>
            <div>
                <div class="tab-count"><?= $counts[$tab['status']] ?? $counts['all'] ?></div>
                <div class="tab-label"><?= $tab['label'] ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- ── FILTER BAR ── -->
    <form method="GET" id="filterForm">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
        <div class="filter-bar">
            <label>Year:
                <select name="year" onchange="this.form.submit()">
                    <?php
                    $yrs = [];
                    if ($yearsResult) while ($y = $yearsResult->fetch_assoc()) $yrs[] = $y['y'];
                    if (!$yrs) $yrs = [date('Y')];
                    foreach ($yrs as $y): ?>
                        <option value="<?= $y ?>" <?= $y == $filterYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Month:
                <select name="month" onchange="this.form.submit()">
                    <option value="0" <?= $filterMonth == 0 ? 'selected' : '' ?>>All Months</option>
                    <?php
                    $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                    foreach ($months as $i => $m): ?>
                        <option value="<?= $i+1 ?>" <?= $filterMonth == $i+1 ? 'selected' : '' ?>><?= $m ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <div class="search-wrap">
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" id="searchInput"
                           value="<?= htmlspecialchars($filterSearch) ?>"
                           placeholder="Search by name or ID…"
                           autocomplete="off">
                    <button type="button" class="search-clear" id="clearSearch"
                            style="<?= $filterSearch ? '' : 'display:none' ?>">✕</button>
                </div>
            </label>
            <span class="result-count" id="resultCount"><?= count($rows) ?> result<?= count($rows) !== 1 ? 's' : '' ?></span>
        </div>
    </form>

    <!-- ── TABLE ── -->
    <div class="table-panel">
        <div class="table-panel-head">
            <h3>
                <?php
                $headLabels = ['all' => 'All Reports', 'Approved' => '✅ Approved Reports', 'Pending' => '⏳ Pending Reports', 'Returned' => '🔄 Returned Reports'];
                echo $headLabels[$filterStatus] ?? 'Reports';
                ?>
            </h3>
        </div>

        <?php if (empty($rows)): ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <?= $filterStatus === 'Approved' ? '✅' : ($filterStatus === 'Returned' ? '🔄' : ($filterStatus === 'Pending' ? '⏳' : '📋')) ?>
                </div>
                <div>No <?= $filterStatus !== 'all' ? strtolower($filterStatus) : '' ?> reports found for the selected period.</div>
            </div>
        <?php else: ?>
        <table class="report-table" id="reportTable">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Report Name</th>
                    <th>Month</th>
                    <th>Week Range</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Rating</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    $es   = $r['eval_status'] ?? 'Pending';
                    $bcls = strtolower($es) === 'approved' ? 'badge-approved' : (strtolower($es) === 'returned' ? 'badge-returned' : 'badge-pending');
                    $icon = strtolower($es) === 'approved' ? '✅' : (strtolower($es) === 'returned' ? '🔄' : '⏳');
                ?>
                <tr data-name="<?= strtolower(htmlspecialchars($r['full_name'] ?? $r['username'])) ?>"
                    data-empid="<?= strtolower(htmlspecialchars($r['employee_id'] ?? '')) ?>">
                    <td>
                        <div class="emp-name"><?= htmlspecialchars($r['full_name'] ?? $r['username']) ?></div>
                        <div class="emp-meta">
                            <?php if ($r['employee_id']): ?>
                                <span class="emp-id"><?= htmlspecialchars($r['employee_id']) ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($r['position'] ?? '') ?>
                            <?php if ($r['division']): ?> · <?= htmlspecialchars($r['division']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="report-name"><?= htmlspecialchars($r['report_name'] ?? 'Untitled') ?></div>
                    </td>
                    <td><span class="month-pill"><?= htmlspecialchars($r['month_name']) ?></span></td>
                    <td><span class="week-range"><?= htmlspecialchars($r['week_range'] ?? '—') ?></span></td>
                    <td style="white-space:nowrap;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                    <td><span class="badge <?= $bcls ?>"><?= $icon ?> <?= htmlspecialchars($es) ?></span></td>
                    <td>
                        <?php if ($r['eval_rating']): ?>
                            <span class="rating-pill"><?= htmlspecialchars($r['eval_rating']) ?></span>
                        <?php else: ?>
                            <span style="color:#bbb; font-size:12px;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn-view"
                           href="admin_evaluate_report.php?user_id=<?= $r['user_id'] ?>&month=<?= $r['month_num'] ?>&year=<?= $filterYear ?>">
                            📝 Evaluate
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div><!-- /.main -->

<script>
// ── CLIENT-SIDE SEARCH ──────────────────────────────────────────────────────
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
const resultCount = document.getElementById('resultCount');

let debounce;
searchInput.addEventListener('input', function () {
    clearSearch.style.display = this.value ? 'inline' : 'none';
    clearTimeout(debounce);
    debounce = setTimeout(filterTable, 200);
});

clearSearch.addEventListener('click', function () {
    searchInput.value = '';
    this.style.display = 'none';
    filterTable();
});

function filterTable() {
    const q = searchInput.value.toLowerCase();
    let visible = 0;
    document.querySelectorAll('#reportTable tbody tr').forEach(function (tr) {
        const match = !q || tr.dataset.name.includes(q) || tr.dataset.empid.includes(q);
        tr.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    const total = document.querySelectorAll('#reportTable tbody tr').length;
    resultCount.textContent = (q ? visible : total) + ' result' + ((q ? visible : total) !== 1 ? 's' : '');
}
</script>

<?php include "footer.php"; ?>

<!-- 
=================================================================
INTEGRATION NOTE — update these in admin_monthly_summary.php:

Replace the static badge spans in .month-totals with links:

<a href="admin_report_status.php?status=Approved&year=<?= $filterYear ?>&month=<?= $parts[0] ?>" class="badge-approved">✅ <?= $monthTotals['approved'] ?> Approved</a>
<a href="admin_report_status.php?status=Pending&year=<?= $filterYear ?>&month=<?= $parts[0] ?>"  class="badge-pending">⏳ <?= $monthTotals['pending'] ?> Pending</a>
<a href="admin_report_status.php?status=Returned&year=<?= $filterYear ?>&month=<?= $parts[0] ?>" class="badge-returned">🔄 <?= $monthTotals['returned'] ?> Returned</a>

Also update the admin dashboard stat cards for this month/last month
to link to admin_report_status.php?status=Pending etc.
=================================================================
-->