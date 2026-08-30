<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบว่าเป็น Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_id = $_POST['booking_id'];
    $action = $_POST['action'];

    if ($action == 'approve') {
        // กรณีอนุมัติ: ต้องรับ driver_id มาด้วย
        $driver_id = $_POST['driver_id'];
        $status = 'approved';

        $sql = "UPDATE bookings SET status = ?, driver_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $status, $driver_id, $booking_id);

        if ($stmt->execute()) {
            header("Location: admin_approve.php?status=approved");
        } else {
            echo "Error: " . $conn->error;
        }

    } elseif ($action == 'reject') {
        // กรณีไม่อนุมัติ: ไม่ต้องใส่คนขับ
        $status = 'rejected';
        
        $sql = "UPDATE bookings SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $booking_id);

        if ($stmt->execute()) {
            header("Location: admin_approve.php?status=rejected");
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>