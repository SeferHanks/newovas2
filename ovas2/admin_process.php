<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';
require_once 'line_helper.php'; 

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action']; 
    $booking_id = $_POST['booking_id'];

    // ==================================================================================
    // 1. กรณีอนุมัติ (APPROVE) -> ส่งหาคนขับ และ ส่งเข้ากลุ่ม
    // ==================================================================================
    if ($action == 'approve') {
        $driver_id = $_POST['driver_id'];
        
        $sql = "UPDATE bookings SET status = 'approved', driver_id = ?, reject_reason = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $driver_id, $booking_id);
        
        if ($stmt->execute()) {
            
            // --- ดึงข้อมูลการจองและคนขับ ---
            $sql_booking = "SELECT b.*, u.fullname, u.phone, u.email, v.brand, v.model, v.license_plate 
                            FROM bookings b 
                            JOIN users u ON b.user_id = u.id 
                            JOIN vehicles v ON b.vehicle_id = v.id
                            WHERE b.id = ?";
            $stmt_b = $conn->prepare($sql_booking);
            $stmt_b->bind_param("i", $booking_id);
            $stmt_b->execute();
            $booking_data = $stmt_b->get_result()->fetch_assoc();

            $sql_driver = "SELECT name, phone, line_user_id FROM drivers WHERE id = ?";
            $stmt_d = $conn->prepare($sql_driver);
            $stmt_d->bind_param("i", $driver_id);
            $stmt_d->execute();
            $driver_res = $stmt_d->get_result()->fetch_assoc();

            if ($booking_data && $driver_res) {
                
                $driverLineId = isset($driver_res['line_user_id']) ? $driver_res['line_user_id'] : '';

                $jobData = [
                    'id' => $booking_id,
                    'destination' => $booking_data['destination'],
                    'date_range' => date('d/m H:i', strtotime($booking_data['start_date'])) . ' - ' . date('d/m H:i', strtotime($booking_data['end_date'])),
                    'user_name' => $booking_data['fullname'],
                    'user_phone' => $booking_data['phone'],
                    'car_info' => $booking_data['brand'] . " " . $booking_data['model'] . " (" . $booking_data['license_plate'] . ")",
                    'remark' => $booking_data['purpose'],
                    'driver_info' => $driver_res['name'] . " (" . $driver_res['phone'] . ")",
                    'passengers' => $booking_data['passengers']
                ];

                // 1. ส่งหาคนขับ (Private)
                if (!empty($driverLineId)) {
                    sendLineToDriver($driverLineId, $jobData);
                }

                // 2. ส่งเข้ากลุ่ม (แจ้งเตือนอนุมัติ - สีเขียว)
                sendApproveFlexToGroup($jobData);
            }

            echo json_encode(['status' => 'success', 'message' => 'อนุมัติรายการเรียบร้อย']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }

    // ==================================================================================
    // 2. กรณีปฏิเสธ (REJECT) -> ส่งเข้ากลุ่มอย่างเดียว
    // ==================================================================================
    } elseif ($action == 'reject') {
        $reason = $_POST['reject_reason'];
        
        $sql = "UPDATE bookings SET status = 'rejected', driver_id = NULL, reject_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $reason, $booking_id);
        
        if ($stmt->execute()) {

            // --- ดึงข้อมูลเพิ่ม (วันเวลา + รถ) ---
            $sql_b = "SELECT b.destination, b.start_date, b.end_date, u.fullname, v.brand, v.model, v.license_plate
                      FROM bookings b 
                      JOIN users u ON b.user_id = u.id 
                      JOIN vehicles v ON b.vehicle_id = v.id
                      WHERE b.id = ?";
            $stmt_b = $conn->prepare($sql_b);
            $stmt_b->bind_param("i", $booking_id);
            $stmt_b->execute();
            $res_b = $stmt_b->get_result()->fetch_assoc();
            
            if ($res_b) {
                $rejectData = [
                    'id' => $booking_id,
                    'user_name' => $res_b['fullname'],
                    'destination' => $res_b['destination'],
                    'reason' => $reason,
                    // [เพิ่ม] ส่งวันเวลาและรถไปด้วย
                    'date_range' => date('d/m H:i', strtotime($res_b['start_date'])) . ' - ' . date('d/m H:i', strtotime($res_b['end_date'])),
                    'car_info' => $res_b['brand'] . " " . $res_b['model'] . " (" . $res_b['license_plate'] . ")"
                ];
                
                // ส่งแจ้งเตือนไม่อนุมัติเข้ากลุ่ม (สีแดง)
                sendRejectFlexToGroup($rejectData);
            }

            echo json_encode(['status' => 'success', 'message' => 'ปฏิเสธรายการเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }
}
$conn->close();
?>