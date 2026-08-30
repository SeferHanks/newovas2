<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบก่อนทำรายการ']);
    exit();
}

// รับค่าจาก Form
$vehicle_id = $_POST['vehicle_id'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];
$destination = $_POST['destination'];
$passengers = $_POST['passengers'];
$purpose = $_POST['purpose'];
$user_id = $_SESSION['user_id'];

// --- 1. ตรวจสอบเวลาทำการ (08:00 - 16:30) ---
// ดึงเฉพาะเวลา (ชั่วโมง:นาที) มาตรวจสอบ
$start_time = date('H:i', strtotime($start_date));
$end_time = date('H:i', strtotime($end_date));

// เวลาทำการ
$min_time = '08:00';
$max_time = '16:30';

if ($start_time < $min_time || $start_time > $max_time) {
    echo json_encode(['status' => 'error', 'message' => 'เวลาเริ่มต้นต้องอยู่ในช่วง 08:00 - 16:30 น. เท่านั้น']);
    exit();
}

if ($end_time < $min_time || $end_time > $max_time) {
    echo json_encode(['status' => 'error', 'message' => 'เวลาสิ้นสุดต้องอยู่ในช่วง 08:00 - 16:30 น. เท่านั้น']);
    exit();
}

// --- 2. ตรวจสอบลำดับเวลา (Start ต้องน้อยกว่า End) ---
if ($start_date >= $end_date) {
    echo json_encode(['status' => 'time_error', 'message' => 'เวลาสิ้นสุดต้องมากกว่าเวลาเริ่มต้น']);
    exit();
}

// --- 3. ตรวจสอบรถว่าง (Availability Check) ---
// เช็คว่ามีรายการอื่นที่ทับซ้อนกันไหม (ไม่นับรายการที่ถูกปฏิเสธ)
$sql_check = "SELECT id FROM bookings 
              WHERE vehicle_id = ? 
              AND status != 'rejected'
              AND (
                  (start_date < ? AND end_date > ?) OR  -- ช่วงเวลาใหม่ คร่อมรายการเดิม
                  (start_date >= ? AND start_date < ?) OR -- เวลาเริ่มใหม่ อยู่ในรายการเดิม
                  (end_date > ? AND end_date <= ?)        -- เวลาจบใหม่ อยู่ในรายการเดิม
              )";

// หมายเหตุ: Logic การเช็คเวลาชนกันแบบละเอียด
// (NewStart < OldEnd) AND (NewEnd > OldStart)

$check_stmt = $conn->prepare("SELECT id FROM bookings WHERE vehicle_id = ? AND status != 'rejected' AND (start_date < ? AND end_date > ?)");
$check_stmt->bind_param("iss", $vehicle_id, $end_date, $start_date);
$check_stmt->execute();
$result_check = $check_stmt->get_result();

if ($result_check->num_rows > 0) {
    echo json_encode(['status' => 'busy']);
    exit();
}

// --- 4. บันทึกข้อมูลลงฐานข้อมูล ---
$sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, destination, passengers, purpose, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iisssis", $user_id, $vehicle_id, $start_date, $end_date, $destination, $passengers, $purpose);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'จองรถสำเร็จ รอการอนุมัติ']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $conn->error]);
}

$conn->close();
?>