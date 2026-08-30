<?php
// ไฟล์: update_profile.php (ฉบับตัดส่วนรูปภาพออก)
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$fullname = $_POST['fullname'];
$username = $_POST['username'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$new_pass = $_POST['new_password'];
$confirm_pass = $_POST['confirm_password'];

// ตรวจสอบรหัสผ่าน (ถ้ามีการกรอก)
if (!empty($new_pass)) {
    if ($new_pass !== $confirm_pass) {
        echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านใหม่ไม่ตรงกัน']);
        exit();
    }
    $password_hashed = password_hash($new_pass, PASSWORD_DEFAULT);
    // อัปเดตรหัสผ่านด้วย
    $sql = "UPDATE users SET fullname=?, username=?, email=?, phone=?, password=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $fullname, $username, $email, $phone, $password_hashed, $user_id);
} else {
    // ไม่อัปเดตรหัสผ่าน
    $sql = "UPDATE users SET fullname=?, username=?, email=?, phone=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $fullname, $username, $email, $phone, $user_id);
}

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
}

$conn->close();
?>