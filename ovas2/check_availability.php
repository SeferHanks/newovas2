<?php
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $vehicle_id = $_POST['vehicle_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // เช็คว่าส่งค่ามาครบไหม
    if(!empty($vehicle_id) && !empty($start_date) && !empty($end_date)) {
        
        // เช็คการจองซ้อน (สูตรเดิม)
        $sql = "SELECT id FROM bookings 
                WHERE vehicle_id = ? 
                AND status != 'rejected' 
                AND (start_date < ? AND end_date > ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $vehicle_id, $end_date, $start_date);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            echo 'busy'; // ไม่ว่าง
        } else {
            echo 'available'; // ว่าง
        }
    }
}
?>