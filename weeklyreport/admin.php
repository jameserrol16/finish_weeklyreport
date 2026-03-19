<?php
session_name('admin_session');
session_start();
require "db.php";

$activeNowJO = 0;
$result = $conn->query("
    SELECT COUNT(*) as total FROM users 
    WHERE role = 'jo' AND last_activity IS NOT NULL
      AND last_activity >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
");
if($result){ $row = $result->fetch_assoc(); $activeNowJO = $row['total']; }

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php"); exit;
}
if(isset($_SESSION['role']) && $_SESSION['role'] === 'jo') {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
}

$profilePic = "default.png";
$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $profilePic = $data['profile_picture'] ?? "default.png";
}
$fullName = $_SESSION['username'];
$checkFullName = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if ($checkFullName && $checkFullName->num_rows > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']); $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $fullName = $data['full_name'] ?? $_SESSION['username'];
}

// Overview counts
$totalJO = 0;
$r = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'jo'");
if ($r) { $totalJO = $r->fetch_assoc()['total']; }
$yourReports = 0;
$r = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id = ".$_SESSION['user_id']);
if($r){ $yourReports = $r->fetch_assoc()['total']; }
$allJoReports = 0;
$r = $conn->query("SELECT COUNT(*) as total FROM weekly_reports r JOIN users u ON r.user_id = u.id WHERE u.role = 'jo'");
if($r){ $allJoReports = $r->fetch_assoc()['total']; }
$newToday = 0;
$r = $conn->query("SELECT COUNT(*) as cnt FROM weekly_reports WHERE DATE(created_at) = CURDATE()");
if ($r) { $newToday = $r->fetch_assoc()['cnt']; }
$newThisWeek = 0;
$r = $conn->query("SELECT COUNT(*) as cnt FROM weekly_reports WHERE YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)");
if ($r) { $newThisWeek = $r->fetch_assoc()['cnt']; }

// ── LEAVE — recent 5, plus summary counts ────────────────────────────────
$recentLeave    = [];
$leaveTotalAll  = 0;
$leaveThisMonth = 0;
$leaveLastMonth = 0;
$leaveTableCheck = $conn->query("SHOW TABLES LIKE 'leave_applications'");
if ($leaveTableCheck && $leaveTableCheck->num_rows > 0) {
    $r2 = $conn->query("
        SELECT la.*, u.full_name, u.username, u.position
        FROM leave_applications la
        JOIN users u ON la.user_id = u.id
        ORDER BY la.created_at DESC LIMIT 5
    ");
    if ($r2) { while ($row = $r2->fetch_assoc()) $recentLeave[] = $row; }

    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications");
    if ($r) $leaveTotalAll = $r->fetch_assoc()['cnt'];
    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    if ($r) $leaveThisMonth = $r->fetch_assoc()['cnt'];
    $r = $conn->query("SELECT COUNT(*) as cnt FROM leave_applications WHERE MONTH(created_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))");
    if ($r) $leaveLastMonth = $r->fetch_assoc()['cnt'];
}
$leaveTrend = $leaveLastMonth > 0
    ? round((($leaveThisMonth - $leaveLastMonth) / $leaveLastMonth) * 100)
    : ($leaveThisMonth > 0 ? 100 : 0);

// ── MONTHLY SUMMARY — eval status counts ─────────────────────────────────
$conn->query("ALTER TABLE weekly_reports ADD COLUMN IF NOT EXISTS eval_status VARCHAR(20) DEFAULT 'Pending'");
$evalApproved = 0; $evalPending = 0; $evalReturned = 0;
$r = $conn->query("
    SELECT eval_status, COUNT(*) as cnt
    FROM weekly_reports r JOIN users u ON r.user_id = u.id
    WHERE u.role = 'jo' GROUP BY eval_status
");
if ($r) {
    while ($row = $r->fetch_assoc()) {
        if ($row['eval_status'] === 'Approved') $evalApproved = (int)$row['cnt'];
        if ($row['eval_status'] === 'Pending')  $evalPending  = (int)$row['cnt'];
        if ($row['eval_status'] === 'Returned') $evalReturned = (int)$row['cnt'];
    }
}

// Top 5 JO this month
$monthlyTopJO = [];
$r = $conn->query("
    SELECT u.full_name, u.username, COUNT(*) as report_count
    FROM weekly_reports wr JOIN users u ON wr.user_id = u.id
    WHERE u.role = 'jo' AND MONTH(wr.created_at)=MONTH(NOW()) AND YEAR(wr.created_at)=YEAR(NOW())
    GROUP BY wr.user_id ORDER BY report_count DESC LIMIT 5
");
if ($r) { while ($row = $r->fetch_assoc()) $monthlyTopJO[] = $row; }

// 6-month trend
$monthlyTrend = [];
for ($i = 5; $i >= 0; $i--) {
    $r = $conn->query("
        SELECT DATE_FORMAT(DATE_SUB(NOW(), INTERVAL $i MONTH), '%b %Y') as label, COUNT(*) as cnt
        FROM weekly_reports
        WHERE MONTH(created_at)=MONTH(DATE_SUB(NOW(), INTERVAL $i MONTH))
          AND YEAR(created_at)=YEAR(DATE_SUB(NOW(), INTERVAL $i MONTH))
    ");
    if ($r) { $monthlyTrend[] = $r->fetch_assoc(); }
}
$trendLabels = json_encode(array_column($monthlyTrend, 'label'));
$trendData   = json_encode(array_column($monthlyTrend, 'cnt'));
?>
<!DOCTYPE html>
<html>
<head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | NTC</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
    --navy:#001f3f; --navy2:#003366; --blue:#0074d9; --sky:#00bfff;
    --green:#28a745; --orange:#ff8800; --maroon:#800000;
    --red:#dc3545; --yellow:#ffc107;
    --bg:#f0f2f7; --surface:#ffffff; --border:#e2e8f0;
    --text:#1a202c; --muted:#718096;
    --shadow:0 1px 3px rgba(0,0,0,.08),0 4px 16px rgba(0,0,0,.06);
    --radius:10px;
}
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'IBM Plex Sans',sans-serif;background:var(--bg);color:var(--text);font-size:14px;}
#loader{position:fixed;inset:0;background:var(--bg);display:flex;align-items:center;justify-content:center;z-index:9999;transition:opacity .5s;}
.spinner{width:44px;height:44px;border:4px solid #ddd;border-top-color:var(--navy);border-radius:50%;animation:spin 1s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.sidebar{position:fixed;left:0;top:0;width:250px;height:100%;background:var(--navy);color:white;transition:width .3s ease;overflow:hidden;display:flex;flex-direction:column;z-index:100;}
.sidebar.collapsed{width:70px;}
.sidebar-header{display:flex;align-items:center;gap:10px;padding:16px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0;}
.sidebar-header img{width:36px;}
.sidebar-header span{font-size:15px;font-weight:700;white-space:nowrap;}
.sidebar.collapsed .sidebar-header span{display:none;}
.sidebar-profile{text-align:center;padding:18px 10px;border-bottom:1px solid rgba(255,255,255,.1);flex-shrink:0;}
.sidebar-profile img{width:56px;height:56px;border-radius:40%;object-fit:cover;border:2px solid rgba(255,255,255,.4);}
.sidebar-profile h4{margin:8px 0 3px;font-size:13px;}
.sidebar-profile small{font-size:11px;color:#a0aec0;}
.sidebar-profile a{display:block;font-size:11px;color:#90cdf4;text-decoration:none;margin-top:5px;}
.sidebar.collapsed .sidebar-profile h4,.sidebar.collapsed .sidebar-profile small,.sidebar.collapsed .sidebar-profile a{display:none;}
.sidebar.collapsed .sidebar-profile{padding:12px 0;}
.sidebar-nav{flex:1;overflow-y:auto;overflow-x:hidden;padding:8px 0;}
.sidebar a.menu{display:flex;align-items:center;gap:11px;padding:10px 18px;color:rgba(255,255,255,.85);text-decoration:none;font-size:13.5px;border-left:3px solid transparent;transition:all .25s;white-space:nowrap;}
.sidebar a.menu:hover{background:var(--navy2);border-left-color:var(--sky);}
.sidebar.collapsed a.menu{justify-content:center;padding:10px;border-left:none;}
.sidebar.collapsed a.menu .text{display:none;}
.sidebar.collapsed a.menu:hover::after{content:attr(data-title);position:absolute;left:72px;background:var(--navy2);padding:5px 10px;border-radius:4px;font-size:12px;white-space:nowrap;z-index:999;}
.sidebar-logout{flex-shrink:0;padding:8px 0 12px;border-top:1px solid rgba(255,255,255,.08);}
.logout{color:#fc8181 !important;}
.header{margin-left:250px;padding:12px 24px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;transition:margin .3s;position:sticky;top:0;z-index:50;box-shadow:0 1px 4px rgba(0,0,0,.06);}
.header.collapsed{margin-left:70px;}
.toggle-btn{cursor:pointer;font-size:20px;line-height:1;}
.dark-btn{padding:5px 12px;border:none;cursor:pointer;background:var(--navy);color:white;border-radius:6px;font-size:13px;transition:opacity .2s;}
.dark-btn:hover{opacity:.8;}
.main{margin-left:250px;padding:28px 28px 40px;transition:margin .3s;min-height:calc(100vh - 49px);}
.main.collapsed{margin-left:70px;}
.page-title{font-size:20px;font-weight:700;color:var(--navy);margin-bottom:4px;}
.page-sub{font-size:13px;color:var(--muted);margin-bottom:26px;}
.section-label{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin:28px 0 12px;display:flex;align-items:center;gap:8px;}
.section-label::after{content:'';flex:1;height:1px;background:var(--border);}
.cards-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:20px;}
.stat-card{background:var(--surface);border-radius:var(--radius);padding:18px 18px 14px;box-shadow:var(--shadow);border-top:3px solid transparent;text-decoration:none;color:inherit;transition:transform .25s,box-shadow .25s;display:flex;flex-direction:column;position:relative;overflow:hidden;}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.1);}
.stat-card .card-icon{font-size:24px;margin-bottom:10px;}
.stat-card h4{font-size:11.5px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;}
.stat-card .value{font-size:28px;font-weight:700;margin-top:6px;font-family:'IBM Plex Mono',monospace;}
.stat-card .sublabel{font-size:11px;color:var(--muted);margin-top:3px;}
.c-navy{border-top-color:var(--navy);} .c-navy .value{color:var(--navy);}
.c-maroon{border-top-color:var(--maroon);} .c-maroon .value{color:var(--maroon);}
.c-orange{border-top-color:var(--orange);} .c-orange .value{color:var(--orange);}
.c-green{border-top-color:var(--green);} .c-green .value{color:var(--green);}
.c-blue{border-top-color:var(--blue);} .c-blue .value{color:var(--blue);}
.c-red{border-top-color:var(--red);} .c-red .value{color:var(--red);}
.c-yellow{border-top-color:var(--yellow);} .c-yellow .value{color:#b7791f;}
.c-sky{border-top-color:var(--sky);} .c-sky .value{color:#0087b3;}
.live-badge{display:inline-flex;align-items:center;gap:5px;font-size:10px;color:var(--green);font-weight:700;margin-top:4px;text-transform:uppercase;letter-spacing:.06em;}
.live-dot{width:7px;height:7px;background:var(--green);border-radius:50%;animation:pulse 1.5s infinite;}
@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(40,167,69,.6);}70%{box-shadow:0 0 0 7px rgba(40,167,69,0);}100%{box-shadow:0 0 0 0 rgba(40,167,69,0);}}
@keyframes flashUpdate{0%{background:rgba(40,167,69,.15);}100%{background:var(--surface);}}
.flash{animation:flashUpdate .7s ease;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
@media(max-width:900px){.two-col{grid-template-columns:1fr;}}
.panel{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
.panel-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:#fafbfc;}
.panel-head h3{font-size:13.5px;font-weight:700;}
.panel-head a{font-size:12px;color:var(--blue);text-decoration:none;}
.panel-head a:hover{text-decoration:underline;}
.panel-body{padding:16px 18px;}
.data-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.data-table th{text-align:left;padding:7px 10px;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--muted);border-bottom:1px solid var(--border);}
.data-table td{padding:8px 10px;border-bottom:1px solid #f0f2f7;vertical-align:middle;}
.data-table tr:last-child td{border-bottom:none;}
.data-table tr:hover td{background:#f8faff;}
.leave-type-pill{background:#e8f4ff;color:#0074d9;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;}
.eval-dist{display:flex;align-items:center;gap:20px;padding:8px 0;}
.eval-donut-wrap{width:130px;height:130px;flex-shrink:0;}
.eval-legend{flex:1;display:flex;flex-direction:column;gap:10px;}
.legend-item{display:flex;align-items:center;gap:8px;font-size:12.5px;}
.legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.top-table{width:100%;border-collapse:collapse;font-size:12.5px;}
.top-table td{padding:7px 10px;border-bottom:1px solid #f0f2f7;}
.top-table tr:last-child td{border-bottom:none;}
.rank-num{font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--muted);width:24px;}
.bar-wrap{background:#eef0f5;border-radius:20px;height:7px;overflow:hidden;}
.bar-fill{height:100%;border-radius:20px;background:var(--navy);transition:width 1s ease;}
.chart-wrap{height:220px;}
.empty-state{text-align:center;padding:24px 0;color:var(--muted);font-size:13px;}
/* summary mini cards */
.mini-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;}
.mini-card{background:var(--bg);border-radius:8px;padding:12px 14px;text-align:center;}
.mini-card .mc-val{font-size:22px;font-weight:700;font-family:'IBM Plex Mono',monospace;}
.mini-card .mc-lbl{font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-top:2px;}
.stat-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-top:1px solid var(--border);font-size:13px;}
.stat-row .sr-label{color:var(--muted);}
/* dark */
body.dark{--bg:#0f1117;--surface:#1a1d27;--border:#2d3148;--text:#e2e8f0;--muted:#718096;}
body.dark .header{background:var(--surface);}
body.dark .sidebar{background:#07091a;}
body.dark .panel-head{background:#1e2133;}
body.dark .data-table tr:hover td{background:rgba(255,255,255,.03);}
body.dark .mini-card{background:#252836;}
body.dark .leave-type-pill{background:#1a2d4a;color:#90cdf4;}
#activeJoTooltip{display:none;position:fixed;background:var(--navy);color:white;border-radius:8px;padding:10px 14px;min-width:180px;max-width:260px;box-shadow:0 6px 20px rgba(0,0,0,.25);z-index:99999;font-size:12px;pointer-events:none;}
</style>
</head>
<body>

<div id="loader"><div class="spinner"></div></div>

<div class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <a href="admin.php"><img src="ntc-logo.png"></a>
    <span>NTC Admin</span>
  </div>
  <div class="sidebar-profile">
    <a href="edit_account.php">
      <img src="uploads/<?= htmlspecialchars($profilePic) ?>" onmouseover="this.style.opacity='.75'" onmouseout="this.style.opacity='1'" style="cursor:pointer;transition:opacity .3s;">
    </a>
    <h4><?= htmlspecialchars($fullName) ?></h4>
    <small>Administrator</small>
    <a href="edit_account.php">Edit Account</a>
  </div>
  <div class="sidebar-nav">
    <a href="admin.php"                 class="menu" data-title="Dashboard">          <span class="icon">🏠</span><span class="text">Dashboard</span></a>
    <a href="index.php"                 class="menu" data-title="Create Report">      <span class="icon">📝</span><span class="text">Create Report</span></a>
    <a href="admin_leave.php"           class="menu" data-title="Leave Applications"> <span class="icon">📋</span><span class="text">Application for Leave</span></a>
    <a href="myreport.php"              class="menu" data-title="My Reports">         <span class="icon">📄</span><span class="text">My Reports</span></a>
    <a href="admin_monthly_summary.php" class="menu" data-title="Monthly Summary">    <span class="icon">📈</span><span class="text">Monthly Summary</span></a>
    <a href="jo_report.php"             class="menu" data-title="All JO Reports">     <span class="icon">📊</span><span class="text">All JO Reports</span></a>
    <a href="create_user.php"           class="menu" data-title="Create JO Account">  <span class="icon">➕</span><span class="text">Create JO Account</span></a>
    <a href="manage_users.php"          class="menu" data-title="Manage JO Accounts"> <span class="icon">👥</span><span class="text">Manage JO Accounts</span></a>
  </div>
  <div class="sidebar-logout">
    <a href="logout.php" class="menu logout" data-title="Logout">
      <span class="icon">🚪</span><span class="text">Logout</span>
    </a>
  </div>
</div>

<div class="header" id="header">
  <span class="toggle-btn" onclick="toggleSidebar()">☰</span>
  <button id="darkToggle" class="dark-btn">🌙 Dark Mode</button>
</div>

<div class="main" id="main">

  <div class="page-title">Administrator Dashboard</div>
  <div class="page-sub">Welcome back, <strong><?= htmlspecialchars($fullName) ?></strong> — here's your overview for today.</div>

  <!-- ═══ SECTION 1 · OVERVIEW ═══ -->
  <div class="section-label">📊 Overview</div>
  <div class="cards-grid">
    <a href="manage_users.php" class="stat-card c-navy">
      <div class="card-icon">👤</div><h4>Total JO Accounts</h4>
      <div class="value"><?= $totalJO ?></div>
    </a>
    <a href="myreport.php" class="stat-card c-maroon">
      <div class="card-icon">📝</div><h4>Your Reports</h4>
      <div class="value"><?= $yourReports ?></div>
    </a>
    <a href="jo_report.php" class="stat-card c-orange">
      <div class="card-icon">📊</div><h4>All JO Reports</h4>
      <div class="value"><?= $allJoReports ?></div>
    </a>
    <div class="stat-card c-green" id="activeJoCard" style="cursor:default;">
      <div class="card-icon">🟢</div><h4>Active JO Now</h4>
      <div class="value" id="activeJoCount"><?= $activeNowJO ?></div>
      <div class="live-badge"><span class="live-dot"></span> Live</div>
    </div>
    <a href="jo_report.php?filter=today" class="stat-card c-blue">
      <div class="card-icon">🆕</div><h4>New Reports Today</h4>
      <div class="value"><?= $newToday ?></div>
    </a>
    <a href="jo_report.php?filter=week" class="stat-card c-sky">
      <div class="card-icon">📅</div><h4>Reports This Week</h4>
      <div class="value"><?= $newThisWeek ?></div>
    </a>
  </div>

  <!-- ═══ SECTION 2 · LEAVE APPLICATIONS ═══ -->
  <div class="section-label">📋 Leave Applications</div>
  <div class="two-col">

    <!-- Recent leave table — NO status column -->
    <div class="panel">
      <div class="panel-head">
        <h3>🕐 Recent Leave Applications</h3>
        <a href="myreport.php?tabLeave">View All →</a>
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
                  <div style="font-weight:600;"><?= htmlspecialchars($lr['full_name'] ?? $lr['username']) ?></div>
                  <?php if (!empty($lr['position'])): ?>
                    <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($lr['position']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="leave-type-pill"><?= htmlspecialchars($lr['leave_type'] ?? 'Leave') ?></span>
                </td>
                <td style="white-space:nowrap;color:var(--muted);font-size:12px;">
                  <?= date('M j, Y', strtotime($lr['created_at'])) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Leave summary panel -->
    <div class="panel">
      <div class="panel-head"><h3>📅 Leave Summary</h3></div>
      <div class="panel-body">

        <!-- This month / last month mini cards -->
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

        <!-- Total all time -->
        <div class="stat-row">
          <span class="sr-label">Total Filed (All Time)</span>
          <span style="font-weight:700;font-family:'IBM Plex Mono',monospace;color:var(--navy);font-size:15px;"><?= $leaveTotalAll ?></span>
        </div>

        <!-- Month-over-month trend -->
        <div class="stat-row">
          <span class="sr-label">vs Last Month</span>
          <?php
            $trendColor = $leaveTrend > 0 ? 'var(--red)' : ($leaveTrend < 0 ? 'var(--green)' : 'var(--muted)');
            $trendIcon  = $leaveTrend > 0 ? '↑' : ($leaveTrend < 0 ? '↓' : '→');
          ?>
          <span style="font-weight:700;color:<?= $trendColor ?>;font-size:13px;"><?= $trendIcon ?> <?= abs($leaveTrend) ?>%</span>
        </div>

      </div>
    </div>
  </div>

  <!-- ═══ SECTION 3 · MONTHLY SUMMARY — eval status ═══ -->
  <div class="section-label">📈 Monthly Summary</div>
  <div class="cards-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px;">
    <a href="admin_report_status.php?status=Approved" class="stat-card c-green">
      <div class="card-icon">✅</div><h4>Approved</h4>
      <div class="value"><?= $evalApproved ?></div>
      <div class="sublabel">Reports evaluated</div>
    </a>
    <a href="admin_report_status.php?status=Pending" class="stat-card c-yellow">
      <div class="card-icon">⏳</div><h4>Pending</h4>
      <div class="value"><?= $evalPending ?></div>
      <div class="sublabel">Awaiting evaluation</div>
    </a>
    <a href="admin_report_status.php?status=Returned" class="stat-card c-red">
      <div class="card-icon">🔄</div><h4>Returned</h4>
      <div class="value"><?= $evalReturned ?></div>
      <div class="sublabel">Needs revision</div>
    </a>
  </div>

  <div class="two-col">
    <div class="panel">
      <div class="panel-head"><h3>📊 Report Status Distribution</h3></div>
      <div class="panel-body">
        <div class="eval-dist">
          <div class="eval-donut-wrap"><canvas id="evalDonut"></canvas></div>
          <div class="eval-legend">
            <div class="legend-item"><span class="legend-dot" style="background:#28a745;"></span><span>Approved — <strong><?= $evalApproved ?></strong></span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#ffc107;"></span><span>Pending — <strong><?= $evalPending ?></strong></span></div>
            <div class="legend-item"><span class="legend-dot" style="background:#dc3545;"></span><span>Returned — <strong><?= $evalReturned ?></strong></span></div>
          </div>
        </div>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head">
        <h3>🏆 Top JO Reporters This Month</h3>
        <a href="admin_monthly_summary.php">Full Summary →</a>
      </div>
      <div class="panel-body" style="padding:8px 18px;">
        <?php if (empty($monthlyTopJO)): ?>
          <div class="empty-state">No JO reports this month yet.</div>
        <?php else:
          $maxCount = max(array_column($monthlyTopJO, 'report_count'));
          foreach ($monthlyTopJO as $i => $jo):
            $pct = $maxCount > 0 ? round(($jo['report_count'] / $maxCount) * 100) : 0;
        ?>
          <table class="top-table" style="width:100%;margin-bottom:2px;">
            <tr>
              <td class="rank-num">#<?= $i+1 ?></td>
              <td style="padding:8px 10px;">
                <div style="font-weight:600;font-size:13px;margin-bottom:5px;"><?= htmlspecialchars($jo['full_name'] ?? $jo['username']) ?></div>
                <div class="bar-wrap"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
              </td>
              <td style="text-align:right;padding:8px 10px;font-family:'IBM Plex Mono',monospace;font-weight:700;font-size:14px;color:var(--navy);white-space:nowrap;">
                <?= $jo['report_count'] ?> <span style="font-size:10px;font-weight:400;color:var(--muted);">report<?= $jo['report_count'] != 1 ? 's' : '' ?></span>
              </td>
            </tr>
          </table>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>

  <div class="panel" style="margin-bottom:20px;">
    <div class="panel-head"><h3>📉 Reports Trend (Last 6 Months)</h3></div>
    <div class="panel-body"><div class="chart-wrap"><canvas id="trendChart"></canvas></div></div>
  </div>

  <!-- ═══ SECTION 4 · ANALYTICS CHART ═══ -->
  <div class="section-label">📊 Analytics Chart</div>
  <div class="panel">
    <div class="panel-head"><h3>Overview Bar Chart</h3></div>
    <div class="panel-body"><div style="height:260px;"><canvas id="reportsChart"></canvas></div></div>
  </div>

</div><!-- /.main -->

<div id="activeJoTooltip">
  <div style="font-weight:700;margin-bottom:6px;border-bottom:1px solid rgba(255,255,255,.2);padding-bottom:6px;">🟢 Online Now</div>
  <div id="activeJoList">Loading...</div>
</div>

<script>
function toggleSidebar() {
    ['sidebar','header','main'].forEach(id => document.getElementById(id).classList.toggle('collapsed'));
    localStorage.setItem('sidebarCollapsed', document.getElementById('sidebar').classList.contains('collapsed'));
}
window.addEventListener('load', function () {
    const loader = document.getElementById('loader');
    loader.style.opacity = '0';
    setTimeout(() => loader.style.display = 'none', 500);
    if (localStorage.getItem('sidebarCollapsed') === 'true')
        ['sidebar','header','main'].forEach(id => document.getElementById(id).classList.add('collapsed'));
});
const darkToggle = document.getElementById('darkToggle');
function applyDark(on) {
    document.body.classList.toggle('dark', on);
    darkToggle.innerHTML = on ? '☀️ Light Mode' : '🌙 Dark Mode';
    const c = on ? '#e2e8f0' : '#333', g = on ? '#2d3148' : '#ddd';
    [reportsChart, trendChart].forEach(ch => {
        ch.options.scales.x.ticks={color:c}; ch.options.scales.y.ticks={color:c};
        ch.options.scales.x.grid={color:g};  ch.options.scales.y.grid={color:g};
        ch.update();
    });
}
if (localStorage.getItem('darkMode') === 'enabled') applyDark(true);
darkToggle.addEventListener('click', function () {
    const on = !document.body.classList.contains('dark');
    localStorage.setItem('darkMode', on ? 'enabled' : 'disabled');
    applyDark(on);
});
const ctx1 = document.getElementById('reportsChart').getContext('2d');
const reportsChart = new Chart(ctx1, {
    type:'bar',
    data:{labels:['Total JO Accounts','Your Reports','All JO Reports','Active JO'],datasets:[{label:'Count',data:[<?= $totalJO ?>,<?= $yourReports ?>,<?= $allJoReports ?>,<?= $activeNowJO ?>],backgroundColor:['#001f3f','#800000','#ff8800','#28a745'],borderRadius:6}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});
const ctx2 = document.getElementById('evalDonut').getContext('2d');
const evalTotal = <?= $evalApproved + $evalPending + $evalReturned ?>;
new Chart(ctx2, {
    type:'doughnut',
    data:{labels:['Approved','Pending','Returned'],datasets:[{data:[<?= $evalApproved ?>,<?= $evalPending ?>,<?= $evalReturned ?>],backgroundColor:['#28a745','#ffc107','#dc3545'],borderWidth:2,borderColor:'#ffffff'}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'70%',plugins:{legend:{display:false},tooltip:{callbacks:{label:ctx=>{const pct=evalTotal>0?Math.round(ctx.parsed/evalTotal*100):0;return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;}}}}}
});
const ctx3 = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(ctx3, {
    type:'line',
    data:{labels:<?= $trendLabels ?>,datasets:[{label:'Reports',data:<?= $trendData ?>,borderColor:'#001f3f',backgroundColor:'rgba(0,31,63,.08)',borderWidth:2.5,pointRadius:5,pointBackgroundColor:'#001f3f',tension:0.35,fill:true}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
});
let prevCount = <?= $activeNowJO ?>, onlineUsers = [];
function fetchActiveJO() {
    fetch('active_jo_account.php').then(r=>r.json()).then(data=>{
        const n=data.activeNowJO; onlineUsers=data.onlineUsers||[];
        if(n!==prevCount){
            document.getElementById('activeJoCount').textContent=n;
            const card=document.getElementById('activeJoCard');
            card.classList.remove('flash'); void card.offsetWidth; card.classList.add('flash');
            reportsChart.data.datasets[0].data[3]=n; reportsChart.update('none');
            prevCount=n;
        }
    }).catch(()=>{});
}
const activeCard=document.getElementById('activeJoCard');
const tooltip=document.getElementById('activeJoTooltip');
const listEl=document.getElementById('activeJoList');
activeCard.addEventListener('mouseenter',function(){
    listEl.innerHTML=onlineUsers.length===0?'<em style="color:#aaa;">No JO currently online</em>':onlineUsers.map(n=>`<div style="padding:2px 0;display:flex;align-items:center;gap:6px;"><span style="width:6px;height:6px;background:#28a745;border-radius:50%;display:inline-block;"></span>${n}</div>`).join('');
    const r=activeCard.getBoundingClientRect();
    tooltip.style.left=r.left+'px'; tooltip.style.top=(r.bottom+8)+'px'; tooltip.style.display='block';
});
activeCard.addEventListener('mousemove',function(){
    const r=activeCard.getBoundingClientRect();
    tooltip.style.left=r.left+'px'; tooltip.style.top=(r.bottom+8)+'px';
});
activeCard.addEventListener('mouseleave',()=>tooltip.style.display='none');
setInterval(fetchActiveJO,5000);
</script>
</body>
</html>