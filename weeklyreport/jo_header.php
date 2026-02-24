<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

// Only allow JO
if (!in_array($_SESSION['role'], ['jo', 'admin'])) {
    header("Location: login.php");
    exit;
}

// Profile Picture
$profilePic = "default.png";
$stmt = $conn->prepare("SELECT profile_picture, full_name FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

$fullName = $_SESSION['username'];
if ($data) {
    $profilePic = $data['profile_picture'] ?? "default.png";
    $fullName   = $data['full_name'] ?? $_SESSION['username'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>JO Dashboard | NTC</title>
<style>
/* ==== BODY & MAIN ==== */
/* Profile Image Wrapper */
.profile-img-wrapper {
    position: relative;
    width: 60px;
    height: 60px;
    margin: 0 auto;
}

.profile-img-wrapper img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    border: 3px solid white;
    object-fit: cover;
    transition: 0.3s;
}

/* Hover effect */
.profile-img-wrapper:hover img {
    opacity: 0.8;
}

/* Small overlay button on image */
.profile-upload-btn {
    position: absolute;
    bottom: 0;
    right: 0;
    background: #001f3f;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    font-size: 12px;
    border: 2px solid white;
    transition: background 0.3s;
}

.profile-upload-btn:hover {
    background: #28a745;
}

body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f4f6f9; }
.main { margin-left: 250px; padding: 30px; transition: 0.3s; }
.main.collapsed { margin-left: 70px; }
.page {
    max-width: 900px;
    margin: 0 auto;  /* center the form, not push it right */
}
/* ==== SIDEBAR ==== */
.sidebar { position: fixed; left: 0; top: 0; width: 250px; height: 100%; background: #001f3f; color: white; transition: width 0.3s ease; overflow-x: hidden; }
.sidebar.collapsed { width: 70px; }

.sidebar-header { display: flex; align-items: center; padding: 15px; gap: 10px; border-bottom: 1px solid rgba(255,255,255,0.2); }
.sidebar-header img { width: 40px; }
.sidebar-header span { font-size: 16px; font-weight: bold; }
.sidebar.collapsed .sidebar-header span { display: none; }

.sidebar-profile { text-align: center; padding: 20px 10px; border-bottom: 1px solid rgba(255,255,255,0.2); }
.sidebar-profile img { width: 60px; height: 60px; border-radius: 40%; object-fit: cover; border: 3px solid white; }
.sidebar-profile h4 { margin: 10px 0 5px; font-size: 14px; }
.sidebar-profile small { font-size: 12px; color: #ccc; }
.sidebar-profile a { display: block; font-size: 12px; color: #ddd; text-decoration: none; margin-top: 6px; }
.sidebar-profile a:hover { color: white; }
.sidebar.collapsed .sidebar-profile h4,
.sidebar.collapsed .sidebar-profile small,
.sidebar.collapsed .sidebar-profile a { display: none; }
.sidebar.collapsed .sidebar-profile { padding: 15px 0; }
/* MENU */
.sidebar a.menu { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: white; text-decoration: none; font-size: 14px; border-left: 4px solid transparent; transition: all 0.3s ease; }
.sidebar a.menu:hover { background: #003366; border-left: 4px solid #00bfff; }
.sidebar.collapsed a.menu .text { display: none; }
.sidebar.collapsed a.menu { justify-content: center; position: relative; }
.sidebar.collapsed a.menu:hover::after { content: attr(data-title); position: absolute; left: 70px; background: #003366; padding: 6px 10px; border-radius: 4px; white-space: nowrap; font-size: 12px; }

/* LOGOUT */
.sidebar-logout { position: absolute; bottom: 20px; width: 100%; }
.sidebar-logout a.logout { color: #ffcccc !important; }

/* HEADER */
.header { margin-left: 250px; padding: 15px 25px; background: white; border-bottom: 1px solid #ddd; transition: 0.3s; }
.header.collapsed { margin-left: 70px; }
.toggle-btn { cursor: pointer; font-size: 20px; font-weight: bold; }
.dark-btn { float: right; padding: 6px 12px; border: none; cursor: pointer; background: #001f3f; color: white; border-radius: 4px; transition: 0.3s; }
.dark-btn:hover { opacity: 0.8; }

/* LOADER */
#loader { position: fixed; width: 100%; height: 100%; background: #f4f6f9; display: flex; justify-content: center; align-items: center; z-index: 9999; transition: opacity 0.5s ease; }
.spinner { width: 50px; height: 50px; border: 5px solid #ddd; border-top: 5px solid #001f3f; border-radius: 50%; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* DARK MODE */
body.dark { background: #121212; color: #eee; }
body.dark .header { background: #1e1e1e; border-color: #333; }
body.dark .sidebar { background: #000c1a; }
body.dark .sidebar a.menu:hover { background: #001f3f; }
.sidebar-profile .full-name {
    font-size: 12px;
    color: #ddd;
    margin-top: 2px;
}
.sidebar-profile .full-name:hover {
    color: white;
}
.sidebar.collapsed .sidebar-profile .full-name {
    display: none;
}

</style>
</head>
<body>

<!-- LOADER -->
<div id="loader"><div class="spinner"></div></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <a href="jo.php">
            <img src="ntc-logo.png" style="cursor:pointer; transition: opacity 0.3s;"
                 onmouseover="this.style.opacity='0.75'"
                 onmouseout="this.style.opacity='1'">
        </a>
        <span>NTC JO</span>
    </div>

    <!-- PROFILE -->
    <div class="sidebar-profile">
        <div class="profile-img-wrapper">
            <img id="sidebarProfileImg" src="uploads/<?= htmlspecialchars($profilePic) ?>" alt="Profile Picture">
            <label for="profileUploadInput" class="profile-upload-btn" title="Change Profile Picture">
                ✎
            </label>
            <input type="file" id="profileUploadInput" accept="image/*" style="display:none">
        </div>
        <h4></h4>
        <small>Job Order</small>
       <div class="full-name"><strong><?= htmlspecialchars($fullName) ?></strong></div>
    </div>

    <!-- MENU -->
    <a href="jo.php" class="menu" data-title="Dashboard">
        <span class="icon">🏠</span>
        <span class="text">Dashboard</span>
    </a>

    <a href="jo_creport.php" class="menu" data-title="Create Report">
        <span class="icon">📝</span>
        <span class="text">Create Report</span>
    </a>

    <a href="jo_vreport.php" class="menu" data-title="My Reports">
        <span class="icon">📄</span>
        <span class="text">My Reports</span>
    </a>

    <div class="sidebar-logout" data-title="Logout">
        <a href="logout.php?role=jo" class="menu logout">
            <span class="icon">🚪</span>
            <span class="text">Logout</span>
        </a>
    </div>

</div><!-- END SIDEBAR -->
<!-- HEADER -->
<div class="header" id="header">
    <span class="toggle-btn" onclick="toggleSidebar()">☰</span>
    <button id="darkToggle" class="dark-btn">🌙</button>
</div>
<div class="main" id="main">
