<?php
session_name('admin_session');
session_start();
require "db.php";
$activeNowJO = 0;
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE role = 'jo' 
      AND last_activity >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
");
if($result){
    $row = $result->fetch_assoc();
    $activeNowJO = $row['total'];
}
// Allow ADMIN only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
if(isset($_SESSION['role']) && $_SESSION['role'] === 'jo') {
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
}
// Profile picture (safe even if column doesn't exist)
$profilePic = "default.png";

$checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($checkColumn && $checkColumn->num_rows > 0) {
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $profilePic = $data['profile_picture'] ?? "default.png";
}
// Fetch full_name for the greeting
$fullName = $_SESSION['username']; // fallback

$checkFullName = $conn->query("SHOW COLUMNS FROM users LIKE 'full_name'");
if ($checkFullName && $checkFullName->num_rows > 0) {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $fullName = $data['full_name'] ?? $_SESSION['username'];
}
// Total JO accounts
$totalJO = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'jo'");
if ($result) {
    $row = $result->fetch_assoc();
    $totalJO = $row['total'];
}

// Your Reports (created by logged-in admin)
$yourReports = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM weekly_reports WHERE user_id = ".$_SESSION['user_id']);
if($result){
    $row = $result->fetch_assoc();
    $yourReports = $row['total'];
}

// All JO Reports (created by users with role 'jo')
$allJoReports = 0;
$result = $conn->query("
    SELECT COUNT(*) as total 
    FROM weekly_reports r 
    JOIN users u ON r.user_id = u.id 
    WHERE u.role = 'jo'
");
if($result){
    $row = $result->fetch_assoc();
    $allJoReports = $row['total'];
}

?>
<!DOCTYPE html>
<html>
<head>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<meta charset="UTF-8">
<title>Admin Dashboard | NTC</title>

<style>
body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background-color: #f4f6f9;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 250px;
    height: 100%;
    background: #001f3f;
    color: white;
    transition: width 0.3s ease;
    overflow-x: hidden;
}
/* ANALYTICS SECTION */
/* Cards container */
.analytics-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
}

/* Individual card */
.analytics-card {
    flex: 1;
    min-width: 180px;
    background: white;
    padding: 20px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    position: relative;
    transition: transform 0.3s;
}

.analytics-card:hover {
    transform: translateY(-3px);
}

/* Icon inside card */
.card-icon {
    font-size: 28px;
    margin-bottom: 10px;
}

/* Different colors */
.card-blue { border-left: 5px solid #001f3f; color: #001f3f; }
.card-orange { border-left: 5px solid #ff8800; color: #ff8800; }
.card-maroon { border-left: 5px solid maroon; color: maroon; }
.card-green { border-left: 5px solid #28a745; color: #28a745; }
.card-purple { border-left: 5px solid purple; color: #28a745; }
/* Card text */
.analytics-card h4 {
    margin: 0;
    font-size: 14px;
    color: inherit;
}

.analytics-card p {
    font-size: 22px;
    font-weight: bold;
    margin-top: 8px;
    color: inherit;
}

/* Chart container */
.analytics-chart {
    background: white;
    padding: 20px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}


.sidebar.collapsed {
    width: 70px;
}

.sidebar-header {
    display: flex;
    align-items: center;
    padding: 15px;
    gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar-header img {
    width: 40px;
}

.sidebar-header span {
    font-size: 16px;
    font-weight: bold;
}

.sidebar.collapsed .sidebar-header span {
    display: none;
}

/* PROFILE SECTION */
.sidebar-profile {
    text-align: center;
    padding: 20px 10px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.sidebar-profile img {
    width: 60px;
    height: 60px;
    border-radius: 40%;
    object-fit: cover;
    border: 3px solid white;
}

.sidebar-profile h4 {
    margin: 10px 0 5px;
    font-size: 14px;
}
/* MENU ICON + TEXT LAYOUT */
/* MENU ICON + TEXT LAYOUT (animated & tooltip) */
.sidebar a.menu {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    transition: all 0.3s ease; /* smooth animation */
}

/* Hide text when collapsed */
.sidebar.collapsed a.menu .text {
    display: none;
}

/* Center icons when collapsed */
.sidebar.collapsed a.menu {
    justify-content: center;
    position: relative; /* for tooltip */
}

/* Tooltip on hover when collapsed */
.sidebar.collapsed a.menu:hover::after {
    content: attr(data-title);
    position: absolute;
    left: 70px;
    background: #003366;
    padding: 6px 10px;
    border-radius: 4px;
    white-space: nowrap;
    font-size: 12px;
    z-index: 1000;
}

/* Hover effect for expanded menu */
.sidebar a.menu:hover {
    background: #003366;
    border-left: 4px solid #00bfff;
}
/* Logout positioning */
.sidebar-logout {
    position: absolute;
    bottom: 20px;
    width: 100%;
}

.logout {
    color: #ffcccc !important;
}
.sidebar-profile small {
    font-size: 12px;
    color: #ccc;
}

.sidebar-profile a {
    display: block;
    font-size: 12px;
    color: #ddd;
    text-decoration: none;
    margin-top: 6px;
}

.sidebar-profile a:hover {
    color: white;
}

/* Hide name + links when collapsed */
.sidebar.collapsed .sidebar-profile h4,
.sidebar.collapsed .sidebar-profile small,
.sidebar.collapsed .sidebar-profile a {
    display: none;
}

/* Center profile picture */
.sidebar.collapsed .sidebar-profile {
    padding: 15px 0;
}

/* MENU */

.sidebar a.menu {
    display: block;
    padding: 12px 20px;
    color: white;
    text-decoration: none;
    font-size: 14px;
    border-left: 4px solid transparent;
}
.sidebar.collapsed a.menu {
    position: relative;
}

.sidebar.collapsed a.menu:hover::after {
    content: attr(data-title);
    position: absolute;
    left: 70px;
    background: #003366;
    padding: 6px 10px;
    border-radius: 4px;
    white-space: nowrap;
    font-size: 12px;
}
.sidebar a.menu:hover {
    background: #003366;
    border-left: 4px solid #00bfff;
}

/* HEADER */
.header {
    margin-left: 250px;
    padding: 15px 25px;
    background: white;
    border-bottom: 1px solid #ddd;
    transition: 0.3s;
}
/* =========================
   LOADING SCREEN
========================= */
#loader {
    position: fixed;
    width: 100%;
    height: 100%;
    background: #f4f6f9;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 5px solid #ddd;
    border-top: 5px solid #001f3f;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* =========================
   DARK MODE
========================= */
body.dark {
    background: #121212;
    color: #eee;
}
body.dark .header {
    background: #1e1e1e;
    border-color: #333;
}

body.dark .analytics-card,
body.dark .analytics-chart {
    background: #1e1e1e;
    color: #eee;
    box-shadow: none;
}

body.dark .sidebar {
    background: #000c1a;
}

body.dark .sidebar a.menu:hover {
    background: #001f3f;
}

.dark-btn {
    float: right;
    padding: 6px 12px;
    border: none;
    cursor: pointer;
    background: #001f3f;
    color: white;
    border-radius: 4px;
    margin-left: 15px;
    transition: 0.3s;
}

.dark-btn:hover {
    opacity: 0.8;
}
.header.collapsed {
    margin-left: 70px;
}

.toggle-btn {
    cursor: pointer;
    font-size: 20px;
    font-weight: bold;
}

/* MAIN */
.main {
    margin-left: 250px;
    padding: 30px;
    transition: 0.3s;
}

.main.collapsed {
    margin-left: 70px;
}
</style>
</head>

<body>
<!-- LOADER -->
<div id="loader">
    <div class="spinner"></div>
</div>
<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

  <div class="sidebar-header">
    <a href="admin.php">
        <img src="ntc-logo.png" style="cursor:pointer; transition: opacity 0.3s;"
             onmouseover="this.style.opacity='0.75'"
             onmouseout="this.style.opacity='1'">
    </a>
    <span>NTC Admin</span>
</div>

    <!-- PROFILE -->
    <!-- PROFILE -->
<div class="sidebar-profile">
    <a href="edit_account.php">
        <img src="uploads/<?= htmlspecialchars($profilePic) ?>" 
             style="cursor:pointer; transition: opacity 0.3s;"
             onmouseover="this.style.opacity='0.75'" 
             onmouseout="this.style.opacity='1'">
    </a>
    <h4><?= htmlspecialchars($fullName) ?></h4>
    <small>Administrator</small>
    <a href="edit_account.php">Edit Account</a>
</div>


    <!-- MENU -->
   <a href="admin.php" class="menu" data-title="Dashboard">
    <span class="icon">🏠</span>
    <span class="text">Dashboard</span>
</a>

<a href="index.php" class="menu" data-title="Create Report">
    <span class="icon">📝</span>
    <span class="text">Create Report</span>
</a>

<a href="myreport.php" class="menu" data-title="My Reports">
    <span class="icon">📄</span>
    <span class="text">My Reports</span>
</a>

<a href="jo_report.php" class="menu" data-title="All JO Reports">
    <span class="icon">📊</span>
    <span class="text">All JO Reports</span>
</a>

<a href="create_user.php" class="menu" data-title="Create JO Account">
    <span class="icon">➕</span>
    <span class="text">Create JO Account</span>
</a>

<a href="manage_users.php" class="menu" data-title="Manage JO Accounts"> 
    <span class="icon">👥</span>
    <span class="text">Manage JO Accounts</span>
</a>

<div class="sidebar-logout" data-title="Logout">
    <a href="logout.php" class="menu logout">
        <span class="icon">🚪</span>
        <span class="text">Logout</span>
    </a>
</div>

</div>
</div>

  <!-- HEADER -->
 <div class="header" id="header">
    <span class="toggle-btn" onclick="toggleSidebar()">☰</span>

    <button id="darkToggle" class="dark-btn">🌙</button>
</div>


<!-- MAIN -->
<div class="main" id="main">

    <h2>Administrator Dashboard</h2>
<p>Welcome, <strong><?= htmlspecialchars($fullName) ?></strong>.</p>

<hr style="margin:25px 0;">

<h3>Report Analytics</h3>

<!-- Analytics Cards -->
<div class="analytics-cards">
     <a href="manage_users.php" class="analytics-card card-blue" style="text-decoration:none;">
        <div class="card-icon">👤</div>
        <h4>Total JO Accounts</h4>
        <p><?= $totalJO ?></p>
    </a>

    <a href="myreport.php" class="analytics-card card-maroon" style="text-decoration:none;">
        <div class="card-icon">📝</div>
        <h4>Your Reports</h4>
        <p><?= $yourReports ?></p>
    </a>

    <a href="jo_report.php" class="analytics-card card-orange" style="text-decoration:none;">
        <div class="card-icon">📊</div>
        <h4>All JO Reports</h4>
        <p><?= $allJoReports ?></p>
    </a>

    <!-- Not clickable -->
    <div class="analytics-card card-purple">
        <div class="card-icon">🟢</div>
        <h4>Currently Active JO</h4>
        <p><?= $activeNowJO ?></p>
    </div>
</div>

<!-- Chart Section -->
<div class="analytics-chart">
    <canvas id="reportsChart"></canvas>
</div>

<style>
.analytics-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.analytics-card {
    background: linear-gradient(145deg, #ffffff, #f0f2f5);
    transition: all 0.3s;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(0,0,0,0.15);
}
.analytics-chart {
    padding: 25px;
    border-radius: 12px;
    background: linear-gradient(145deg, #ffffff, #f4f6f9);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
}
</style>
<script>
function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const header = document.getElementById("header");
    const main = document.getElementById("main");

    sidebar.classList.toggle("collapsed");
    header.classList.toggle("collapsed");
    main.classList.toggle("collapsed");

    // Save state to localStorage
    const isCollapsed = sidebar.classList.contains("collapsed");
    localStorage.setItem("sidebarCollapsed", isCollapsed ? "true" : "false");
}

// Loader
window.addEventListener("load", function() {
    const loader = document.getElementById("loader");
    loader.style.opacity = "0";
    setTimeout(() => loader.style.display = "none", 500);

    // Restore sidebar state
    const sidebarCollapsed = localStorage.getItem("sidebarCollapsed");
    if (sidebarCollapsed === "true") {
        document.getElementById("sidebar").classList.add("collapsed");
        document.getElementById("header").classList.add("collapsed");
        document.getElementById("main").classList.add("collapsed");
    }
});
</script>
<script>
const ctx = document.getElementById('reportsChart').getContext('2d');
const reportsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['Total JO Accounts', 'Your Reports', 'All JO Reports', 'Active JO Accounts'],
        datasets: [{
            label: 'Report Analytics',
            data: [<?= $totalJO ?>, <?= $yourReports ?>, <?= $allJoReports ?>, <?= $activeNowJO ?>],
            backgroundColor: ['#001f3f', '#800000', '#FFA500', '#28a745'] // green for active JO
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>
<script>
// ==========================
// PAGE LOADER
// ==========================
window.addEventListener("load", function() {
    const loader = document.getElementById("loader");
    loader.style.opacity = "0";
    setTimeout(() => loader.style.display = "none", 500);
});

// ==========================
// DARK MODE TOGGLE
// ==========================
const darkToggle = document.getElementById("darkToggle");

// Load saved preference
if (localStorage.getItem("darkMode") === "enabled") {
    document.body.classList.add("dark");
    darkToggle.innerHTML = "☀️";
    applyChartDarkMode(true);
}

darkToggle.addEventListener("click", function() {
    document.body.classList.toggle("dark");

    if (document.body.classList.contains("dark")) {
        localStorage.setItem("darkMode", "enabled");
        darkToggle.innerHTML = "☀️";
        applyChartDarkMode(true);
    } else {
        localStorage.setItem("darkMode", "disabled");
        darkToggle.innerHTML = "🌙";
        applyChartDarkMode(false);
    }
});
setInterval(() => {
    fetch('active_jo_count.php')
    .then(res => res.json())
    .then(data => {
        reportsChart.data.datasets[0].data[3] = data.activeNowJO;
        reportsChart.update();
    });
}, 60000); 
function applyChartDarkMode(isDark) {
    reportsChart.options.plugins.legend.labels = {
        color: isDark ? '#eee' : '#333'
    };
    reportsChart.options.scales.x.ticks = { color: isDark ? '#eee' : '#333' };
    reportsChart.options.scales.y.ticks = { color: isDark ? '#eee' : '#333' };
    reportsChart.options.scales.x.grid = { color: isDark ? '#333' : '#ddd' };
    reportsChart.options.scales.y.grid = { color: isDark ? '#333' : '#ddd' };
    reportsChart.update();
}
</script>
</body>
</html>
