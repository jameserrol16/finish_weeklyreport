<?php
session_name('jo_session');
session_start();
require "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'jo') {
    header("Location: login.php");
    exit;
}

require "jo_header.php";

// Update last activity
$stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();

// Get full name
$fullName = $_SESSION['username'];
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if ($data && !empty($data['full_name'])) {
    $fullName = $data['full_name'];
}

// ── ENSURE status + notif columns EXIST (run once, safe to repeat) ──────────
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending'");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS status_seen TINYINT(1) NOT NULL DEFAULT 0");
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS status_updated_at DATETIME NULL");

// ── MARK NOTIFICATIONS AS SEEN (AJAX endpoint) ──────────────────────────────
if (isset($_POST['mark_seen'])) {
    $uid = (int)$_SESSION['user_id'];
    $conn->query("UPDATE weekly_reports SET status_seen=1 WHERE user_id=$uid AND status_seen=0 AND status != 'pending'");
    echo json_encode(['ok' => true]);
    exit;
}

// Total Reports
$totalReports = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id=" . $_SESSION['user_id']);
if ($result) $totalReports = $result->fetch_assoc()['total'];

// Last Report
$lastReport   = '-';
$lastReportId = null;
$result = $conn->query("SELECT id, report_name FROM weekly_reports WHERE user_id=" . $_SESSION['user_id'] . " ORDER BY created_at DESC LIMIT 1");
if ($result && $row = $result->fetch_assoc()) {
    $lastReport   = $row['report_name'];
    $lastReportId = $row['id'];
}

// This Month Reports
$thisMonth = date('Y-m');
$monthlyReports = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id=" . $_SESSION['user_id'] . " AND DATE_FORMAT(created_at,'%Y-%m') = '$thisMonth'");
if ($result) $monthlyReports = $result->fetch_assoc()['total'];

// Average Reports
$totalMonths = max(1, $conn->query("SELECT COUNT(DISTINCT DATE_FORMAT(created_at,'%Y-%m')) as months FROM weekly_reports WHERE user_id=" . $_SESSION['user_id'])->fetch_assoc()['months']);
$averageReports = $totalReports > 0 ? round($totalReports / $totalMonths, 1) : 0;

// 6-month trend
$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $r = $conn->query("
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL $i MONTH), '%b %Y') as label, COUNT(*) as cnt
        FROM weekly_reports
        WHERE user_id=" . $_SESSION['user_id'] . "
          AND MONTH(created_at)=MONTH(DATE_SUB(NOW(), INTERVAL $i MONTH))
          AND YEAR(created_at)=YEAR(DATE_SUB(NOW(), INTERVAL $i MONTH))
    ");
    if ($r) $monthlyTrend[] = $r->fetch_assoc();
}
$trendLabels = json_encode(array_column($monthlyTrend, 'label'));
$trendData   = json_encode(array_column($monthlyTrend, 'cnt'));

// ── LEAVE DATA ───────────────────────────────────────────────────────────────
$totalLeave      = 0;
$leaveThisMonth  = 0;
$leaveLastMonth  = 0;
$recentLeave     = [];

$leaveCheck = $conn->query("SHOW TABLES LIKE 'leave_applications'");
if ($leaveCheck && $leaveCheck->num_rows > 0) {
    $uid = (int)$_SESSION['user_id'];

    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications WHERE user_id=$uid");
    if ($r) $totalLeave = $r->fetch_assoc()['cnt'];

    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications WHERE user_id=$uid AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    if ($r) $leaveThisMonth = $r->fetch_assoc()['cnt'];

    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications WHERE user_id=$uid AND MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))");
    if ($r) $leaveLastMonth = $r->fetch_assoc()['cnt'];

    $r = $conn->query("
        SELECT la.*, u.full_name, u.position
        FROM leave_applications la
        JOIN users u ON la.user_id = u.id
        WHERE la.user_id=$uid
        ORDER BY la.created_at DESC LIMIT 5
    ");
    if ($r) { while ($row = $r->fetch_assoc()) $recentLeave[] = $row; }
}
$leaveTrend = $leaveLastMonth > 0
    ? round((($leaveThisMonth - $leaveLastMonth) / $leaveLastMonth) * 100)
    : ($leaveThisMonth > 0 ? 100 : 0);

// ── REPORT STATUS DATA ───────────────────────────────────────────────────────
$uid = (int)$_SESSION['user_id'];

// Unread status notifications (approved/rejected not yet seen)
$unseenCount = 0;
$r = $conn->query("SELECT COUNT(*) as cnt FROM weekly_reports WHERE user_id=$uid AND status != 'pending' AND status_seen=0");
if ($r) $unseenCount = $r->fetch_assoc()['cnt'];

// Recent reports with status (last 8)
$recentReports = [];
$r = $conn->query("
    SELECT id, report_name, status, status_seen, created_at, status_updated_at
    FROM weekly_reports
    WHERE user_id=$uid
    ORDER BY created_at DESC
    LIMIT 8
");
if ($r) { while ($row = $r->fetch_assoc()) $recentReports[] = $row; }

// Status breakdown counts
$statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$r = $conn->query("SELECT status, COUNT(*) as cnt FROM weekly_reports WHERE user_id=$uid GROUP BY status");
if ($r) { while ($row = $r->fetch_assoc()) $statusCounts[$row['status']] = (int)$row['cnt']; }
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ── PAGE LAYOUT ── */
.page-title { font-size: 20px; font-weight: 700; color: var(--navy); margin-bottom: 4px; }
.page-sub   { font-size: 13px; color: var(--muted); margin-bottom: 26px; }

.section-label {
    font-size: 11px; font-weight: 700;
    letter-spacing: .08em; text-transform: uppercase;
    color: var(--muted); margin: 28px 0 12px;
    display: flex; align-items: center; gap: 8px;
}
.section-label::after { content: ''; flex: 1; height: 1px; background: var(--border); }

/* ── STAT CARDS ── */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 18px 18px 14px;
    box-shadow: var(--shadow);
    border-top: 3px solid transparent;
    text-decoration: none; color: inherit;
    transition: transform .25s, box-shadow .25s;
    display: flex; flex-direction: column;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1); }
.stat-card .card-icon  { font-size: 24px; margin-bottom: 10px; }
.stat-card h4          { font-size: 11.5px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.stat-card .value      { font-size: 28px; font-weight: 700; margin-top: 6px; font-family: 'IBM Plex Mono', monospace; }
.stat-card .sublabel   { font-size: 11px; color: var(--muted); margin-top: 3px; }

.c-navy   { border-top-color: var(--navy);   } .c-navy   .value { color: var(--navy);   }
.c-orange { border-top-color: var(--orange); } .c-orange .value { color: var(--orange); }
.c-blue   { border-top-color: var(--blue);   } .c-blue   .value { color: var(--blue);   }
.c-green  { border-top-color: var(--green);  } .c-green  .value { color: var(--green);  }
.c-red    { border-top-color: var(--red);    } .c-red    .value { color: var(--red);    }
.c-yellow { border-top-color: var(--yellow); } .c-yellow .value { color: #b7791f;       }

/* ── PANELS ── */
.panel { background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 20px; }
.panel-head {
    padding: 14px 18px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: #fafbfc;
}
.panel-head h3 { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.panel-head a  { font-size: 12px; color: var(--blue); text-decoration: none; }
.panel-head a:hover { text-decoration: underline; }
.panel-body { padding: 16px 18px; }
.chart-wrap { height: 220px; }

/* ── TWO COL ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
@media(max-width:900px) { .two-col { grid-template-columns: 1fr; } }

/* ── LEAVE TABLE ── */
.data-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.data-table th { text-align: left; padding: 7px 10px; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
.data-table td { padding: 8px 10px; border-bottom: 1px solid #f0f2f7; vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: #f8faff; }

.leave-type-pill { background: #e8f4ff; color: #0074d9; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }

/* ── STATUS PILLS ── */
.status-pill { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; letter-spacing: .03em; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; }
.status-pending  { background: #fff8e1; color: #b7791f; }
.status-approved { background: #e6f9ee; color: #276749; }
.status-rejected { background: #ffe8e8; color: #c53030; }

.empty-state { text-align: center; padding: 24px 0; color: var(--muted); font-size: 13px; }

/* ── MINI SUMMARY CARDS ── */
.mini-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px; }
.mini-card { background: var(--bg); border-radius: 8px; padding: 12px 14px; text-align: center; }
.mini-card .mc-val { font-size: 22px; font-weight: 700; font-family: 'IBM Plex Mono', monospace; }
.mini-card .mc-lbl { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin-top: 2px; }
.stat-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-top: 1px solid var(--border); font-size: 13px; }
.stat-row .sr-label { color: var(--muted); }

/* ── NOTIFICATION BELL ── */
.notif-bell-wrap {
    position: relative; display: inline-flex; align-items: center;
    cursor: pointer; margin-left: 8px;
}
.notif-badge {
    position: absolute; top: -5px; right: -7px;
    background: #e53e3e; color: #fff;
    font-size: 10px; font-weight: 700;
    min-width: 17px; height: 17px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    padding: 0 3px;
    animation: pulse-badge .8s ease-in-out infinite alternate;
    box-shadow: 0 0 0 2px var(--surface);
}
@keyframes pulse-badge {
    from { box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px rgba(229,62,62,.3); }
    to   { box-shadow: 0 0 0 2px var(--surface), 0 0 0 7px rgba(229,62,62,.0); }
}

/* ── NOTIFICATION DROPDOWN ── */
.notif-dropdown {
    display: none;
    position: absolute; top: calc(100% + 10px); right: 0;
    width: 340px;
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: 0 8px 32px rgba(0,0,0,.14);
    border: 1px solid var(--border);
    z-index: 9999;
    overflow: hidden;
}
.notif-dropdown.open { display: block; }
.notif-dd-head {
    padding: 12px 16px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    background: #fafbfc;
}
.notif-dd-head h4 { font-size: 12.5px; font-weight: 700; }
.notif-dd-head button {
    font-size: 11px; color: var(--blue); background: none; border: none;
    cursor: pointer; padding: 0; font-weight: 600;
}
.notif-item {
    padding: 10px 16px; border-bottom: 1px solid #f0f2f7;
    display: flex; align-items: flex-start; gap: 10px;
    transition: background .15s;
    text-decoration: none; color: inherit;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f8faff; }
.notif-item.unread { background: #f0f6ff; }
.notif-icon-wrap {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0; margin-top: 1px;
}
.ni-approved { background: #e6f9ee; }
.ni-rejected { background: #ffe8e8; }
.ni-pending  { background: #fff8e1; }
.notif-text  { flex: 1; min-width: 0; }
.notif-title { font-size: 12.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.notif-meta  { font-size: 11px; color: var(--muted); margin-top: 2px; }
.notif-dot   { width: 7px; height: 7px; background: var(--blue); border-radius: 50%; flex-shrink: 0; margin-top: 6px; }

/* ── ALERT BANNER ── */
.status-alert {
    border-radius: var(--radius); padding: 13px 18px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 16px; font-size: 13px; font-weight: 500;
    box-shadow: var(--shadow);
}
.alert-approved { background: #e6f9ee; border-left: 4px solid #38a169; color: #276749; }
.alert-rejected { background: #ffe8e8; border-left: 4px solid #e53e3e; color: #c53030; }
.alert-icon { font-size: 18px; }
.alert-close {
    margin-left: auto; background: none; border: none;
    cursor: pointer; font-size: 16px; color: inherit; opacity: .6; padding: 0 4px;
}
.alert-close:hover { opacity: 1; }

/* ── STATUS TABLE ROWS ── */
.new-status-row td { background: #f0f6ff !important; }
.report-name-cell { max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── STATUS BREAKDOWN PILLS ── */
.status-breakdown { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.sb-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    box-shadow: 0 1px 4px rgba(0,0,0,.07);
}
.sb-pill .sb-num { font-size: 16px; font-family: 'IBM Plex Mono', monospace; }
.sb-pending  { background: #fff8e1; color: #b7791f; border: 1px solid #f6e05e; }
.sb-approved { background: #e6f9ee; color: #276749; border: 1px solid #9ae6b4; }
.sb-rejected { background: #ffe8e8; color: #c53030; border: 1px solid #feb2b2; }

/* ── DARK MODE ── */
body.dark .page-title { color: #e2e8f0; }
body.dark .panel-head { background: #1e2133; }
body.dark .stat-card  { background: var(--surface); }
body.dark .data-table tr:hover td { background: rgba(255,255,255,.03); }
body.dark .mini-card  { background: #252836; }
body.dark .leave-type-pill { background: #1a2d4a; color: #90cdf4; }
body.dark .notif-dropdown { background: #1e2133; border-color: #2d3148; }
body.dark .notif-dd-head  { background: #252836; }
body.dark .notif-item.unread { background: #1a2d4a; }
body.dark .notif-item:hover  { background: rgba(255,255,255,.05); }
body.dark .new-status-row td { background: #1a2d4a !important; }
body.dark .alert-approved { background: #1a3328; }
body.dark .alert-rejected { background: #3b1a1a; }
body.dark .sb-pending  { background: #2d2410; }
body.dark .sb-approved { background: #0f2d1f; }
body.dark .sb-rejected { background: #2d1010; }
</style>

<div id="main" class="main">

    <div class="page-title">JO Dashboard</div>
    <div class="page-sub">Welcome back, <strong><?= htmlspecialchars($fullName) ?></strong> — here's your overview for today.</div>

    <!-- ═══ ALERT BANNERS for newly actioned reports ═══ -->
    <?php
    $newApproved = array_filter($recentReports, fn($r) => $r['status'] === 'approved' && !$r['status_seen']);
    $newRejected = array_filter($recentReports, fn($r) => $r['status'] === 'rejected' && !$r['status_seen']);
    ?>
    <?php if (!empty($newApproved)): ?>
    <div class="status-alert alert-approved" id="alertApproved">
        <span class="alert-icon">✅</span>
        <div>
            <strong><?= count($newApproved) ?> report(s) approved</strong> by the admin —
            <?= implode(', ', array_map(fn($r) => htmlspecialchars($r['report_name']), $newApproved)) ?>
        </div>
        <button class="alert-close" onclick="this.closest('.status-alert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <?php if (!empty($newRejected)): ?>
    <div class="status-alert alert-rejected" id="alertRejected">
        <span class="alert-icon">❌</span>
        <div>
            <strong><?= count($newRejected) ?> report(s) rejected</strong> by the admin —
            <?= implode(', ', array_map(fn($r) => htmlspecialchars($r['report_name']), $newRejected)) ?>
        </div>
        <button class="alert-close" onclick="this.closest('.status-alert').remove()">✕</button>
    </div>
    <?php endif; ?>

    <!-- ═══ OVERVIEW CARDS ═══ -->
    <div class="section-label">📊 Overview</div>
    <div class="cards-grid">
        <a href="jo_vreport.php?tab=reports" class="stat-card c-navy">
            <div class="card-icon">📝</div>
            <h4>Total Reports</h4>
            <div class="value"><?= $totalReports ?></div>
        </a>
        <a href="jo_vreport.php?tab=reports" class="stat-card c-orange">
            <div class="card-icon">📊</div>
            <h4>This Month</h4>
            <div class="value"><?= $monthlyReports ?></div>
        </a>
        <div class="stat-card c-blue">
            <div class="card-icon">📈</div>
            <h4>Monthly Average</h4>
            <div class="value"><?= $averageReports ?></div>
        </div>
        <?php if ($lastReportId): ?>
        <a href="jo_creport.php?id=<?= $lastReportId ?>" class="stat-card c-green">
        <?php else: ?>
        <a href="jo_vreport.php?tab=reports" class="stat-card c-green">
        <?php endif; ?>
            <div class="card-icon">📅</div>
            <h4>Last Report</h4>
            <div class="value" style="font-size:14px; margin-top:8px;"><?= htmlspecialchars($lastReport) ?></div>
        </a>
    </div>

    <!-- ═══ REPORT STATUS NOTIFICATIONS ═══ -->
    <div class="section-label" style="position:relative;">
        📋 Weekly Report Status
        <!-- Notification Bell -->
        <?php if ($unseenCount > 0): ?>
        <div class="notif-bell-wrap" id="notifBell" title="You have <?= $unseenCount ?> new notification(s)">
            <span style="font-size:18px;">🔔</span>
            <span class="notif-badge" id="notifBadge"><?= $unseenCount ?></span>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-dd-head">
                    <h4>Report Notifications</h4>
                    <button id="markAllSeen">Mark all as read</button>
                </div>
                <?php foreach ($recentReports as $rr):
                    if ($rr['status'] === 'pending') continue;
                    $isUnread = !$rr['status_seen'];
                    $iconClass = $rr['status'] === 'approved' ? 'ni-approved' : 'ni-rejected';
                    $icon      = $rr['status'] === 'approved' ? '✅' : '❌';
                    $statusLabel = ucfirst($rr['status']);
                    $updatedStr = $rr['status_updated_at'] ? date('M j, g:i A', strtotime($rr['status_updated_at'])) : date('M j, Y', strtotime($rr['created_at']));
                ?>
                <a href="jo_creport.php?id=<?= $rr['id'] ?>" class="notif-item <?= $isUnread ? 'unread' : '' ?>">
                    <div class="notif-icon-wrap <?= $iconClass ?>"><?= $icon ?></div>
                    <div class="notif-text">
                        <div class="notif-title"><?= htmlspecialchars($rr['report_name']) ?></div>
                        <div class="notif-meta">
                            <span class="status-pill status-<?= $rr['status'] ?>"><?= $statusLabel ?></span>
                            &nbsp;<?= $updatedStr ?>
                        </div>
                    </div>
                    <?php if ($isUnread): ?><div class="notif-dot"></div><?php endif; ?>
                </a>
                <?php endforeach; ?>
                <?php if (empty(array_filter($recentReports, fn($r) => $r['status'] !== 'pending'))): ?>
                    <div class="empty-state" style="padding:18px;">No notifications yet.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Status Breakdown Pills -->
    <div class="status-breakdown">
        <div class="sb-pill sb-pending">
            <span>⏳</span>
            <span class="sb-num"><?= $statusCounts['pending'] ?></span>
            <span>Pending</span>
        </div>
        <div class="sb-pill sb-approved">
            <span>✅</span>
            <span class="sb-num"><?= $statusCounts['approved'] ?></span>
            <span>Approved</span>
        </div>
        <div class="sb-pill sb-rejected">
            <span>❌</span>
            <span class="sb-num"><?= $statusCounts['rejected'] ?></span>
            <span>Rejected</span>
        </div>
    </div>

    <!-- Report Status Table -->
    <div class="panel" style="margin-bottom:24px;">
        <div class="panel-head">
            <h3>
                📄 Recent Report Statuses
                <?php if ($unseenCount > 0): ?>
                    <span class="status-pill status-rejected" style="font-size:10px; padding:2px 7px;"><?= $unseenCount ?> new</span>
                <?php endif; ?>
            </h3>
            <a href="jo_vreport.php?tab=reports">View All →</a>
        </div>
        <div class="panel-body" style="padding:0;">
            <?php if (empty($recentReports)): ?>
                <div class="empty-state">No reports submitted yet.</div>
            <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Report Name</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReports as $rr):
                        $isNew = ($rr['status'] !== 'pending' && !$rr['status_seen']);
                    ?>
                    <tr class="<?= $isNew ? 'new-status-row' : '' ?>">
                        <td>
                            <a href="jo_creport.php?id=<?= $rr['id'] ?>"
                               style="color:var(--navy); font-weight:600; text-decoration:none; font-size:12.5px;">
                                <div class="report-name-cell"><?= htmlspecialchars($rr['report_name']) ?></div>
                            </a>
                        </td>
                        <td style="color:var(--muted); font-size:12px; white-space:nowrap;">
                            <?= date('M j, Y', strtotime($rr['created_at'])) ?>
                        </td>
                        <td>
                            <?php
                                $icons = ['pending' => '⏳', 'approved' => '✅', 'rejected' => '❌'];
                                $icon  = $icons[$rr['status']] ?? '⏳';
                            ?>
                            <span class="status-pill status-<?= $rr['status'] ?>">
                                <?= $icon ?> <?= ucfirst($rr['status']) ?>
                            </span>
                            <?php if ($isNew): ?>
                                <span class="notif-dot" style="display:inline-block; vertical-align:middle; margin-left:4px;"></span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted); font-size:12px; white-space:nowrap;">
                            <?= $rr['status_updated_at'] ? date('M j, g:i A', strtotime($rr['status_updated_at'])) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ LEAVE APPLICATIONS ═══ -->
    <div class="section-label">📋 Leave Applications</div>

    <div class="two-col">
        <!-- Recent leave table -->
        <div class="panel">
            <div class="panel-head">
                <h3>🕐 Recent Leave Applications</h3>
                <a href="jo_vreport.php?tab=leave">View All →</a>
            </div>
            <div class="panel-body" style="padding:0;">
                <?php if (empty($recentLeave)): ?>
                    <div class="empty-state">No leave applications yet.</div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Date Filed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLeave as $lr): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:600;"><?= htmlspecialchars($lr['full_name'] ?? '') ?></div>
                                    <?php if (!empty($lr['position'])): ?>
                                        <div style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($lr['position']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="leave-type-pill"><?= htmlspecialchars($lr['leave_type'] ?? 'Leave') ?></span>
                                </td>
                                <td style="white-space:nowrap; color:var(--muted); font-size:12px;">
                                    <?= date('M j, Y', strtotime($lr['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave summary -->
        <div class="panel">
            <div class="panel-head"><h3>📅 Leave Summary</h3></div>
            <div class="panel-body">
                <div class="mini-cards">
                    <div class="mini-card" style="border-top:3px solid var(--blue);">
                        <div class="mc-val" style="color:var(--blue);"><?= $leaveThisMonth ?></div>
                        <div class="mc-lbl">This Month</div>
                    </div>
                    <div class="mini-card" style="border-top:3px solid var(--muted);">
                        <div class="mc-val" style="color:var(--muted);"><?= $leaveLastMonth ?></div>
                        <div class="mc-lbl">Last Month</div>
                    </div>
                </div>
                <div class="stat-row">
                    <span class="sr-label">Total Filed (All Time)</span>
                    <span style="font-weight:700; font-family:'IBM Plex Mono',monospace; color:var(--navy); font-size:15px;"><?= $totalLeave ?></span>
                </div>
                <div class="stat-row">
                    <span class="sr-label">vs Last Month</span>
                    <?php
                        $trendColor = $leaveTrend > 0 ? 'var(--red)' : ($leaveTrend < 0 ? 'var(--green)' : 'var(--muted)');
                        $trendIcon  = $leaveTrend > 0 ? '↑' : ($leaveTrend < 0 ? '↓' : '→');
                    ?>
                    <span style="font-weight:700; color:<?= $trendColor ?>; font-size:13px;"><?= $trendIcon ?> <?= abs($leaveTrend) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ TREND CHART ═══ -->
    <div class="section-label">📈 Report Trend</div>
    <div class="panel">
        <div class="panel-head">
            <h3>📉 Reports Trend (Last 6 Months)</h3>
            <a href="jo_vreport.php?tab=reports">View Reports →</a>
        </div>
        <div class="panel-body">
            <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
        </div>
    </div>

    <!-- ═══ BAR CHART ═══ -->
    <div class="section-label">📊 Analytics</div>
    <div class="panel">
        <div class="panel-head"><h3>Overview Bar Chart</h3></div>
        <div class="panel-body">
            <div style="height:220px;"><canvas id="barChart"></canvas></div>
        </div>
    </div>

</div><!-- /.main -->

<script>
// ── Notification Bell Toggle ──────────────────────────────────────────────────
const bell     = document.getElementById('notifBell');
const dropdown = document.getElementById('notifDropdown');
const badge    = document.getElementById('notifBadge');

if (bell) {
    bell.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (dropdown && !bell.contains(e.target)) dropdown.classList.remove('open');
    });
}

// ── Mark All Seen ─────────────────────────────────────────────────────────────
const markBtn = document.getElementById('markAllSeen');
if (markBtn) {
    markBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'mark_seen=1'
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                // Remove badge
                if (badge) badge.remove();
                // Remove unread highlights
                document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
                document.querySelectorAll('.new-status-row').forEach(el => el.classList.remove('new-status-row'));
                document.querySelectorAll('.notif-dot').forEach(el => el.remove());
                // Remove alert banners
                ['alertApproved','alertRejected'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.remove();
                });
                // Update panel header badge
                const newBadge = document.querySelector('.panel-head .status-pill.status-rejected');
                if (newBadge) newBadge.remove();
                dropdown.classList.remove('open');
                markBtn.textContent = 'All read ✓';
            }
        });
    });
}

// ── Trend Line Chart ──────────────────────────────────────────────────────────
const ctx1 = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(ctx1, {
    type: 'line',
    data: {
        labels: <?= $trendLabels ?>,
        datasets: [{
            label: 'Reports',
            data: <?= $trendData ?>,
            borderColor: '#001f3f',
            backgroundColor: 'rgba(0,31,63,.08)',
            borderWidth: 2.5,
            pointRadius: 5,
            pointBackgroundColor: '#001f3f',
            tension: 0.35,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// ── Bar Chart ─────────────────────────────────────────────────────────────────
const ctx2 = document.getElementById('barChart').getContext('2d');
const barChart = new Chart(ctx2, {
    type: 'bar',
    data: {
        labels: ['Total Reports', 'This Month', 'Monthly Average'],
        datasets: [{
            label: 'Report Metrics',
            data: [<?= $totalReports ?>, <?= $monthlyReports ?>, <?= $averageReports ?>],
            backgroundColor: ['#001f3f', '#ff8800', '#0074d9'],
            borderRadius: 6,
            barPercentage: 0.6,
            categoryPercentage: 0.6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// ── Dark Mode Chart Sync ──────────────────────────────────────────────────────
function applyChartDarkMode(isDark) {
    const c = isDark ? '#e2e8f0' : '#333';
    const g = isDark ? '#2d3148' : '#ddd';
    [trendChart, barChart].forEach(ch => {
        ch.options.scales.x.ticks = { color: c };
        ch.options.scales.y.ticks = { color: c };
        ch.options.scales.x.grid  = { color: g };
        ch.options.scales.y.grid  = { color: g };
        ch.update();
    });
}

if (localStorage.getItem("darkMode") === "enabled") applyChartDarkMode(true);

document.getElementById("darkToggle").addEventListener("click", function () {
    const isDark = document.body.classList.contains("dark");
    applyChartDarkMode(isDark);
});
</script>

<?php require "jo_footer.php"; ?>