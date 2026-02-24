<?php
session_name('jo_session');
session_start();
require "db.php";

if(isset($_POST['ajax']) && $_POST['ajax'] === 'profile'){
    if(isset($_FILES['profile_picture'])){
        $file = $_FILES['profile_picture'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg','jpeg','png','gif'];

        if(!in_array(strtolower($ext), $allowed)){
            echo json_encode(['status'=>'error','message'=>'Invalid file type']);
            exit;
        }

        $newName = "profile_" . $_SESSION['user_id'] . "." . $ext;
        $uploadDir = "uploads/";
        $target = $uploadDir . $newName;

        if(move_uploaded_file($file['tmp_name'], $target)){
            // Save to DB if column exists
            $check = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
            if($check && $check->num_rows > 0){
                $stmt = $conn->prepare("UPDATE users SET profile_picture=? WHERE id=?");
                $stmt->bind_param("si",$newName,$_SESSION['user_id']);
                $stmt->execute();
            }
            echo json_encode(['status'=>'success','profile_picture'=>$newName]);
        } else {
            echo json_encode(['status'=>'error','message'=>'Upload failed']);
        }
    }
    exit;
}
?>
