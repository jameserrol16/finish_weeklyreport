<?php
$role = $_GET['role'] ?? '';

if ($role === 'admin') {
    session_name('admin_session');
    session_start();
    session_destroy();
    setcookie('admin_session', '', time() - 3600, '/');
} elseif ($role === 'jo') {
    session_name('jo_session');
    session_start();
    session_destroy();
    setcookie('jo_session', '', time() - 3600, '/');
}

// Do NOT clear remember me cookies here.
// If the user checked "Remember Me", those cookies should survive logout
// so their credentials are pre-filled when they return to the login page.
// They are only cleared when the user explicitly unchecks "Remember Me".

header("Location: login.php");
exit;
?>