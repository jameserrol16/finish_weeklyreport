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

/**
 * DELETE JO
 */
if (isset($_GET['delete'])) {

    $deleteId = (int) $_GET['delete'];

    if ($deleteId === (int) $_SESSION['user_id']) {
        $errors[] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'jo'");
        $stmt->bind_param("i", $deleteId);

        if ($stmt->execute()) {
            $success = "JO account deleted successfully.";
        } else {
            $errors[] = "Failed to delete account.";
        }

        $stmt->close();
    }
}

/**
 * UPDATE JO
 */
if (isset($_POST['update_user'])) {

    $id       = (int) $_POST['id'];
    $username = trim($_POST['username']);
    $fullName = trim($_POST['full_name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if ($username === '' || $fullName === '' || $email === '') {
        $errors[] = "All fields except password are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {

        if ($password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?, password_hush = ?
                WHERE id = ? AND role = 'jo'
            ");
            $stmt->bind_param("ssssi", $username, $fullName, $email, $hashedPassword, $id);

        } else {
            $stmt = $conn->prepare("
                UPDATE users 
                SET username = ?, full_name = ?, email = ?
                WHERE id = ? AND role = 'jo'
            ");
            $stmt->bind_param("sssi", $username, $fullName, $email, $id);
        }

        if ($stmt->execute()) {
            $success = "JO account updated successfully.";
        } else {
            $errors[] = "Update failed.";
        }

        $stmt->close();
    }
}

/**
 * Fetch all JO accounts
 */
$result = $conn->query("SELECT id, username, full_name, email FROM users WHERE role = 'jo' ORDER BY id DESC");
?>

<?php include 'header.php'; ?>

<style>
.table-card {
    background: white;
    padding: 25px;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

body.dark .table-card {
    background: #1e1e1e;
    box-shadow: none;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}

body.dark th, body.dark td {
    border-color: #333;
}

th {
    background: #001f3f;
    color: white;
}

.btn {
    padding: 6px 10px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
}

.btn-edit { background: #007bff; color: white; }
.btn-delete { background: #cc0000; color: white; }

.btn:hover { opacity: 0.85; }

.alert {
    padding: 10px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.alert-error { background: #ffe5e5; color: #b30000; }
.alert-success { background: #e6ffed; color: #006622; }

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 25px;
    border-radius: 8px;
    width: 400px;
}

body.dark .modal-content {
    background: #1e1e1e;
}
</style>

<div class="main" id="main">
    <div class="table-card">

        <h2>Manage JO Accounts</h2>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

       <div style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
    <input type="text" id="searchInput" placeholder="🔍 Search JO..."
        style="padding:8px; width:250px; border-radius:6px; border:1px solid #ccc;">

    <select id="rowsPerPage" style="padding:8px; border-radius:6px;">
        <option value="5">5 rows</option>
        <option value="10" selected>10 rows</option>
        <option value="20">20 rows</option>
    </select>
</div>

<table id="usersTable">
    <thead>
    <tr>
        <th>#</th>
        <th class="sortable" data-column="1">Username ⬍</th>
        <th class="sortable" data-column="2">Full Name ⬍</th>
        <th class="sortable" data-column="3">Email ⬍</th>
        <th>Actions</th>
    </tr>
</thead>

    <tbody>
        <?php 
        $counter = 1;
        while ($row = $result->fetch_assoc()): 
        ?>
            <tr>
                <td><?= $counter++ ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td>
                    <button class="btn btn-edit"
                        onclick="openEditModal(
                            <?= $row['id'] ?>,
                            '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>',
                            '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>'
                        )">
                        Edit
                    </button>

                    <a href="?delete=<?= $row['id'] ?>"
                       onclick="return confirm('Delete this account?')"
                       class="btn btn-delete">
                        Delete
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<div id="pagination" style="margin-top:15px;"></div>

    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <h3>Edit JO Account</h3>
        <form method="POST">
            <input type="hidden" name="id" id="editId">

            <label>Username</label>
            <input type="text" name="username" id="editUsername" required style="width:100%; margin-bottom:10px;">

            <label>Full Name</label>
            <input type="text" name="full_name" id="editFullName" required style="width:100%; margin-bottom:10px;">

            <label>Email</label>
            <input type="email" name="email" id="editEmail" required style="width:100%; margin-bottom:10px;">

            <label>New Password (optional)</label>
            <input type="password" name="password" style="width:100%; margin-bottom:10px;">

            <button type="submit" name="update_user" class="btn btn-edit">Update</button>
            <button type="button" onclick="closeModal()" class="btn btn-delete">Cancel</button>
        </form>
    </div>
</div>

<script>
function openEditModal(id, username, fullName, email) {
    document.getElementById('editId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editFullName').value = fullName;
    document.getElementById('editEmail').value = email;
    document.getElementById('editModal').style.display = "flex";
}

function closeModal() {
    document.getElementById('editModal').style.display = "none";
}
</script>
<script>
const searchInput = document.getElementById("searchInput");
const table = document.getElementById("usersTable");
const rowsPerPageSelect = document.getElementById("rowsPerPage");
const paginationDiv = document.getElementById("pagination");
const sortableHeaders = document.querySelectorAll(".sortable");

let currentPage = 1;
let sortColumn = null;
let sortDirection = "asc";

function getAllRows() {
    return Array.from(table.querySelectorAll("tbody tr"));
}

function getFilteredRows() {
    const filter = searchInput.value.toLowerCase();
    return getAllRows().filter(row =>
        row.innerText.toLowerCase().includes(filter)
    );
}

function sortRows(rows) {
    if (sortColumn === null) return rows;

    return rows.sort((a, b) => {
        const aText = a.children[sortColumn].innerText.toLowerCase();
        const bText = b.children[sortColumn].innerText.toLowerCase();

        if (aText < bText) return sortDirection === "asc" ? -1 : 1;
        if (aText > bText) return sortDirection === "asc" ? 1 : -1;
        return 0;
    });
}

function displayTable() {
    let rows = getFilteredRows();
    rows = sortRows(rows);

    const rowsPerPage = parseInt(rowsPerPageSelect.value);
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    getAllRows().forEach(row => row.style.display = "none");

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    rows.slice(start, end).forEach(row => row.style.display = "");

    renderPagination(totalPages);
}

function renderPagination(totalPages) {
    paginationDiv.innerHTML = "";

    for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement("button");
        btn.innerText = i;
        btn.style.margin = "0 4px";
        btn.style.padding = "5px 8px";
        btn.style.border = "1px solid #001f3f";
        btn.style.background = i === currentPage ? "#001f3f" : "white";
        btn.style.color = i === currentPage ? "white" : "#001f3f";
        btn.style.cursor = "pointer";
        btn.style.borderRadius = "4px";

        btn.onclick = function () {
            currentPage = i;
            displayTable();
        };

        paginationDiv.appendChild(btn);
    }
}

sortableHeaders.forEach(header => {
    header.addEventListener("click", function () {
        const columnIndex = parseInt(this.dataset.column);

        if (sortColumn === columnIndex) {
            sortDirection = sortDirection === "asc" ? "desc" : "asc";
        } else {
            sortColumn = columnIndex;
            sortDirection = "asc";
        }

        currentPage = 1;
        displayTable();
    });
});

searchInput.addEventListener("keyup", function () {
    currentPage = 1;
    displayTable();
});

rowsPerPageSelect.addEventListener("change", function () {
    currentPage = 1;
    displayTable();
});

displayTable();
</script>

<?php include 'footer.php'; ?>
