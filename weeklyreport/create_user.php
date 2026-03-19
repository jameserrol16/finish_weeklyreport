<?php
declare(strict_types=1);

session_name('admin_session');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";
require_once "csrf_helper.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$errors  = [];
$success = "";
$old     = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    csrf_verify();

    $username  = trim($_POST['username']  ?? '');
    $password  = trim($_POST['password']  ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $employeeId= trim($_POST['employee_id'] ?? '');
    $branch    = trim($_POST['branch']    ?? '');
    $division  = trim($_POST['division']  ?? '');
    $sg        = trim($_POST['sg']        ?? '');
    $position  = trim($_POST['position']  ?? '');
    $address   = trim($_POST['address']   ?? '');
    $sex       = trim($_POST['sex']       ?? '');
    $education = trim($_POST['education'] ?? '');
    $prevWork  = trim($_POST['prev_work'] ?? '');

    $old = compact('username','fullName','email','employeeId','branch','division','sg','position','address','sex','education','prevWork');

    // Validation
    if ($fullName  === '') $errors['full_name']    = "Full name is required.";
    if ($employeeId === '') $errors['employee_id'] = "Employee ID is required.";
    if ($sex       === '') $errors['sex']        = "Please select a sex.";
    if ($address   === '') $errors['address']    = "Address is required.";
    if ($email     === '') {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }
    if ($username  === '') $errors['username']   = "Username is required.";
    if ($password  === '') {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors['password'] = "Password must be at least 6 characters.";
    }
    if ($branch    === '') $errors['branch']     = "Branch is required.";
    if ($division  === '') $errors['division']   = "Division is required.";
    if ($sg        === '') $errors['sg']         = "SG is required.";
    if ($position  === '') $errors['position']   = "Position is required.";
    if ($education === '') $errors['education']  = "Education is required.";

    // Username uniqueness
    if (!isset($errors['username']) && $username !== '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors['username'] = "Username already exists.";
        $stmt->close();
    }

    // Employee ID uniqueness
    if (!isset($errors['employee_id']) && $employeeId !== '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ?");
        $stmt->bind_param("s", $employeeId);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors['employee_id'] = "Employee ID already exists.";
        $stmt->close();
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role           = "jo";
        $profilePicture = "default.png";

        $stmt = $conn->prepare("
            INSERT INTO users (username, password_hush, full_name, email, role, profile_picture,
                               employee_id, branch, division, sg, position, address, sex, education, prev_work)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssssssssssssss",
            $username, $hashedPassword, $fullName, $email, $role, $profilePicture,
            $employeeId, $branch, $division, $sg, $position, $address, $sex, $education, $prevWork
        );

        if ($stmt->execute()) {
            $success = "JO account created successfully.";
            $old = [];
        } else {
            $errors['_general'] = "Something went wrong. Please try again.";
        }
        $stmt->close();
    }
}

function fe(array $e, string $k): string {
    return isset($e[$k])
        ? '<div class="field-error">⚠ ' . htmlspecialchars($e[$k]) . '</div>'
        : '';
}
function ic(array $e, string $k): string { return isset($e[$k]) ? ' invalid' : ''; }
function ov(array $o, string $k): string { return htmlspecialchars($o[$k] ?? ''); }
?>

<?php include 'header.php'; ?>

<style>
.form-card {
    max-width: 700px;
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
body.dark .form-title { color: #4db8ff; }

.section-label {
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    color: #888;
    letter-spacing: 0.05em;
    margin: 24px 0 12px;
    border-bottom: 1px solid #eee;
    padding-bottom: 6px;
}
body.dark .section-label { color: #aaa; border-color: #333; }

.input-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.input-group { margin-bottom: 18px; }
.input-group label { display: block; font-size: 14px; margin-bottom: 6px; }
.input-group input,
.input-group select,
.input-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 6px;
    transition: 0.3s;
    font-size: 14px;
    font-family: inherit;
    box-sizing: border-box;
}
.input-group textarea { resize: vertical; min-height: 80px; }
.input-group input:focus,
.input-group select:focus,
.input-group textarea:focus {
    border-color: #001f3f;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,31,63,0.1);
}
body.dark .input-group input,
body.dark .input-group select,
body.dark .input-group textarea {
    background: #2a2a2a;
    border-color: #444;
    color: white;
}
body.dark .input-group select option { background: #2a2a2a; }

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
.btn-primary:hover { background: #003366; }

.alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
.alert-error   { background: #ffe5e5; color: #b30000; }
.alert-success { background: #e6ffed; color: #006622; }
body.dark .alert-error   { background: #3a1f1f; color: #ff8080; }
body.dark .alert-success { background: #1f3a28; color: #80ffbf; }

.input-group input.invalid,
.input-group select.invalid,
.input-group textarea.invalid {
    border-color: #cc0000 !important;
    box-shadow: 0 0 0 2px rgba(204,0,0,0.1) !important;
    background-color: #fff8f8;
}
body.dark .input-group input.invalid,
body.dark .input-group select.invalid,
body.dark .input-group textarea.invalid { background-color: #2e1a1a; }
.input-group input.valid,
.input-group select.valid {
    border-color: #28a745 !important;
    box-shadow: 0 0 0 2px rgba(40,167,69,0.1) !important;
}
.field-error { font-size: 12px; color: #cc0000; margin-top: 4px; }
</style>

<div class="main" id="main">
    <div class="form-card">

        <div class="form-title">➕ Create JO Account</div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (isset($errors['_general'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($errors['_general']) ?></div>
        <?php endif; ?>

        <form method="POST" id="createForm" novalidate>
            <?php csrf_token_field(); ?>

            <!-- Personal Information -->
            <div class="section-label">👤 Personal Information</div>

            <div class="input-row">
                <div class="input-group">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id"
                           class="<?= ic($errors,'employee_id') ?>"
                           value="<?= ov($old,'employeeId') ?>"
                           placeholder="e.g. NTC-2024-001" required>
                    <?= fe($errors,'employee_id') ?>
                </div>
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name"
                           class="<?= ic($errors,'full_name') ?>"
                           value="<?= ov($old,'fullName') ?>" required>
                    <?= fe($errors,'full_name') ?>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Sex</label>
                    <select name="sex" class="<?= ic($errors,'sex') ?>" required>
                        <option value="">-- Select --</option>
                        <option value="Male"   <?= (($old['sex']??'')==='Male'   ?'selected':'') ?>>Male</option>
                        <option value="Female" <?= (($old['sex']??'')==='Female' ?'selected':'') ?>>Female</option>
                    </select>
                    <?= fe($errors,'sex') ?>
                </div>
            </div>

            <div class="input-group">
                <label>📍 Address</label>
                <input type="text" name="address"
                       class="<?= ic($errors,'address') ?>"
                       value="<?= ov($old,'address') ?>" required>
                <?= fe($errors,'address') ?>
            </div>

            <!-- Account Information -->
            <div class="section-label">🔐 Account Information</div>

            <div class="input-row">
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           class="<?= ic($errors,'email') ?>"
                           value="<?= ov($old,'email') ?>" required>
                    <?= fe($errors,'email') ?>
                </div>
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username"
                           class="<?= ic($errors,'username') ?>"
                           value="<?= ov($old,'username') ?>" required>
                    <?= fe($errors,'username') ?>
                </div>
            </div>

            <div class="input-group">
                <label>🔒 Password</label>
                <input type="password" name="password"
                       class="<?= ic($errors,'password') ?>" required>
                <?= fe($errors,'password') ?>
            </div>

            <!-- Assignment Details -->
            <div class="section-label">🏢 Assignment Details</div>

            <div class="input-row">
                <div class="input-group">
                    <label>Branch (Regional Office)</label>
                    <input type="text" name="branch"
                           class="<?= ic($errors,'branch') ?>"
                           value="<?= ov($old,'branch') ?>"
                           placeholder="e.g. NCR Branch, Region III Branch" required>
                    <?= fe($errors,'branch') ?>
                </div>
                <div class="input-group">
                    <label>Division (Functional Unit)</label>
                    <input type="text" name="division"
                           class="<?= ic($errors,'division') ?>"
                           value="<?= ov($old,'division') ?>"
                           placeholder="e.g. Administrative Division" required>
                    <?= fe($errors,'division') ?>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>SG (Salary Grade)</label>
                    <select name="sg" class="<?= ic($errors,'sg') ?>" required>
                        <option value="">-- Select SG --</option>
                        <?php for ($i = 1; $i <= 33; $i++):
                            $val = "Sg-$i"; ?>
                            <option value="<?= $val ?>"
                                <?= (($old['sg'] ?? '') === $val ? 'selected' : '') ?>>
                                SG-<?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <?= fe($errors,'sg') ?>
                </div>
                <div class="input-group">
                    <label>💼 Position</label>
                    <input type="text" name="position"
                           class="<?= ic($errors,'position') ?>"
                           value="<?= ov($old,'position') ?>" required>
                    <?= fe($errors,'position') ?>
                </div>
            </div>

            <!-- Education & Work Background -->
            <div class="section-label">🎓 Education & Work Background</div>

            <div class="input-group">
                <label>Highest Educational Attainment</label>
                <input type="text" name="education"
                       class="<?= ic($errors,'education') ?>"
                       value="<?= ov($old,'education') ?>"
                       placeholder="e.g. Bachelor of Science in Information Technology" required>
                <?= fe($errors,'education') ?>
            </div>

            <div class="input-group">
                <label>Previous Work Experience <span style="color:#999;font-weight:normal;">(optional)</span></label>
                <textarea name="prev_work"
                          placeholder="Briefly describe previous work experience..."><?= ov($old,'prevWork') ?></textarea>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>

        </form>
    </div>
</div>

<script>
const form = document.getElementById('createForm');

function showError(el, msg) {
    clearError(el);
    el.classList.add('invalid');
    el.classList.remove('valid');
    const div = document.createElement('div');
    div.className = 'field-error';
    div.textContent = '⚠ ' + msg;
    el.insertAdjacentElement('afterend', div);
}
function showValid(el) {
    clearError(el);
    el.classList.remove('invalid');
    el.classList.add('valid');
}
function clearError(el) {
    const n = el.nextElementSibling;
    if (n && n.classList.contains('field-error')) n.remove();
}
function validateEl(el) {
    const val = el.value.trim(), name = el.name;
    if (el.hasAttribute('required') && val === '') {
        showError(el, 'This field is required.'); return false;
    }
    if (name === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
        showError(el, 'Invalid email format.'); return false;
    }
    if (name === 'password' && val && val.length < 6) {
        showError(el, 'Password must be at least 6 characters.'); return false;
    }
    if (el.hasAttribute('required')) showValid(el);
    return true;
}

form.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('blur', () => validateEl(el));
    el.addEventListener('input', () => {
        if (el.classList.contains('invalid')) validateEl(el);
        else { el.classList.remove('invalid'); clearError(el); }
    });
});

form.addEventListener('submit', function(e) {
    let ok = true;
    form.querySelectorAll('input[required], select[required]').forEach(el => {
        if (!validateEl(el)) ok = false;
    });
    if (!ok) {
        e.preventDefault();
        form.querySelector('.invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

<?php include 'footer.php'; ?>