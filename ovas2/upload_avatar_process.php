<?php
// ไฟล์: upload_avatar_process.php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ตรวจสอบว่ามีการส่งไฟล์รูปมาหรือไม่
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    
    // สร้างโฟลเดอร์ uploads ถ้ายังไม่มี
    if (!file_exists('uploads')) {
        mkdir('uploads', 0777, true);
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        // ตั้งชื่อไฟล์ใหม่ (ป้องกันชื่อซ้ำและ Cache)
        $new_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $ext;
        $destination = 'uploads/' . $new_filename;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
            
            // 1. ลบรูปเก่าทิ้ง (เพื่อไม่ให้ไฟล์ขยะล้น Server)
            $sql_old = "SELECT profile_image FROM users WHERE id = ?";
            $stmt_old = $conn->prepare($sql_old);
            $stmt_old->bind_param("i", $user_id);
            $stmt_old->execute();
            $res_old = $stmt_old->get_result();
            $row_old = $res_old->fetch_assoc();
            
            if (!empty($row_old['profile_image']) && file_exists('uploads/' . $row_old['profile_image'])) {
                unlink('uploads/' . $row_old['profile_image']);
            }

            // 2. อัปเดตชื่อไฟล์ลงฐานข้อมูลทันที
            $update_sql = "UPDATE users SET profile_image = ? WHERE id = ?";
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bind_param("si", $new_filename, $user_id);
            
            if ($stmt_update->execute()) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => 'อัปโหลดรูปภาพสำเร็จ',
                    'new_image' => $new_filename // ส่งชื่อรูปกลับไปแสดงผล
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database update failed']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'นามสกุลไฟล์ไม่ถูกต้อง (ต้องเป็น jpg, png, gif)']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'ไม่พบไฟล์รูปภาพ']);
}

$conn->close();
?>