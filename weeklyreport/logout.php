<?php
require "db.php"; // make sure DB is available
$role = $_GET['role'] ?? '';

if ($role === 'admin') {
    session_name('admin_session');
    session_start();
    // Clear last_activity on logout
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    session_destroy();
    setcookie('admin_session', '', time() - 3600, '/');

} elseif ($role === 'jo') {
    session_name('jo_session');
    session_start();
    // Clear last_activity on logout
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("UPDATE users SET last_activity = NULL WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    session_destroy();
    setcookie('jo_session', '', time() - 3600, '/');
}

header("Location: login.php");
exit;
?>