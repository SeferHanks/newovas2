<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];

    // ตรวจสอบว่าเป็นเจ้าของรายการและสถานะเป็น pending เท่านั้น
    $check = $conn->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'pending'");
    $check->bind_param("ii", $booking_id, $user_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        // ลบรายการ
        $del = $conn->prepare("DELETE FROM bookings WHERE id = ?");
        $del->bind_param("i", $booking_id);
        
        if ($del->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'ยกเลิกรายการสำเร็จ']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาดในการลบ']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถยกเลิกได้ (อาจได้รับการอนุมัติไปแล้ว)']);
    }
}
?>