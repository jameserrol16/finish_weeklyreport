<?php
session_name('admin_session');
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error   = "";

// Get current user data — column names match the actual DB schema
$stmt = $conn->prepare("SELECT username, full_name, email, profile_picture, password_hush,
                               employee_id, branch, division, sg, position, address, sex, education, prev_work
                        FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username        = trim($_POST['username']);
    $fullName        = trim($_POST['full_name']);
    $email           = trim($_POST['email']);
    $employeeId      = trim($_POST['employee_id']);
    $branch          = trim($_POST['branch']);
    $division        = trim($_POST['division']);
    $sg              = trim($_POST['sg']);
    $position        = trim($_POST['position']);
    $address         = trim($_POST['address']);
    $sex             = trim($_POST['sex']);
    $education       = trim($_POST['education']);
    $prevWork        = trim($_POST['prev_work']);
    $currentPassword = $_POST['current_password'];
    $newPassword     = $_POST['new_password'];

    $profilePicture  = $user['profile_picture'];

    // Employee ID uniqueness check (exclude current user)
    if ($employeeId !== '') {
        $chk = $conn->prepare("SELECT id FROM users WHERE employee_id = ? AND id != ?");
        $chk->bind_param("si", $employeeId, $_SESSION['user_id']);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = "Employee ID already exists. Please use a different one.";
        }
        $chk->close();
    }

    if (!empty($_FILES['profile_picture']['name'])) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName   = time() . "_" . basename($_FILES["profile_picture"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
            $profilePicture = $fileName;
        }
    } elseif (!empty($_POST['cropped_image']) && strpos($_POST['cropped_image'], 'data:image') === 0) {
        $targetDir  = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $_POST['cropped_image']);
        $imageData  = base64_decode($base64Data);
        $fileName   = time() . "_cropped_" . $_SESSION['user_id'] . ".jpg";
        file_put_contents($targetDir . $fileName, $imageData);
        $profilePicture = $fileName;
    }

    if (empty($error) && !empty($newPassword)) {
        if (!password_verify($currentPassword, $user['password_hush'])) {
            $error = "Current password is incorrect!";
        } else {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users
                SET username=?, full_name=?, email=?, password_hush=?, profile_picture=?,
                    employee_id=?, branch=?, division=?, sg=?, position=?, address=?, sex=?, education=?, prev_work=?
                WHERE id=?");
            $stmt->bind_param("ssssssssssssssi",
                $username, $fullName, $email, $hashed, $profilePicture,
                $employeeId, $branch, $division, $sg, $position, $address, $sex, $education, $prevWork,
                $_SESSION['user_id']
            );
            $stmt->execute();

            $_SESSION['username'] = $username;
            $message = "Account updated successfully!";
        }
    } elseif (empty($error)) {
        $stmt = $conn->prepare("UPDATE users
            SET username=?, full_name=?, email=?, profile_picture=?,
                employee_id=?, branch=?, division=?, sg=?, position=?, address=?, sex=?, education=?, prev_work=?
            WHERE id=?");
        $stmt->bind_param("ssssssssssssssi",
            $username, $fullName, $email, $profilePicture,
            $employeeId, $branch, $division, $sg, $position, $address, $sex, $education, $prevWork,
            $_SESSION['user_id']
        );
        $stmt->execute();

        $_SESSION['username'] = $username;
        $message = "Account updated successfully!";
    }
}

// AJAX: profile picture only
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['ajax'] === "profile") {
    header('Content-Type: application/json');

    $fileName = null;
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // Cropped base64 image takes priority
    if (!empty($_POST['cropped_image']) && strpos($_POST['cropped_image'], 'data:image') === 0) {
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $_POST['cropped_image']);
        $imageData  = base64_decode($base64Data);
        $fileName   = time() . "_cropped_" . $_SESSION['user_id'] . ".jpg";
        file_put_contents($targetDir . $fileName, $imageData);
    } elseif (!empty($_FILES['profile_picture']['name'])) {
        $fileName   = time() . "_" . basename($_FILES["profile_picture"]["name"]);
        $targetFile = $targetDir . $fileName;
        if (!move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
            $fileName = null;
        }
    }

    if ($fileName) {
        $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
        $stmt->bind_param("si", $fileName, $_SESSION['user_id']);
        $stmt->execute();
        echo json_encode(["status" => "success", "profile_picture" => $fileName]);
    } else {
        echo json_encode(["status" => "error", "message" => "No file selected or upload failed."]);
    }
    exit;
}

// AJAX: full form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($error) {
        echo json_encode(["status" => "error", "message" => $error]);
    } else {
        echo json_encode([
            "status"          => "success",
            "message"         => $message,
            "username"        => $username,
            "full_name"       => $fullName,
            "email"           => $email,
            "profile_picture" => $profilePicture,
            "employee_id"     => $employeeId,
            "branch"          => $branch,
            "division"        => $division,
            "sg"              => $sg,
            "position"        => $position,
            "address"         => $address,
            "sex"             => $sex,
            "education"       => $education,
            "prev_work"       => $prevWork
        ]);
    }
    exit;
}
?>

<?php include "header.php"; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<style>
/* EDIT ACCOUNT PAGE ONLY */

.page-container {
    display: flex;
    justify-content: center;
    padding: 40px 20px;
}

.form-card {
    width: 100%;
    max-width: 750px;
    padding: 35px;
    border-radius: 14px;
    backdrop-filter: blur(12px);
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 8px 30px rgba(0,0,0,0.25);
}

.form-card label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 13px;
}

.form-card input,
.form-card select,
.form-card textarea {
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    border-radius: 8px;
    border: 1px solid #ccc;
    background: #ffffff;
    color: #222;
    font-size: 14px;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.form-card textarea {
    resize: vertical;
    min-height: 90px;
}

.form-card input:focus,
.form-card select:focus,
.form-card textarea:focus {
    outline: none;
    border-color: #4c8cff;
    box-shadow: 0 0 0 3px rgba(76,140,255,0.25);
    transform: scale(1.01);
}

.field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 18px;
}

@media (max-width: 560px) {
    .field-row { grid-template-columns: 1fr; }
}

/* DARK MODE */
body.dark-mode .form-card {
    background: rgba(255,255,255,0.08);
}
body.dark-mode .form-card input,
body.dark-mode .form-card select,
body.dark-mode .form-card textarea {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
}
body.dark-mode .form-card select option {
    background: #1a1a2e;
    color: #fff;
}

.form-card button[type="submit"] {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg, #4c8cff, #2c6bed);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s ease;
    font-size: 15px;
    margin-top: 6px;
}

.form-card button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(76,140,255,0.4);
}

.section-title {
    font-size: 15px;
    font-weight: 700;
    margin: 24px 0 14px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(128,128,128,0.3);
    color: #4c8cff;
}
</style>

<div class="main" id="main">
    <div class="page-container">
        <div class="form-card">

            <h2>Edit Account</h2>
            <p>Update your account information below.</p>
            <hr>

            <?php if ($message): ?>
                <div style="color:#28a745; margin-bottom:15px;"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div style="color:#ff4d4d; margin-bottom:15px;"><?= $error ?></div>
            <?php endif; ?>

            <form id="accountForm" method="POST" enctype="multipart/form-data">

                <!-- ───── Profile Picture ───── -->
                <div style="text-align:center; margin-bottom:24px;">
                    <img id="previewImage"
                         src="uploads/<?= htmlspecialchars($user['profile_picture'] ?? 'default.png'); ?>"
                         style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #001f3f;display:block;margin:0 auto 14px;">

                    <div style="display:flex;justify-content:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" onclick="document.getElementById('profileInput').click()"
                            style="padding:8px 16px;border-radius:8px;border:1px solid #ccc;background:#f4f6f9;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px;">
                            🖼️ Choose Image
                        </button>
                        <button type="button" onclick="openCamera()"
                            style="padding:8px 16px;border-radius:8px;border:1px solid #ccc;background:#f4f6f9;cursor:pointer;font-size:13px;display:flex;align-items:center;gap:6px;">
                            📷 Take Photo
                        </button>
                    </div>

                    <input type="file" name="profile_picture" id="profileInput"
                           accept="image/*" style="display:none;">
                    <!-- Hidden input that holds the cropped base64 image -->
                    <input type="hidden" name="cropped_image" id="croppedImageInput">
                </div>

                <!-- ── Crop Modal ── -->
                <div id="cropModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:99998;align-items:center;justify-content:center;">
                    <div style="background:#fff;border-radius:14px;padding:24px;max-width:480px;width:94%;text-align:center;">
                        <h3 style="margin:0 0 14px;font-size:15px;">✂️ Crop Your Photo</h3>
                        <div style="max-height:340px;overflow:hidden;border-radius:8px;">
                            <img id="cropImage" style="max-width:100%;display:block;">
                        </div>
                        <div style="display:flex;gap:10px;margin-top:16px;justify-content:center;">
                            <button type="button" onclick="applyCrop()"
                                style="padding:10px 22px;background:#001f3f;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:600;">
                                ✅ Apply Crop
                            </button>
                            <button type="button" onclick="cancelCrop()"
                                style="padding:10px 22px;background:#ccc;color:#333;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Camera Modal -->
                <div id="cameraModal" style="
                    display:none; position:fixed; inset:0;
                    background:rgba(0,0,0,0.7); z-index:99999;
                    align-items:center; justify-content:center;">
                    <div style="background:white;border-radius:14px;padding:24px;max-width:420px;width:90%;text-align:center;">
                        <h3 style="margin:0 0 14px;">Take a Photo</h3>
                        <video id="cameraFeed" autoplay playsinline
                               style="width:100%;border-radius:10px;background:#000;"></video>
                        <canvas id="cameraCanvas" style="display:none;"></canvas>
                        <div style="display:flex;gap:10px;margin-top:14px;justify-content:center;">
                            <button type="button" onclick="capturePhoto()"
                                style="padding:10px 20px;background:#001f3f;color:white;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                                📸 Capture
                            </button>
                            <button type="button" onclick="closeCamera()"
                                style="padding:10px 20px;background:#ccc;color:#333;border:none;border-radius:8px;cursor:pointer;font-size:14px;">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ───── Personal Information ───── -->
                <div class="section-title">👤 Personal Information</div>

                <label>Employee ID</label>
                <input type="text" name="employee_id"
                       placeholder="e.g. NTC-2024-001"
                       value="<?= htmlspecialchars($user['employee_id'] ?? ''); ?>">

                <label>Full Name</label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($user['full_name']); ?>" required>

                <label>Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($user['email']); ?>" required>

                <label>Username</label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($user['username']); ?>" required>

                <div class="field-row">
                    <div>
                        <label>Sex</label>
                        <select name="sex">
                            <option value="">— Select —</option>
                            <option value="Male"   <?= ($user['sex'] === 'Male')   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($user['sex'] === 'Female') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div>
                        <label>Address</label>
                        <input type="text" name="address"
                               placeholder="e.g. Bocaue, Bulacan"
                               value="<?= htmlspecialchars($user['address'] ?? ''); ?>">
                    </div>
                </div>

                <!-- ───── Employment Details ───── -->
                <div class="section-title">🏢 Employment Details</div>

                <div class="field-row">
                    <div>
                        <label>Branch (Regional Office)</label>
                        <input type="text" name="branch"
                               placeholder="e.g. NCR Branch, Region III Branch"
                               value="<?= htmlspecialchars($user['branch'] ?? ''); ?>">
                    </div>
                    <div>
                        <label>Division (Functional Unit)</label>
                        <input type="text" name="division"
                               placeholder="e.g. Administrative Division, Legal Division"
                               value="<?= htmlspecialchars($user['division'] ?? ''); ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div>
                        <label>Salary Grade (SG)</label>
                        <select name="sg">
                            <option value="">— Select SG —</option>
                            <?php for ($i = 1; $i <= 33; $i++):
                                $val = "Sg-$i"; ?>
                                <option value="<?= $val ?>"
                                    <?= ($user['sg'] === $val) ? 'selected' : '' ?>>
                                    SG-<?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label>Position / Designation</label>
                        <input type="text" name="position"
                               placeholder="e.g. Administrative Officer"
                               value="<?= htmlspecialchars($user['position'] ?? ''); ?>">
                    </div>
                </div>

                <!-- ───── Educational Background ───── -->
                <div class="section-title">🎓 Educational Background</div>

                <label>Highest Educational Attainment</label>
                <input type="text" name="education"
                       placeholder="e.g. Bachelor's Degree in Information Technology"
                       value="<?= htmlspecialchars($user['education'] ?? ''); ?>">

                <!-- ───── Work Experience ───── -->
                <div class="section-title">💼 Previous Work Experience <span style="font-weight:400;font-size:12px;color:#888;">(Optional)</span></div>

                <label>Previous Work Experience</label>
                <textarea name="prev_work"
                          placeholder="e.g. 2018–2021 – Records Officer II, Department of Health&#10;2015–2018 – Administrative Aide VI, LGU Quezon City"><?= htmlspecialchars($user['prev_work'] ?? ''); ?></textarea>

                <!-- ───── Change Password ───── -->
                <div class="section-title">🔒 Change Password</div>

                <label>Current Password</label>
                <input type="password" name="current_password">

                <label>New Password</label>
                <input type="password" name="new_password" id="new_password">

                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password">

                <button type="submit">Update Account</button>

            </form>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script>
const profileInput    = document.getElementById("profileInput");
const previewImage    = document.getElementById("previewImage");
const croppedInput    = document.getElementById("croppedImageInput");
const cropModal       = document.getElementById("cropModal");
const cropImage       = document.getElementById("cropImage");
let   cameraStream    = null;
let   cropperInstance = null;

// ── Open crop modal with chosen image ──
function openCropModal(src) {
    cropImage.src = src;
    cropModal.style.display = "flex";
    if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
    setTimeout(() => {
        cropperInstance = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            movable: true,
            zoomable: true,
            scalable: false,
            background: false,
            autoCropArea: 0.8,
        });
    }, 100);
}

function applyCrop() {
    if (!cropperInstance) return;
    const canvas = cropperInstance.getCroppedCanvas({ width: 300, height: 300 });
    const dataURL = canvas.toDataURL("image/jpeg", 0.9);
    previewImage.src = dataURL;
    croppedInput.value = dataURL;
    cancelCrop();
}

function cancelCrop() {
    cropModal.style.display = "none";
    if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
    profileInput.value = "";
}

// ── File picker → crop modal ──
profileInput.addEventListener("change", function () {
    const file = this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => openCropModal(e.target.result);
    reader.readAsDataURL(file);
});

// ── Camera ──
function openCamera() {
    const modal = document.getElementById("cameraModal");
    modal.style.display = "flex";
    navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false })
        .then(stream => {
            cameraStream = stream;
            document.getElementById("cameraFeed").srcObject = stream;
        })
        .catch(err => {
            alert("Camera access denied or not available.\n" + err.message);
            modal.style.display = "none";
        });
}

function capturePhoto() {
    const video  = document.getElementById("cameraFeed");
    const canvas = document.getElementById("cameraCanvas");
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext("2d").drawImage(video, 0, 0);
    closeCamera();
    openCropModal(canvas.toDataURL("image/png"));
}

function closeCamera() {
    document.getElementById("cameraModal").style.display = "none";
    if (cameraStream) {
        cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    }
}

// ── Form submit ──
document.getElementById("accountForm").addEventListener("submit", function (e) {
    e.preventDefault();

    const newPassword     = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if (newPassword || confirmPassword) {
        if (newPassword !== confirmPassword) {
            alert("New Password and Confirm Password do not match!");
            return;
        }
    }

    const formData = new FormData(this);
    formData.append("ajax", "1");

    // If a cropped image exists, convert it to a file and replace the file input
    if (croppedInput.value) {
        fetch(croppedInput.value)
            .then(r => r.blob())
            .then(blob => {
                const file = new File([blob], "profile_cropped.jpg", { type: "image/jpeg" });
                formData.set("profile_picture", file);
                submitForm(formData);
            });
        return;
    }
    submitForm(formData);
});

function submitForm(formData) {
    fetch("edit_account.php", { method: "POST", body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {
                alert(data.message);

                if (data.profile_picture) {
                    const ts     = "?" + new Date().getTime();
                    const newSrc = "uploads/" + data.profile_picture + ts;
                    previewImage.src = newSrc;

                    const sidebarImage = document.querySelector(".sidebar-profile img");
                    if (sidebarImage) sidebarImage.src = newSrc;

                    document.querySelectorAll("img[data-profile]").forEach(img => {
                        img.src = newSrc;
                    });
                }

                document.getElementById("new_password").value = "";
                document.getElementById("confirm_password").value = "";
                document.querySelector('input[name="current_password"]').value = "";
                croppedInput.value = "";

            } else {
                alert(data.message);
            }
        })
        .catch(err => console.error(err));
}
</script>

<?php include "footer.php"; ?>