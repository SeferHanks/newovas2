<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_id = $_POST['booking_id'];
    $rating = $_POST['rating'];
    $comment = $_POST['comment'];
    $user_id = $_SESSION['user_id'];

    // อัปเดตคะแนนลงในตาราง bookings
    // ต้องเช็ค user_id ด้วยเพื่อความปลอดภัย (ห้ามคนอื่นมาแอบตัดเกรดแทนเรา)
    $sql = "UPDATE bookings SET rating = ?, rating_comment = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $rating, $comment, $booking_id, $user_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'บันทึกคะแนนเรียบร้อย']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด']);
    }
}
?>