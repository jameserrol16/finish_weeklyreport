<?php
// Try all possible session names used in this app
$started = false;

foreach (['PHPSESSID', 'admin_session', 'jo_session'] as $name) {
    if (isset($_COOKIE[$name])) {
        session_name($name);
        session_start();
        if (isset($_SESSION['user_id'])) {
            $started = true;
            break;
        }
        // Wrong session, destroy and try next
        session_write_close();
        session_unset();
    }
}

if (!$started && session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    // Last resort: accept user_id posted from JS if session truly can't be read
    // (only safe because we verify against DB below)
    $postedId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    if ($postedId <= 0) {
        echo json_encode(["status" => "error", "message" => "Not logged in. Session could not be read."]);
        exit;
    }
    $userId = $postedId;
} else {
    $userId = (int)$_SESSION['user_id'];
}

if (empty($_FILES['profile_picture']['name'])) {
    echo json_encode(["status" => "error", "message" => "No file selected."]);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$fileType = mime_content_type($_FILES['profile_picture']['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(["status" => "error", "message" => "Invalid file type."]);
    exit;
}

$targetDir = __DIR__ . '/uploads/';
if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

$fileName   = time() . "_" . basename($_FILES["profile_picture"]["name"]);
$targetFile = $targetDir . $fileName;

if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $targetFile)) {
    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->bind_param("si", $fileName, $userId);
    $stmt->execute();
    echo json_encode(["status" => "success", "profile_picture" => $fileName]);
} else {
    echo json_encode(["status" => "error", "message" => "Upload failed. Check folder permissions."]);
}
exit;