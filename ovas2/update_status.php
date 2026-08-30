<?php
include 'db_connect.php';

$booking_id = $_POST['booking_id'];
$driver_id = $_POST['driver_id'];
$action = $_POST['action']; // รับค่า 'approve' หรือ 'reject'

if ($action == 'approve') {
    $status = 'approved';
    // อัปเดตสถานะและคนขับ
    $sql = "UPDATE bookings SET status='$status', driver_id='$driver_id' WHERE id='$booking_id'";
} else {
    $status = 'rejected';
    // กรณีไม่อนุมัติ ไม่ต้องใส่คนขับ
    $sql = "UPDATE bookings SET status='$status' WHERE id='$booking_id'";
}

if ($conn->query($sql) === TRUE) {
    echo "บันทึกข้อมูลเรียบร้อย";
    header("Location: admin_approve.php");
} else {
    echo "Error: " . $conn->error;
}
?>