<?php
session_name('admin_session');
session_start();
require "db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";
$error = "";

// Get current user data
$stmt = $conn->prepare("SELECT username, full_name, email, profile_picture, password_hush FROM users WHERE id=?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username  = trim($_POST['username']);
    $fullName  = trim($_POST['full_name']);
    $email     = trim($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword  = $_POST['new_password'];

    $profilePicture = $user['profile_picture'];

    if (!empty($_FILES['profile_picture']['name'])) {

        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES["profile_picture"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
            $profilePicture = $fileName;
        }
    }

    if (!empty($newPassword)) {

        if (!password_verify($currentPassword, $user['password_hush'])) {
            $error = "Current password is incorrect!";
        } else {

            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users 
                SET username=?, full_name=?, email=?, password_hush=?, profile_picture=? 
                WHERE id=?");
            $stmt->bind_param("sssssi",
                $username,
                $fullName,
                $email,
                $hashed,
                $profilePicture,
                $_SESSION['user_id']
            );
            $stmt->execute();

            $_SESSION['username'] = $username;
            $message = "Account updated successfully!";
        }

    } else {

        $stmt = $conn->prepare("UPDATE users 
            SET username=?, full_name=?, email=?, profile_picture=? 
            WHERE id=?");
        $stmt->bind_param("ssssi",
            $username,
            $fullName,
            $email,
            $profilePicture,
            $_SESSION['user_id']
        );
        $stmt->execute();

        $_SESSION['username'] = $username;
        $message = "Account updated successfully!";
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax']) && $_POST['ajax'] === "profile") {
    header('Content-Type: application/json');

    if (!empty($_FILES['profile_picture']['name'])) {

        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        $fileName = time() . "_" . basename($_FILES["profile_picture"]["name"]);
        $targetFile = $targetDir . $fileName;

        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
            $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
            $stmt->bind_param("si", $fileName, $_SESSION['user_id']);
            $stmt->execute();

            echo json_encode(["status"=>"success", "profile_picture"=>$fileName]);
        } else {
            echo json_encode(["status"=>"error","message"=>"Upload failed."]);
        }

    } else {
        echo json_encode(["status"=>"error","message"=>"No file selected."]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    if ($error) {
        echo json_encode(["status" => "error", "message" => $error]);
    } else {
        echo json_encode([
            "status" => "success",
            "message" => $message,
            "username" => $username,
            "full_name" => $fullName,
            "email" => $email,
            "profile_picture" => $profilePicture
        ]);
    }
    exit;
}
?>

<?php include "header.php"; ?>  <!-- ✅ THIS LOADS SIDEBAR + HEADER -->
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

.form-card input {
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    border-radius: 8px;
    border: 1px solid #ccc;
    background: #ffffff;
    color: #222;
    transition: all 0.3s ease;
}

.form-card input:focus {
    outline: none;
    border-color: #4c8cff;
    box-shadow: 0 0 0 3px rgba(76,140,255,0.25);
    transform: scale(1.02);
}

/* DARK MODE SUPPORT */
body.dark-mode .form-card {
    background: rgba(255,255,255,0.08);
}

body.dark-mode .form-card input {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
}
.form-card button {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: none;
    background: linear-gradient(135deg,#4c8cff,#2c6bed);
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s ease;
}

.form-card button:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(76,140,255,0.4);
}

</style>
<div class="main" id="main">

    <div class="page-container">

        <div class="form-card">

            <h2>Edit Account</h2>
            <p>Update your account information below.</p>
            <hr>

            <?php if($message): ?>
                <div style="color:#28a745; margin-bottom:15px;">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <div style="color:#ff4d4d; margin-bottom:15px;">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <form id="accountForm" method="POST" enctype="multipart/form-data">

                <?php if (!empty($user['profile_picture'])): ?>
                    <div style="text-align:center; margin-bottom:20px;">
                        <img id="previewImage"
     src="uploads/<?= htmlspecialchars($user['profile_picture']); ?>"
     style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:4px solid #001f3f;">

                    </div>
                <?php endif; ?>

                <label>Profile Picture</label>
                <input type="file" name="profile_picture" id="profileInput">

                <label>Full Name</label>
                <input type="text" name="full_name"
                       value="<?= htmlspecialchars($user['full_name']); ?>" required>

                <label>Email</label>
                <input type="email" name="email"
                       value="<?= htmlspecialchars($user['email']); ?>" required>

                <label>Username</label>
                <input type="text" name="username"
                       value="<?= htmlspecialchars($user['username']); ?>" required>

                <hr>

                <h4>Change Password</h4>

                <label>Current Password</label>
                <input type="password" name="current_password">

               <label>New Password</label>
<input type="password" name="new_password" id="new_password">

<label>Confirm New Password</label>
<input type="password" name="confirm_password" id="confirm_password">


                <button type="submit" class="dark-btn">
                    Update Account
                </button>

            </form>

        </div>

    </div>

</div>

<script>
// Live image preview
document.getElementById("profileInput").addEventListener("change", function(e){
    const reader = new FileReader();
    reader.onload = function(){
        document.getElementById("previewImage").src = reader.result;
    }
    reader.readAsDataURL(e.target.files[0]);
});
</script>
<script>
document.getElementById("accountForm").addEventListener("submit", function(e){
    e.preventDefault();

    const newPassword = document.getElementById("new_password").value;
    const confirmPassword = document.getElementById("confirm_password").value;

    if(newPassword || confirmPassword){
        if(newPassword !== confirmPassword){
            alert("New Password and Confirm Password do not match!");
            return; // stop form submission
        }
    }

    let formData = new FormData(this);
    formData.append("ajax", "1");

    fetch("edit_account.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {

        if(data.status === "success"){
            alert(data.message);

            // Update profile image instantly
            if(data.profile_picture){
                document.getElementById("previewImage").src =
                    "uploads/" + data.profile_picture + "?" + new Date().getTime();

                let sidebarImage = document.querySelector(".sidebar-profile img");
                if(sidebarImage){
                    sidebarImage.src = "uploads/" + data.profile_picture + "?" + new Date().getTime();
                }
            }

            // Clear password fields after success
            document.getElementById("new_password").value = "";
            document.getElementById("confirm_password").value = "";
            document.querySelector('input[name="current_password"]').value = "";

        } else {
            alert(data.message);
        }

    })
    .catch(err => console.error(err));
});
// Sidebar profile picture live update
const profileInput = document.getElementById("profileInput");
const previewImage = document.getElementById("previewImage");
const sidebarImage = document.querySelector(".sidebar-profile img");

profileInput.addEventListener("change", function(e) {
    const file = this.files[0];
    if (!file) return;

    // Show preview immediately on account page
    const reader = new FileReader();
    reader.onload = function(){
        previewImage.src = reader.result;
    }
    reader.readAsDataURL(file);

    // Upload via AJAX
    const formData = new FormData();
    formData.append("profile_picture", file);
    formData.append("ajax", "profile"); // tells PHP it's an AJAX request

    fetch("edit_account.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === "success" && data.profile_picture) {
            // Update sidebar immediately, prevent caching
            sidebarImage.src = "uploads/" + data.profile_picture + "?" + new Date().getTime();
        } else {
            alert(data.message || "Upload failed.");
        }
    })
    .catch(err => console.error(err));
});

</script>

<?php include "footer.php"; ?> <!-- ✅ THIS CLOSES LAYOUT -->
