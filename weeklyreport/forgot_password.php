<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require "db.php";

$step = 1;
$error = "";
$success = "";
$userId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Step 1: Verify username
    // Step 1: Verify username - add role check here
if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && $result['role'] === 'admin') {
        $_SESSION['reset_user_id'] = $result['id'];
        $step = 2;
    } elseif ($result && $result['role'] !== 'admin') {
        $error = "Only admin accounts can reset their password here.";
    } else {
        $error = "Username not found.";
    }
}

    // Step 2: Reset password
    if (isset($_POST['new_password'])) {
        $newPassword = trim($_POST['new_password']);
        $confirm     = trim($_POST['confirm_password']);

        if (!isset($_SESSION['reset_user_id'])) {
            $error = "Session expired. Please try again.";
            $step = 1;
        } elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters.";
            $step = 2;
        } elseif ($newPassword !== $confirm) {
            $error = "Passwords do not match.";
            $step = 2;
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hush = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $_SESSION['reset_user_id']);
            $stmt->execute();
            unset($_SESSION['reset_user_id']);
            $success = "Password reset successfully!";
            $step = 3;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password | NTC</title>
<link rel="stylesheet" href="login.css?v=<?= time() ?>">
<style>
.back-link {
  display: block;
  text-align: center;
  margin-top: 16px;
  font-size: 13px;
  color: #2f80ed;
  text-decoration: none;
}
.back-link:hover { text-decoration: underline; }
.step-indicator {
  display: flex;
  justify-content: center;
  gap: 8px;
  margin-bottom: 24px;
}
.step-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #dde2ec;
}
.step-dot.active { background: #2f80ed; }
.step-dot.done { background: #28a745; }
</style>
</head>
<body>

<div class="login-wrapper">
  <div class="login-box">

    <div class="login-header">
      <div class="agency-name">Republic of the Philippines</div>
      <h2>NTC Portal</h2>
      <p class="login-subtitle">Reset Your Password</p>
    </div>

    <div class="login-divider"></div>

    <!-- Step Indicator -->
    <div class="step-indicator">
      <div class="step-dot <?= $step >= 1 ? 'done' : '' ?>"></div>
      <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
      <div class="step-dot <?= $step === 3 ? 'done' : '' ?>"></div>
    </div>

    <?php if ($error): ?>
      <p class="error"><?= $error ?></p>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <form method="POST">
    <label>Enter your Admin Username</label>
    <div class="input-group">
      <span class="input-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </span>
      <input type="text" name="username" placeholder="Enter admin username" required>
    </div>

    <!-- PUT IT HERE, after the input group and before the button -->
    <p style="font-size:12px; color:#aaa; text-align:center; margin-top:-12px; margin-bottom:16px;">
      Only admin accounts can reset passwords here.<br>
      JO accounts must contact their administrator.
    </p>

    <button type="submit">Continue</button>
  </form>

    <?php elseif ($step === 2): ?>
      <form method="POST">
        <label>New Password</label>
        <div class="input-group password-wrapper">
          <span class="input-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="password" name="new_password" id="newPass" placeholder="New password" required>
          <span class="eye-toggle" onclick="togglePass('newPass', 'eye1open', 'eye1closed')">
            <svg id="eye1closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye1open" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </span>
        </div>

        <label>Confirm Password</label>
        <div class="input-group password-wrapper">
          <span class="input-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="password" name="confirm_password" id="confirmPass" placeholder="Confirm password" required>
          <span class="eye-toggle" onclick="togglePass('confirmPass', 'eye2open', 'eye2closed')">
            <svg id="eye2closed" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eye2open" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </span>
        </div>

        <button type="submit">Reset Password</button>
      </form>

    <?php elseif ($step === 3): ?>
      <p style="text-align:center; color:#28a745; font-size:15px; font-weight:600;">
        ✅ <?= $success ?>
      </p>
    <?php endif; ?>

    <a href="login.php" class="back-link">← Back to Login</a>

    <div class="login-footer">
      National Telecommunications Commission © <?= date('Y') ?>
    </div>
  </div>
</div>

<script>
function togglePass(inputId, openId, closedId) {
  const input = document.getElementById(inputId);
  const open  = document.getElementById(openId);
  const closed = document.getElementById(closedId);
  if (input.type === 'password') {
    input.type = 'text';
    open.style.display = 'block';
    closed.style.display = 'none';
  } else {
    input.type = 'password';
    open.style.display = 'none';
    closed.style.display = 'block';
  }
}
</script>
</body>
</html>