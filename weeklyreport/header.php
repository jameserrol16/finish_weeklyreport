<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Profile Picture
$profilePic = "default.png";
$stmt = $conn->prepare("SELECT profile_picture, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if ($data) {
    $profilePic = $data['profile_picture'] ?? "default.png";
    $fullName   = $data['full_name'] ?? $_SESSION['username'];
} else {
    $fullName = $_SESSION['username'];
}
?>
<!DOCTYPE html>
<html>
<head>
    
<meta charset="UTF-8">
<title>NTC Dashboard</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <a href="logout.php?role=admin" class="menu logout">
    <span class="icon">🚪</span>
    <span class="text">Logout</span>
</a>
</div>

</div>

  <!-- HEADER -->
 <div class="header" id="header">
    <span class="toggle-btn" onclick="toggleSidebar()">☰</span>

    <button id="darkToggle" class="dark-btn">🌙</button>
</div>