<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

// Only allow JO or admin
if (!in_array($_SESSION['role'], ['jo', 'admin'])) {
    header("Location: login.php");
    exit;
}

// Profile Picture & Full Name
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>NTC JO Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CSS VARIABLES
══════════════════════════════════════════════ */
:root {
    --navy:    #001f3f;
    --navy2:   #003366;
    --sky:     #00bfff;
    --blue:    #0074d9;
    --green:   #28a745;
    --orange:  #ff8800;
    --maroon:  #800000;
    --red:     #dc3545;
    --yellow:  #ffc107;
    --bg:      #f0f2f7;
    --surface: #ffffff;
    --border:  #e2e8f0;
    --text:    #1a202c;
    --muted:   #718096;
    --shadow:  0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
    --radius:  10px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: 'IBM Plex Sans', sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
}

/* ── PRE-PAINT COLLAPSED ── */
body.sidebar-collapsed .sidebar { width: 70px; }
body.sidebar-collapsed .header  { margin-left: 70px; }
body.sidebar-collapsed .main    { margin-left: 70px; }

/* ── LOADER ── */
#loader {
    position: fixed; inset: 0;
    background: var(--bg);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
    transition: opacity .5s ease;
}
.spinner {
    width: 44px; height: 44px;
    border: 4px solid #ddd;
    border-top-color: var(--navy);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SIDEBAR ── */
.sidebar {
    position: fixed; left: 0; top: 0;
    width: 250px; height: 100%;
    background: var(--navy);
    color: white;
    transition: width .3s ease;
    overflow: hidden;
    display: flex; flex-direction: column;
    z-index: 100;
}
.sidebar.collapsed { width: 70px; }

/* Sidebar Header */
.sidebar-header {
    display: flex; align-items: center; gap: 10px;
    padding: 16px;
    border-bottom: 1px solid rgba(255,255,255,.1);
    flex-shrink: 0;
}
.sidebar-header img {
    width: 36px; cursor: pointer;
    transition: opacity .3s;
}
.sidebar-header img:hover { opacity: .75; }
.sidebar-header span {
    font-size: 15px; font-weight: 700;
    white-space: nowrap;
}
.sidebar.collapsed .sidebar-header span { display: none; }

/* Sidebar Profile */
.sidebar-profile {
    text-align: center;
    padding: 18px 10px;
    border-bottom: 1px solid rgba(255,255,255,.1);
    flex-shrink: 0;
    position: relative;
}
.sidebar-profile .avatar-wrap {
    position: relative;
    display: inline-block;
}
.sidebar-profile img {
    width: 56px; height: 56px;
    border-radius: 40%;
    object-fit: cover;
    border: 2px solid rgba(255,255,255,.4);
    cursor: pointer;
    transition: opacity .3s;
    display: block;
}
.sidebar-profile img:hover { opacity: .75; }

.profile-upload-btn {
    position: absolute; bottom: 0; right: 0;
    width: 20px; height: 20px;
    background: var(--sky); color: var(--navy);
    border: none; border-radius: 50%;
    font-size: 10px; line-height: 20px; text-align: center;
    cursor: pointer; padding: 0;
    box-shadow: 0 1px 4px rgba(0,0,0,.35);
    transition: opacity .2s;
    display: flex; align-items: center; justify-content: center;
}
.profile-upload-btn:hover { opacity: .85; }

.sidebar-profile h4 { margin: 8px 0 3px; font-size: 13px; font-weight: 600; }
.sidebar-profile small { font-size: 11px; color: #a0aec0; }
.sidebar-profile .edit-link {
    display: block; font-size: 11px;
    color: #90cdf4; text-decoration: none;
    margin-top: 5px;
}
.sidebar-profile .edit-link:hover { color: white; }

.sidebar.collapsed .sidebar-profile { padding: 12px 0; }
.sidebar.collapsed .sidebar-profile h4,
.sidebar.collapsed .sidebar-profile small,
.sidebar.collapsed .sidebar-profile .edit-link,
.sidebar.collapsed .profile-upload-btn { display: none; }

/* Profile Menu */
#profileMenu {
    display: none;
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    background: var(--navy2);
    border-radius: 8px;
    padding: 6px;
    z-index: 9999;
    min-width: 160px;
    box-shadow: 0 4px 12px rgba(0,0,0,.4);
    margin-top: 6px;
}
#profileMenu div {
    padding: 8px 12px;
    color: white;
    cursor: pointer;
    border-radius: 6px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background .15s;
}
#profileMenu div:hover { background: rgba(255,255,255,.1); }

/* Sidebar Nav */
.sidebar-nav {
    flex: 1;
    overflow-y: auto; overflow-x: hidden;
    padding: 8px 0;
}
.sidebar-nav::-webkit-scrollbar { width: 4px; }
.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,.15); border-radius: 4px;
}

.sidebar a.menu {
    display: flex; align-items: center; gap: 11px;
    padding: 10px 18px;
    color: rgba(255,255,255,.85);
    text-decoration: none;
    font-size: 13.5px;
    border-left: 3px solid transparent;
    transition: all .25s ease;
    white-space: nowrap;
}
.sidebar a.menu:hover {
    background: var(--navy2);
    border-left-color: var(--sky);
    color: white;
}
.sidebar.collapsed a.menu {
    justify-content: center;
    padding: 10px;
    border-left: none;
    position: relative;
}
.sidebar.collapsed a.menu .text { display: none; }
.sidebar.collapsed a.menu:hover::after {
    content: attr(data-title);
    position: absolute; left: 72px;
    background: var(--navy2);
    padding: 5px 10px; border-radius: 4px;
    font-size: 12px; white-space: nowrap;
    z-index: 999;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
}

/* Sidebar Logout */
.sidebar-logout {
    flex-shrink: 0;
    padding: 8px 0 12px;
    border-top: 1px solid rgba(255,255,255,.08);
}
.logout { color: #fc8181 !important; }

/* ── HEADER BAR ── */
.header {
    margin-left: 250px;
    padding: 12px 24px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    transition: margin .3s ease;
    position: sticky; top: 0; z-index: 50;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.header.collapsed { margin-left: 70px; }

.toggle-btn {
    cursor: pointer; font-size: 20px; line-height: 1;
    color: var(--navy); transition: opacity .2s;
}
.toggle-btn:hover { opacity: .6; }

.dark-btn {
    padding: 5px 12px; border: none; cursor: pointer;
    background: var(--navy); color: white;
    border-radius: 6px; font-size: 14px;
    transition: opacity .2s;
}
.dark-btn:hover { opacity: .8; }

/* ── MAIN CONTENT SHELL ── */
.main {
    margin-left: 250px;
    padding: 28px 28px 40px;
    transition: margin .3s ease;
    min-height: calc(100vh - 49px);
}
.main.collapsed { margin-left: 70px; }

/* ── DARK MODE ── */
body.dark {
    --bg:      #0f1117;
    --surface: #1a1d27;
    --border:  #2d3148;
    --text:    #e2e8f0;
    --muted:   #718096;
}
body.dark .header  { background: var(--surface); border-color: var(--border); }
body.dark .sidebar { background: #07091a; }
body.dark #loader  { background: var(--bg); }

/* Camera Modal */
#sidebarCameraModal {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.7);
    z-index: 99999;
    align-items: center;
    justify-content: center;
}
#sidebarCameraModal .cam-box {
    background: white;
    border-radius: 14px;
    padding: 24px;
    max-width: 420px;
    width: 90%;
    text-align: center;
}
#sidebarCameraModal h3 { margin: 0 0 14px; font-size: 15px; }
#sidebarCameraFeed {
    width: 100%; border-radius: 10px; background: #000;
}
.cam-btns {
    display: flex; gap: 10px; margin-top: 14px; justify-content: center;
}
.cam-btns button {
    padding: 10px 20px; border: none; border-radius: 8px;
    cursor: pointer; font-size: 14px;
}
.cam-capture { background: var(--navy); color: white; }
.cam-cancel  { background: #ccc; color: #333; }
</style>

<script>
// Pre-paint collapsed state
(function() {
    if (localStorage.getItem("sidebarCollapsed") === "true") {
        document.body.classList.add("sidebar-collapsed");
    }
})();
</script>

</head>
<body>

<!-- LOADER -->
<div id="loader"><div class="spinner"></div></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-header">
        <a href="jo.php">
            <img src="ntc-logo.png">
        </a>
        <span>NTC JO</span>
    </div>

    <!-- PROFILE -->
    <div class="sidebar-profile">
        <div class="avatar-wrap">
            <img id="sidebarProfileImg"
                 src="uploads/<?= htmlspecialchars($profilePic) ?>"
                 alt="Profile Picture">
            <input type="file" id="profileUploadInput" accept="image/*" style="display:none;">
            <label onclick="openProfileMenu()" class="profile-upload-btn" title="Change photo">✎</label>
        </div>

        <!-- Profile menu -->
        <div id="profileMenu">
            <div onclick="document.getElementById('profileUploadInput').click(); closeProfileMenu()">
                🖼️ Choose Image
            </div>
            <div onclick="openSidebarCamera()">
                📷 Take Photo
            </div>
        </div>

        <h4><?= htmlspecialchars($fullName) ?></h4>
        <small>Job Order</small>
        <a href="edit_account.php" class="edit-link">Edit Account</a>
    </div>

    <!-- NAV -->
    <div class="sidebar-nav">
        <a href="jo.php"          class="menu" data-title="Dashboard">          <span class="icon">🏠</span><span class="text">Dashboard</span></a>
        <a href="jo_creport.php"  class="menu" data-title="Create Report">      <span class="icon">📝</span><span class="text">Create Report</span></a>
        <a href="jo_leave.php"    class="menu" data-title="Application for Leave"><span class="icon">📋</span><span class="text">Application for Leave</span></a>
        <a href="jo_vreport.php"  class="menu" data-title="My Reports">         <span class="icon">📄</span><span class="text">My Reports</span></a>
    </div>

    <div class="sidebar-logout">
        <a href="logout.php?role=jo" class="menu logout" data-title="Logout">
            <span class="icon">🚪</span><span class="text">Logout</span>
        </a>
    </div>

</div><!-- /.sidebar -->

<!-- Camera Modal -->
<div id="sidebarCameraModal">
    <div class="cam-box">
        <h3>Take a Photo</h3>
        <video id="sidebarCameraFeed" autoplay playsinline></video>
        <canvas id="sidebarCameraCanvas" style="display:none;"></canvas>
        <div class="cam-btns">
            <button type="button" class="cam-capture" onclick="captureSidebarPhoto()">📸 Capture</button>
            <button type="button" class="cam-cancel"  onclick="closeSidebarCamera()">Cancel</button>
        </div>
    </div>
</div>

<!-- HEADER BAR -->
<div class="header" id="header">
    <span class="toggle-btn" onclick="toggleSidebar()">☰</span>
    <button id="darkToggle" class="dark-btn">🌙 Dark Mode</button>
</div>