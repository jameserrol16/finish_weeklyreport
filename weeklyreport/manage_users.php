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

/** DELETE JO */
if (isset($_GET['delete'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }
    $deleteId = (int) $_GET['delete'];
    if ($deleteId === (int) $_SESSION['user_id']) {
        $errors[] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'jo'");
        $stmt->bind_param("i", $deleteId);
        $stmt->execute() ? $success = "JO account deleted successfully." : $errors[] = "Failed to delete account.";
        $stmt->close();
    }
}

/** UPDATE JO */
if (isset($_POST['update_user'])) {
    csrf_verify();

    $id        = (int) trim($_POST['id']        ?? 0);
    $username  = trim($_POST['username']  ?? '');
    $fullName  = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $employeeId= trim($_POST['employee_id'] ?? '');
    $branch    = trim($_POST['branch']    ?? '');
    $division  = trim($_POST['division']  ?? '');
    $sg        = trim($_POST['sg']        ?? '');
    $position  = trim($_POST['position']  ?? '');
    $address   = trim($_POST['address']   ?? '');
    $sex       = trim($_POST['sex']       ?? '');
    $education = trim($_POST['education'] ?? '');
    $prevWork  = trim($_POST['prev_work'] ?? '');

    if ($username === '' || $fullName === '' || $email === '' ||
        $employeeId === '' || $branch === '' || $division === '' || $sg === '' || $position === '' ||
        $address === '' || $sex === '' || $education === '') {
        $errors[] = "All fields except password and previous work are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Employee ID uniqueness (exclude current user)
    if ($employeeId !== '') {
        $stmt = $conn->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $stmt->bind_param("si", $employeeId, $id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) $errors[] = "Employee ID already exists.";
        $stmt->close();
    }

    if (empty($errors)) {
        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE users SET username=?, full_name=?, email=?, password_hush=?,
                    employee_id=?, branch=?, division=?, sg=?, position=?, address=?, sex=?, education=?, prev_work=?
                WHERE id=? AND role='jo'
            ");
            $stmt->bind_param("ssssssssssssssi",
                $username, $fullName, $email, $hashedPassword,
                $employeeId, $branch, $division, $sg, $position, $address, $sex, $education, $prevWork, $id
            );
        } else {
            $stmt = $conn->prepare("
                UPDATE users SET username=?, full_name=?, email=?,
                    employee_id=?, branch=?, division=?, sg=?, position=?, address=?, sex=?, education=?, prev_work=?
                WHERE id=? AND role='jo'
            ");
            $stmt->bind_param("sssssssssssssi",
                $username, $fullName, $email,
                $employeeId, $branch, $division, $sg, $position, $address, $sex, $education, $prevWork, $id
            );
        }
        $stmt->execute() ? $success = "JO account updated successfully." : $errors[] = "Update failed.";
        $stmt->close();
    }
}

/** Fetch all JO accounts */
$result = $conn->query("
    SELECT id, username, full_name, email, employee_id, branch, division, sg, position, address, sex, education, prev_work
    FROM users WHERE role = 'jo' ORDER BY id DESC
");

$csrfToken = csrf_generate();
?>
<?php include 'header.php'; ?>

<style>
.table-card {
    background: white; padding: 25px; border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}
body.dark .table-card { background: #1e1e1e; box-shadow: none; }

table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; white-space: nowrap; }
body.dark th, body.dark td { border-color: #333; }
th { background: #001f3f; color: white; }

.btn { padding: 6px 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-edit   { background: #007bff; color: white; }
.btn-delete { background: #cc0000; color: white; }
.btn:hover  { opacity: 0.85; }

.export-btn {
    padding: 8px 16px; background: #28a745; color: white;
    border: none; border-radius: 6px; cursor: pointer; font-size: 13px;
    font-weight: bold; text-decoration: none; display: inline-block;
}
.export-btn:hover { background: #218838; }

.alert { padding: 10px; border-radius: 6px; margin-bottom: 15px; }
.alert-error   { background: #ffe5e5; color: #b30000; }
.alert-success { background: #e6ffed; color: #006622; }

/* Modal */
.modal {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.5); justify-content: center;
    align-items: center; z-index: 1000;
}
.modal-content {
    background: white; padding: 28px; border-radius: 10px;
    width: 580px; max-height: 90vh; overflow-y: auto;
}
body.dark .modal-content { background: #1e1e1e; }

.modal-content h3 { margin-bottom: 18px; color: #001f3f; }
body.dark .modal-content h3 { color: #4db8ff; }

.modal-section {
    font-size: 12px; font-weight: bold; text-transform: uppercase;
    color: #888; letter-spacing: 0.05em; margin: 16px 0 8px;
    border-bottom: 1px solid #eee; padding-bottom: 5px;
}
body.dark .modal-section { color: #aaa; border-color: #333; }

.modal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-full { grid-column: 1 / -1; }

.mf { display: flex; flex-direction: column; gap: 4px; }
.mf label { font-size: 13px; }

.mf input, .mf select, .mf textarea {
    padding: 8px 10px; border: 1px solid #ccc; border-radius: 6px;
    font-size: 13px; font-family: inherit; box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s; width: 100%;
}
.mf textarea { resize: vertical; min-height: 65px; }

.mf input:focus, .mf select:focus, .mf textarea:focus {
    border-color: #001f3f; outline: none;
    box-shadow: 0 0 0 2px rgba(0,31,63,0.1);
}

.mf input.invalid, .mf select.invalid, .mf textarea.invalid {
    border-color: #cc0000 !important;
    box-shadow: 0 0 0 2px rgba(204,0,0,0.12) !important;
    background: #fff8f8;
}
.mf input.valid, .mf select.valid {
    border-color: #28a745 !important;
    box-shadow: 0 0 0 2px rgba(40,167,69,0.12) !important;
}
.field-error {
    font-size: 12px; color: #cc0000;
    display: flex; align-items: center; gap: 4px; margin-top: 2px;
}

body.dark .mf input, body.dark .mf select, body.dark .mf textarea {
    background: #2a2a2a; border-color: #444; color: white;
}
body.dark .mf input.invalid, body.dark .mf select.invalid { background: #2e1a1a; }
body.dark .mf select option { background: #2a2a2a; }

.modal-actions { display: flex; gap: 10px; margin-top: 18px; }
.modal-actions button { flex: 1; padding: 10px; font-weight: bold; }
</style>

<div class="main" id="main">
    <div class="table-card">
        <h2>Manage JO Accounts</h2>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div style="display:flex; gap:10px; align-items:center;">
                <input type="text" id="searchInput" placeholder="🔍 Search JO..."
                    style="padding:8px; width:240px; border-radius:6px; border:1px solid #ccc;">
                <select id="rowsPerPage" style="padding:8px; border-radius:6px;">
                    <option value="5">5 rows</option>
                    <option value="10" selected>10 rows</option>
                    <option value="20">20 rows</option>
                </select>
            </div>
            <a href="export_jo.php" class="export-btn">⬇ Export to Excel</a>
        </div>

        <div style="overflow-x:auto;">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th class="sortable" data-column="1">Employee ID ⬍</th>
                    <th class="sortable" data-column="2">Username ⬍</th>
                    <th class="sortable" data-column="3">Full Name ⬍</th>
                    <th class="sortable" data-column="4">Email ⬍</th>
                    <th class="sortable" data-column="5">Branch ⬍</th>
                    <th class="sortable" data-column="6">Division ⬍</th>
                    <th class="sortable" data-column="7">SG ⬍</th>
                    <th class="sortable" data-column="8">Position ⬍</th>
                    <th class="sortable" data-column="9">Sex ⬍</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $counter = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($row['employee_id']) ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td><?= htmlspecialchars($row['full_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['branch']) ?></td>
                        <td><?= htmlspecialchars($row['division']) ?></td>
                        <td><?= htmlspecialchars($row['sg']) ?></td>
                        <td><?= htmlspecialchars($row['position']) ?></td>
                        <td><?= htmlspecialchars($row['sex']) ?></td>
                        <td>
                            <button class="btn btn-edit" onclick="openEditModal(
                                <?= $row['id'] ?>,
                                '<?= htmlspecialchars($row['employee_id'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['username'],  ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['email'],     ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['branch'],    ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['division'],  ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['sg'],        ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['position'],  ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['address'],   ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['sex'],       ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['education'], ENT_QUOTES) ?>',
                                '<?= htmlspecialchars($row['prev_work'] ?? '', ENT_QUOTES) ?>'
                            )">Edit</button>

                            <a href="?delete=<?= $row['id'] ?>&csrf_token=<?= urlencode($csrfToken) ?>"
                               onclick="return confirm('Delete this JO account? This cannot be undone.')"
                               class="btn btn-delete">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>

        <div id="pagination" style="margin-top:15px;"></div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <h3>✏️ Edit JO Account</h3>
        <form method="POST" id="editForm" novalidate>
            <?php csrf_token_field(); ?>
            <input type="hidden" name="id" id="editId">

            <div class="modal-section">👤 Personal Information</div>
            <div class="modal-grid">
                <div class="mf">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" id="editEmployeeId"
                           placeholder="e.g. NTC-2024-001" required>
                </div>
                <div class="mf">
                    <label>Full Name</label>
                    <input type="text" name="full_name" id="editFullName" required>
                </div>
                <div class="mf">
                    <label>Sex</label>
                    <select name="sex" id="editSex" required>
                        <option value="">-- Select --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="mf modal-full">
                    <label>Address</label>
                    <input type="text" name="address" id="editAddress" required>
                </div>
            </div>

            <div class="modal-section">🔐 Account Information</div>
            <div class="modal-grid">
                <div class="mf">
                    <label>Username</label>
                    <input type="text" name="username" id="editUsername" required>
                </div>
                <div class="mf">
                    <label>Email</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>
                <div class="mf modal-full">
                    <label>New Password <span style="color:#999;font-weight:normal;">(leave blank to keep current)</span></label>
                    <input type="password" name="password" id="editPassword">
                </div>
            </div>

            <div class="modal-section">🏢 Assignment Details</div>
            <div class="modal-grid">
                <div class="mf">
                    <label>Branch (Regional Office)</label>
                    <input type="text" name="branch" id="editBranch" required
                           placeholder="e.g. NCR Branch, Region III Branch">
                </div>
                <div class="mf">
                    <label>Division (Functional Unit)</label>
                    <input type="text" name="division" id="editDivision" required
                           placeholder="e.g. Administrative Division">
                </div>
                <div class="mf">
                    <label>SG (Salary Grade)</label>
                    <select name="sg" id="editSg" required>
                        <option value="">-- Select SG --</option>
                        <?php for ($i = 1; $i <= 33; $i++): ?>
                            <option value="Sg-<?= $i ?>">SG-<?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="mf">
                    <label>Position</label>
                    <input type="text" name="position" id="editPosition" required>
                </div>
            </div>

            <div class="modal-section">🎓 Education & Work Background</div>
            <div class="modal-grid">
                <div class="mf">
                    <label>Highest Educational Attainment</label>
                    <input type="text" name="education" id="editEducation" required>
                </div>
                <div class="mf">
                    <label>Previous Work <span style="color:#999;font-weight:normal;">(optional)</span></label>
                    <textarea name="prev_work" id="editPrevWork"></textarea>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" name="update_user" class="btn btn-edit">Update</button>
                <button type="button" onclick="closeModal()" class="btn btn-delete">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Modal open/close ──
function openEditModal(id, employeeId, username, fullName, email, branch, division, sg, position, address, sex, education, prevWork) {
    document.getElementById('editId').value          = id;
    document.getElementById('editEmployeeId').value  = employeeId;
    document.getElementById('editUsername').value    = username;
    document.getElementById('editFullName').value    = fullName;
    document.getElementById('editEmail').value       = email;
    document.getElementById('editBranch').value      = branch;
    document.getElementById('editDivision').value    = division;
    document.getElementById('editSg').value          = sg;
    document.getElementById('editPosition').value    = position;
    document.getElementById('editAddress').value     = address;
    document.getElementById('editSex').value         = sex;
    document.getElementById('editEducation').value   = education;
    document.getElementById('editPrevWork').value    = prevWork;

    // Clear leftover validation states
    document.querySelectorAll('#editForm .invalid, #editForm .valid').forEach(el => {
        el.classList.remove('invalid', 'valid');
    });
    document.querySelectorAll('#editForm .field-error').forEach(el => el.remove());

    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ── Inline validation ──
function showErr(el, msg) {
    clearErr(el);
    el.classList.add('invalid'); el.classList.remove('valid');
    const d = document.createElement('div');
    d.className = 'field-error';
    d.innerHTML = '<span>⚠</span> ' + msg;
    el.insertAdjacentElement('afterend', d);
}
function showOk(el) {
    clearErr(el); el.classList.remove('invalid'); el.classList.add('valid');
}
function clearErr(el) {
    const n = el.nextElementSibling;
    if (n && n.classList.contains('field-error')) n.remove();
}
function validateEl(el) {
    const val = el.value.trim(), name = el.name;
    if (el.hasAttribute('required') && val === '') { showErr(el, 'This field is required.'); return false; }
    if (name === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showErr(el, 'Invalid email format.'); return false; }
    if (name === 'password' && val && val.length < 6) { showErr(el, 'Password must be at least 6 characters.'); return false; }
    if (el.hasAttribute('required')) showOk(el);
    return true;
}

['createForm','editForm'].forEach(formId => {
    const f = document.getElementById(formId);
    if (!f) return;
    f.querySelectorAll('input, select, textarea').forEach(el => {
        el.addEventListener('blur', () => validateEl(el));
        el.addEventListener('input', () => {
            if (el.classList.contains('invalid')) validateEl(el);
            else { el.classList.remove('invalid'); clearErr(el); }
        });
    });
    f.addEventListener('submit', function(e) {
        let ok = true;
        f.querySelectorAll('input[required], select[required]').forEach(el => {
            if (!validateEl(el)) ok = false;
        });
        if (!ok) {
            e.preventDefault();
            f.querySelector('.invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});

// ── Table: search, sort, paginate ──
const searchInput       = document.getElementById("searchInput");
const table             = document.getElementById("usersTable");
const rowsPerPageSelect = document.getElementById("rowsPerPage");
const paginationDiv     = document.getElementById("pagination");
const sortableHeaders   = document.querySelectorAll(".sortable");

let currentPage = 1, sortColumn = null, sortDirection = "asc";

function getAllRows()      { return Array.from(table.querySelectorAll("tbody tr")); }
function getFilteredRows() {
    const f = searchInput.value.toLowerCase();
    return getAllRows().filter(r => r.innerText.toLowerCase().includes(f));
}
function sortRows(rows) {
    if (sortColumn === null) return rows;
    return rows.sort((a, b) => {
        const at = a.children[sortColumn].innerText.toLowerCase();
        const bt = b.children[sortColumn].innerText.toLowerCase();
        return at < bt ? (sortDirection==="asc"?-1:1) : at > bt ? (sortDirection==="asc"?1:-1) : 0;
    });
}
function displayTable() {
    let rows = sortRows(getFilteredRows());
    const rpp = parseInt(rowsPerPageSelect.value);
    const total = Math.ceil(rows.length / rpp);
    getAllRows().forEach(r => r.style.display = "none");
    rows.slice((currentPage-1)*rpp, currentPage*rpp).forEach(r => r.style.display = "");
    renderPagination(total);
}
function renderPagination(total) {
    paginationDiv.innerHTML = "";
    for (let i = 1; i <= total; i++) {
        const btn = document.createElement("button");
        btn.innerText = i;
        btn.style.cssText = `margin:0 4px;padding:5px 8px;border:1px solid #001f3f;
            background:${i===currentPage?'#001f3f':'white'};
            color:${i===currentPage?'white':'#001f3f'};
            cursor:pointer;border-radius:4px;`;
        btn.onclick = () => { currentPage = i; displayTable(); };
        paginationDiv.appendChild(btn);
    }
}

sortableHeaders.forEach(h => h.addEventListener("click", function() {
    const col = parseInt(this.dataset.column);
    sortDirection = sortColumn === col ? (sortDirection==="asc"?"desc":"asc") : "asc";
    sortColumn = col; currentPage = 1; displayTable();
}));

searchInput.addEventListener("keyup", () => { currentPage = 1; displayTable(); });
rowsPerPageSelect.addEventListener("change", () => { currentPage = 1; displayTable(); });
displayTable();
</script>

<?php include 'footer.php'; ?>