<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require "db.php";

$error = "";
$remembered_username = "";
$remembered_password = "";
$is_remembered = false;

// Only restore if the remember_me FLAG cookie is explicitly set to '1'
if (
    isset($_COOKIE['remember_me']) && $_COOKIE['remember_me'] === '1' &&
    isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_pass'])
) {
    $remembered_username = $_COOKIE['remember_user'];
    $remembered_password = $_COOKIE['remember_pass'];
    $is_remembered = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if ($username && $password) {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password_hush'])) {

                if ($remember) {
                    $expire = time() + (30 * 24 * 60 * 60);
                    setcookie('remember_me',   '1',       $expire, '/');
                    setcookie('remember_user', $username, $expire, '/');
                    setcookie('remember_pass', $password, $expire, '/');
                } else {
                    setcookie('remember_me',   '', time() - 3600, '/');
                    setcookie('remember_user', '', time() - 3600, '/');
                    setcookie('remember_pass', '', time() - 3600, '/');
                }

                session_destroy();

                if ($user['role'] === 'admin') {
                    session_name('admin_session');
                } else {
                    session_name('jo_session');
                }
                session_start();

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['role']     = $user['role'];

                if ($user['role'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: jo.php");
                }
                exit;
            }
        }

        $error = "Invalid username or password.";
        $remembered_username = $username;
        $remembered_password = $password;
        $is_remembered = $remember;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>NTC – Login</title>
  <link rel="stylesheet" href="login.css?v=<?= time() ?>">
</head>
<body>

<div class="login-wrapper">
  <div class="login-box">

    <!-- HEADER -->
    <div class="login-header">
      <div class="agency-name">National Telecommunications Commission</div>
      <h2>Welcome Back</h2>
      <p class="login-subtitle">Sign in to your account to continue</p>
    </div>

    <div class="login-divider"></div>

    <!-- ERROR -->
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">

      <!-- USERNAME -->
      <label for="username">Username</label>
      <div class="input-group">
        <span class="input-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="8" r="4"/>
            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
          </svg>
        </span>
        <input type="text" id="username" name="username"
          placeholder="Enter your username"
          value="<?= htmlspecialchars($remembered_username) ?>"
          autocomplete="username" required />
      </div>

      <!-- PASSWORD -->
      <label for="password">Password</label>
      <div class="input-group">
        <div class="password-wrapper">
          <span class="input-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
          </span>
          <input type="password" id="passwordInput" name="password"
            placeholder="Enter your password"
            value="<?= htmlspecialchars($remembered_password) ?>"
            autocomplete="current-password" required />
          <span class="eye-toggle" onclick="togglePassword()" title="Show/Hide Password">
            <svg id="eyeClosed" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            <svg id="eyeOpen" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="16" height="16"
              viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round">
              <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
              <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
              <line x1="1" y1="1" x2="23" y2="23"/>
            </svg>
          </span>
        </div>
      </div>

      <!-- REMEMBER ME + FORGOT PASSWORD (same row) -->
      <div class="remember-row">
        <div class="remember-left">
          <input type="checkbox" id="remember" name="remember"
            <?= $is_remembered ? 'checked' : '' ?>
            onchange="handleRememberChange(this)" />
          <label for="remember">Remember me</label>
        </div>
        <a href="forgot_password.php" class="forgot-password">Forgot password?</a>
      </div>

      <button type="submit">Sign In</button>

    </form>

    <!-- FOOTER -->
    <div class="login-footer">
      &copy; <?= date('Y') ?> National Telecommunications Commission &middot; All rights reserved
    </div>

  </div>
</div>

<script>
function getCookie(name) {
  const match = document.cookie.split('; ').find(row => row.startsWith(name + '='));
  return match ? match.substring(name.length + 1) : null;
}

function clearRememberCookies() {
  const expired = 'expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
  document.cookie = 'remember_me='   + '; ' + expired;
  document.cookie = 'remember_user=' + '; ' + expired;
  document.cookie = 'remember_pass=' + '; ' + expired;
}

window.addEventListener('DOMContentLoaded', function () {
  const checkbox     = document.getElementById('remember');
  const isRemembered = getCookie('remember_me') === '1';

  if (isRemembered) {
    // Repopulate fields from cookies as the source of truth
    const savedUser = getCookie('remember_user');
    const savedPass = getCookie('remember_pass');
    if (savedUser) document.getElementById('username').value      = decodeURIComponent(savedUser);
    if (savedPass) document.getElementById('passwordInput').value = decodeURIComponent(savedPass);
    checkbox.checked = true;
  } else {
    // No valid remember cookie — uncheck the box but leave typed fields alone
    checkbox.checked = false;
    clearRememberCookies();
  }
});

function togglePassword() {
  const input     = document.getElementById('passwordInput');
  const eyeOpen   = document.getElementById('eyeOpen');
  const eyeClosed = document.getElementById('eyeClosed');

  if (input.type === 'password') {
    input.type = 'text';
    eyeOpen.style.display   = 'block';
    eyeClosed.style.display = 'none';
  } else {
    input.type = 'password';
    eyeOpen.style.display   = 'none';
    eyeClosed.style.display = 'block';
  }
}

function handleRememberChange(checkbox) {
  if (!checkbox.checked) {
    // Only clear the cookies — do NOT wipe the fields the user may have typed
    clearRememberCookies();
  }
}
</script>

</body>
</html>