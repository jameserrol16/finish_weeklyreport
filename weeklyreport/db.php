<?php
$conn = new mysqli(
    'localhost',  // ← your MySQL Server
    'root',              // ← your MySQL Username
    '',              // ← your MySQL Password
    'reports'    // ← your Database Name
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->query("SET time_zone = '+08:00'");
date_default_timezone_set('Asia/Manila');
?>

