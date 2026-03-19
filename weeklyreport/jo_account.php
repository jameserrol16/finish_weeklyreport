<?php
session_name('jo_session');
session_start();
require "db.php";

if(isset($_POST['ajax']) && $_POST['ajax'] === 'profile'){
    $fileName  = null;
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

    // Cropped base64 image takes priority
    if (!empty($_POST['cropped_image']) && strpos($_POST['cropped_image'], 'data:image') === 0) {
        $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $_POST['cropped_image']);
        $imageData  = base64_decode($base64Data);
        $fileName   = "profile_" . $_SESSION['user_id'] . "_cropped.jpg";
        file_put_contents($uploadDir . $fileName, $imageData);
    } elseif(isset($_FILES['profile_picture'])){
        $file    = $_FILES['profile_picture'];
        $ext     = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];

        if(!in_array(strtolower($ext), $allowed)){
            echo json_encode(['status'=>'error','message'=>'Invalid file type']);
            exit;
        }

        $fileName = "profile_" . $_SESSION['user_id'] . "." . $ext;
        if(!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)){
            $fileName = null;
        }
    }

    if ($fileName) {
        // Save to DB if column exists
        $check = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
        if($check && $check->num_rows > 0){
            $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
            $stmt->bind_param("si", $fileName, $_SESSION['user_id']);
            $stmt->execute();
        }
        echo json_encode(['status'=>'success','profile_picture'=>$fileName]);
    } else {
        echo json_encode(['status'=>'error','message'=>'Upload failed']);
    }
    exit;
}
?>