<?php
declare(strict_types=1);

session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$errors = [];
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if ($username === '' || $password === '' || $fullName === '' || $email === '') {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errors[] = "Username already exists.";
        }
        $stmt->close();
    }

    if (empty($errors)) {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = "jo";
        $profilePicture = "default.png";

        $stmt = $conn->prepare("
            INSERT INTO users (username, password_hush, full_name, email, role, profile_picture)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param("ssssss", $username, $hashedPassword, $fullName, $email, $role, $profilePicture);

        if ($stmt->execute()) {
            $success = "JO account created successfully.";
        } else {
            $errors[] = "Something went wrong.";
        }

        $stmt->close();
    }
}
?>

<?php include 'header.php'; ?>

<style>
.form-card {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    transition: 0.3s;
}

body.dark .form-card {
    background: #1e1e1e;
    box-shadow: none;
}

.form-title {
    font-size: 22px;
    margin-bottom: 20px;
    color: #001f3f;
    display: flex;
    align-items: center;
    gap: 10px;
}

body.dark .form-title {
    color: #4db8ff;
}

.input-group {
    margin-bottom: 18px;
}

.input-group label {
    display: block;
    font-size: 14px;
    margin-bottom: 6px;
}

.input-group input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    transition: 0.3s;
}

.input-group input:focus {
    border-color: #001f3f;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,31,63,0.1);
}

body.dark .input-group input {
    background: #2a2a2a;
    border-color: #444;
    color: white;
}

.btn-primary {
    width: 100%;
    padding: 12px;
    background: #001f3f;
    color: white;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.btn-primary:hover {
    background: #003366;
}

.alert {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 14px;
}

.alert-error {
    background: #ffe5e5;
    color: #b30000;
}

.alert-success {
    background: #e6ffed;
    color: #006622;
}

body.dark .alert-error {
    background: #3a1f1f;
    color: #ff8080;
}

body.dark .alert-success {
    background: #1f3a28;
    color: #80ffbf;
}
</style>

<div class="main" id="main">
    <div class="form-card">

        <div class="form-title">
            ➕ Create JO Account
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <div class="input-group">
                <label>👤 Full Name</label>
                <input type="text" name="full_name" required>
            </div>

            <div class="input-group">
                <label>📧 Email</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>🆔 Username</label>
                <input type="text" name="username" required>
            </div>

            <div class="input-group">
                <label>🔒 Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">
                Create Account
            </button>

        </form>

    </div>
</div>

<?php include 'footer.php'; ?>
