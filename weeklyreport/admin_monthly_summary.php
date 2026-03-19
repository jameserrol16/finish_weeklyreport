<?php
session_name('admin_session');
session_start();
include "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

// Auto-create evaluation columns if not exist
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_rating VARCHAR(20) DEFAULT NULL");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_remarks TEXT DEFAULT NULL");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_status VARCHAR(20) DEFAULT 'Pending'");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS evaluated_at DATETIME DEFAULT NULL");

// Filters
$filterYear   = isset($_GET['year'])   ? intval($_GET['year'])   : date('Y');
$filterMonth  = isset($_GET['month'])  ? intval($_GET['month'])  : 0;
$filterSearch = isset($_GET['search']) ? trim($_GET['search'])   : '';

$yearsResult = $conn->query("
    SELECT DISTINCT YEAR(created_at) AS y
    FROM weekly_reports
    WHERE user_id IN (SELECT id FROM users WHERE role='jo')
    ORDER BY y DESC
");

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

$grandTotal = $grandApproved = $grandPending = $grandReturned = 0;
foreach ($totalsPerMonth as $t) {
    $grandTotal    += $t['total'];
    $grandApproved += $t['approved'];
    $grandPending  += $t['pending'];
    $grandReturned += $t['returned'];
}

include "header.php";
?>

<div id="main" class="main-content">

    <div class="page-header">
        <div class="header-text">
            <h2>📊 Monthly Reports Summary</h2>
            <p>Overview of JO weekly accomplishment reports by month — with evaluation tracking</p>
        </div>
    </div>

    <!-- Grand Stat Cards -->
    <div class="stat-row">
        <div class="stat-card total">
            <div class="stat-icon">📄</div>
            <div class="stat-info">
                <span class="stat-num"><?= $grandTotal ?></span>
                <span class="stat-label">Total Reports</span>
            </div>
        </div>
        <div class="stat-card approved">
            <div class="stat-icon">✅</div>
            <div class="stat-info">
                <span class="stat-num"><?= $grandApproved ?></span>
                <span class="stat-label">Approved</span>
            </div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon">⏳</div>
            <div class="stat-info">
                <span class="stat-num"><?= $grandPending ?></span>
                <span class="stat-label">Pending Evaluation</span>
            </div>
        </div>
        <div class="stat-card returned">
            <div class="stat-icon">🔄</div>
            <div class="stat-info">
                <span class="stat-num"><?= $grandReturned ?></span>
                <span class="stat-label">Returned</span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" id="filterForm" class="filter-bar">
        <label>Year:
            <select name="year" id="yearSelect">
                <?php
                $yearCount = 0;
                if ($yearsResult) {
                    while ($yr = $yearsResult->fetch_assoc()) {
                        $sel = $yr['y'] == $filterYear ? 'selected' : '';
                        echo "<option value=\"{$yr['y']}\" $sel>{$yr['y']}</option>";
                        $yearCount++;
                    }
                }
                if ($yearCount === 0) {
                    echo "<option value=\"" . date('Y') . "\" selected>" . date('Y') . "</option>";
                }
                ?>
            </select>
        </label>
        <label>Month:
            <select name="month" id="monthSelect">
                <option value="0" <?= $filterMonth == 0 ? 'selected' : '' ?>>All Months</option>
                <?php
                $months = ['January','February','March','April','May','June',
                           'July','August','September','October','November','December'];
                foreach ($months as $i => $m) {
                    $sel = $filterMonth == $i+1 ? 'selected' : '';
                    echo "<option value=\"" . ($i+1) . "\" $sel>$m</option>";
                }
                ?>
            </select>
        </label>
        <label class="search-label">Search:
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input
                    type="text"
                    name="search"
                    id="searchInput"
                    value="<?= htmlspecialchars($filterSearch) ?>"
                    placeholder="Name or Employee ID…"
                    autocomplete="off"
                >
                <button type="button" class="search-clear" id="clearBtn" title="Clear search"
                    style="<?= $filterSearch === '' ? 'display:none' : '' ?>">✕</button>
                <span class="search-spinner" id="searchSpinner" style="display:none;">⏳</span>
            </div>
        </label>
        <a href="admin_monthly_summary.php" class="reset-btn">↺ Reset</a>
    </form>

    <?php if ($filterSearch !== ''): ?>
    <div class="search-notice" id="searchNotice">
        Showing results for: <strong>"<?= htmlspecialchars($filterSearch) ?>"</strong>
        — <a href="#" onclick="clearSearch(); return false;">Clear search</a>
    </div>
    <?php else: ?>
    <div class="search-notice" id="searchNotice" style="display:none;"></div>
    <?php endif; ?>

    <!-- Monthly Tables -->
    <div id="reportContent">
        <?php if (empty($grouped)): ?>
            <div class="report-card">
                <p class="no-reports">No reports found for the selected period.</p>
            </div>
        <?php else: ?>
            <?php foreach ($grouped as $monthKey => $joRows):
                $monthTotals = $totalsPerMonth[$monthKey];
                $parts = explode('|', $monthKey);
                $monthLabel = $parts[1] . ' ' . $filterYear;
            ?>
            <div class="month-block">
                <div class="month-header">
                    <div class="month-title">
                        <span class="month-badge">📅 <?= htmlspecialchars($monthLabel) ?></span>
                    </div>
                    <?php
// ── PASTE THIS REPLACEMENT into admin_monthly_summary.php ──
// Find the <div class="month-totals"> block and replace the badge spans with these links:
?>

<div class="month-totals">
    <span class="badge-total"><?= $monthTotals['total'] ?> Reports</span>

    <a href="admin_report_status.php?status=Approved&year=<?= $filterYear ?>&month=<?= $parts[0] ?>"
       class="badge-approved" style="text-decoration:none;">
        ✅ <?= $monthTotals['approved'] ?> Approved
    </a>

    <a href="admin_report_status.php?status=Pending&year=<?= $filterYear ?>&month=<?= $parts[0] ?>"
       class="badge-pending" style="text-decoration:none;">
        ⏳ <?= $monthTotals['pending'] ?> Pending
    </a>

    <?php if ($monthTotals['returned'] > 0): ?>
    <a href="admin_report_status.php?status=Returned&year=<?= $filterYear ?>&month=<?= $parts[0] ?>"
       class="badge-returned" style="text-decoration:none;">
        🔄 <?= $monthTotals['returned'] ?> Returned
    </a>
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
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<style>
.main-content {
    margin-left: 250px; padding: 40px; min-height: 100vh;
    background: #f0f3f8; font-family: 'Segoe UI', Arial, sans-serif;
    transition: margin-left 0.3s ease;
}
.main-content.collapsed { margin-left: 70px; }

.page-header { margin-bottom: 28px; }
.page-header h2 { margin: 0; font-size: 26px; color: #0d2a52; font-weight: 700; }
.page-header p  { margin: 4px 0 0; color: #5a6a80; font-size: 14px; }

/* Stat Cards */
.stat-row { display: flex; gap: 16px; margin-bottom: 28px; flex-wrap: wrap; }
.stat-card {
    flex: 1; min-width: 170px; display: flex; align-items: center;
    gap: 14px; background: #fff; border-radius: 10px; padding: 18px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07); border-left: 5px solid #ccc;
}
.stat-card.total    { border-left-color: #003366; }
.stat-card.approved { border-left-color: #27ae60; }
.stat-card.pending  { border-left-color: #f39c12; }
.stat-card.returned { border-left-color: #e74c3c; }
.stat-icon { font-size: 28px; }
.stat-num  { display: block; font-size: 28px; font-weight: 700; color: #0d2a52; line-height: 1; }
.stat-label{ display: block; font-size: 12px; color: #7a8a9a; margin-top: 3px; text-transform: uppercase; letter-spacing: .5px; }

/* Filter Bar */
.filter-bar {
    display: flex; align-items: center; gap: 16px; background: #fff;
    border-radius: 10px; padding: 14px 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.07); margin-bottom: 28px; flex-wrap: wrap;
}
.filter-bar label { font-size: 13px; color: #444; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.filter-bar select {
    padding: 6px 12px; border: 1px solid #ccd3dd; border-radius: 6px;
    font-size: 13px; background: #f8f9fb; color: #333; cursor: pointer;
}
.reset-btn {
    padding: 7px 14px; background: #eee; color: #444; border-radius: 6px;
    font-size: 13px; font-weight: 600; text-decoration: none; transition: background 0.2s;
}
.reset-btn:hover { background: #ddd; }

/* Month Block */
.month-block { margin-bottom: 32px; }
.month-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; margin-bottom: 12px;
}
.month-badge { font-size: 15px; font-weight: 700; color: #003366; background: #dce8f8; padding: 6px 16px; border-radius: 20px; }
.month-totals { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.badge-total    { background:#003366; color:#fff; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; }
.badge-approved { background:#1a7a3c; color:#fff; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; }
.badge-pending  { background:#c87000; color:#fff; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; }
.badge-returned { background:#c0392b; color:#fff; padding:4px 12px; border-radius:12px; font-size:12px; font-weight:600; }

/* Table */
.report-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
.report-table-container { overflow-x: auto; }
table { border-collapse: collapse; width: 100%; min-width: 620px; }
th, td { padding: 11px 14px; text-align: left; border-bottom: 1px solid #e8ecf2; font-size: 14px; color: #1a1a1a; }
th { background: #003366; color: #fff; font-weight: 600; font-size: 13px; }
tr:nth-child(even) { background: #f7f9fc; }
.count-pill { display: inline-block; background: #003366; color: #fff; border-radius: 20px; padding: 2px 12px; font-size: 13px; font-weight: 700; }
.badge-sm { display: inline-block; border-radius: 10px; padding: 2px 10px; font-size: 12px; font-weight: 700; }
.badge-sm.approved { background: #e6f7ef; color: #1a7a3c; }
.badge-sm.pending  { background: #fef3cd; color: #c87000; }
.badge-sm.returned { background: #fde8e6; color: #c0392b; }
.muted { color: #bbb; }
.action-btn.view { background: #003366; color: #fff; padding: 5px 12px; border-radius: 5px; text-decoration: none; font-size: 13px; font-weight: 600; transition: background 0.2s; }
.action-btn.view:hover { background: #002244; }
.no-reports { text-align: center; color: #888; padding: 20px; font-size: 15px; }

/* Search input */
.search-label { display: flex; flex-direction: column; gap: 4px; font-size: 13px; color: #444; font-weight: 600; }
.search-wrap  { position: relative; display: flex; align-items: center; }
.search-icon  { position: absolute; left: 10px; font-size: 13px; pointer-events: none; }
.search-wrap input[type="text"] {
    padding: 6px 60px 6px 30px;
    border: 1px solid #ccd3dd;
    border-radius: 6px;
    font-size: 13px;
    background: #f8f9fb;
    color: #333;
    width: 210px;
    transition: border-color .2s, box-shadow .2s;
}
.search-wrap input[type="text"]:focus {
    outline: none;
    border-color: #003366;
    box-shadow: 0 0 0 3px rgba(0,51,102,.1);
    background: #fff;
}
.search-clear {
    position: absolute;
    right: 26px;
    background: none;
    border: none;
    font-size: 12px;
    color: #999;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}
.search-clear:hover { color: #c0392b; }
.search-spinner {
    position: absolute;
    right: 8px;
    font-size: 12px;
    animation: spin 1s linear infinite;
}
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.search-notice {
    margin-bottom: 16px;
    font-size: 13px;
    color: #555;
    background: #fff8e1;
    border: 1px solid #ffe082;
    padding: 8px 16px;
    border-radius: 8px;
}
.search-notice a { color: #003366; font-weight: 600; text-decoration: none; }
.emp-id {
    display: inline-block;
    font-size: 12px;
    font-family: 'Courier New', monospace;
    background: #eef2fa;
    color: #003366;
    padding: 2px 8px;
    border-radius: 4px;
    font-weight: 600;
}

/* Fade animation for content refresh */
#reportContent { transition: opacity 0.15s ease; }
#reportContent.loading { opacity: 0.4; pointer-events: none; }

/* Dark Mode */
body.dark .main-content       { background: #1a1a2e; color: #e0e0e0; }
body.dark .page-header h2     { color: #a0c4ff; }
body.dark .page-header p      { color: #8892a4; }
body.dark .stat-card          { background: #252540; }
body.dark .stat-num           { color: #a0c4ff; }
body.dark .filter-bar         { background: #252540; }
body.dark .filter-bar label   { color: #ccd; }
body.dark .filter-bar select  { background: #1a1a2e; color: #ddd; border-color: #444; }
body.dark .month-badge        { background: #1a2d4a; color: #7eb5f5; }
body.dark .report-card        { background: #252540; }
body.dark th                  { background: #0d1f3c; }
body.dark td                  { color: #ddd; border-bottom-color: #3a3a5a; }
body.dark tr:nth-child(even)  { background: #2a2a4a; }
body.dark .reset-btn          { background: #333; color: #ccc; }
body.dark .search-wrap input[type="text"] { background: #1a1a2e; color: #ddd; border-color: #444; }
body.dark .search-wrap input[type="text"]:focus { border-color: #4477aa; box-shadow: 0 0 0 3px rgba(68,119,170,.2); background: #12122a; }
body.dark .search-label { color: #ccd; }
body.dark .search-notice { background: #2a2a1a; border-color: #665500; color: #ccc; }
body.dark .emp-id { background: #1a2d4a; color: #7eb5f5; }

@media (max-width: 768px) {
    .main-content { margin-left: 0; padding: 20px; }
    .stat-row     { flex-direction: column; }
    .search-wrap input[type="text"] { width: 100%; }
}
</style>

<script>
(function () {
    const searchInput  = document.getElementById('searchInput');
    const clearBtn     = document.getElementById('clearBtn');
    const spinner      = document.getElementById('searchSpinner');
    const notice       = document.getElementById('searchNotice');
    const reportContent= document.getElementById('reportContent');
    const yearSelect   = document.getElementById('yearSelect');
    const monthSelect  = document.getElementById('monthSelect');

    let debounceTimer = null;

    // ── Instant search on keyup (debounced 350ms) ──────────────────────────
    searchInput.addEventListener('input', function () {
        const val = this.value;
        clearBtn.style.display  = val.length > 0 ? 'inline' : 'none';
        spinner.style.display   = 'inline';

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchResults(), 350);
    });

    // ── Year / Month dropdowns auto-submit immediately ─────────────────────
    yearSelect.addEventListener('change',  () => fetchResults());
    monthSelect.addEventListener('change', () => fetchResults());

    // ── Clear button ───────────────────────────────────────────────────────
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        fetchResults();
    });

    // ── Core fetch function ────────────────────────────────────────────────
    function fetchResults() {
        const year   = yearSelect.value;
        const month  = monthSelect.value;
        const search = searchInput.value.trim();

        // Update browser URL without reloading
        const url = new URL(window.location.href);
        url.searchParams.set('year',   year);
        url.searchParams.set('month',  month);
        url.searchParams.set('search', search);
        window.history.replaceState({}, '', url.toString());

        // Show loading state
        reportContent.classList.add('loading');
        spinner.style.display = 'inline';

        fetch('admin_monthly_summary_ajax.php?year=' + encodeURIComponent(year)
                + '&month='  + encodeURIComponent(month)
                + '&search=' + encodeURIComponent(search))
            .then(r => r.json())
            .then(data => {
                reportContent.innerHTML = data.html;
                updateNotice(search);
            })
            .catch(() => {
                // Fallback: submit the form normally
                document.getElementById('filterForm').submit();
            })
            .finally(() => {
                reportContent.classList.remove('loading');
                spinner.style.display = 'none';
            });
    }

    // ── Update the "Showing results for" notice ────────────────────────────
    function updateNotice(search) {
        if (search !== '') {
            notice.style.display = 'block';
            notice.innerHTML = 'Showing results for: <strong>"' + escHtml(search) + '"</strong>'
                + ' — <a href="#" onclick="document.getElementById(\'clearBtn\').click(); return false;">Clear search</a>';
        } else {
            notice.style.display = 'none';
            notice.innerHTML = '';
        }
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Expose clearSearch globally (for inline onclick fallback)
    window.clearSearch = function () {
        searchInput.value = '';
        clearBtn.style.display = 'none';
        fetchResults();
    };
})();
</script>

<?php include "footer.php"; ?>