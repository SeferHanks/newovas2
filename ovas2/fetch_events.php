<?php
require_once 'db_connect.php';

// ตั้งค่า Timezone ให้ตรงกับไทย (สำคัญมากสำหรับการเทียบเวลา)
date_default_timezone_set('Asia/Bangkok');

// --- SQL Query ---
// เพิ่มเงื่อนไข: WHERE b.end_date >= NOW() 
// ความหมาย: ดึงเฉพาะรายการที่เวลายังไม่หมด (อนาคต หรือ กำลังใช้งานอยู่)
// และไม่ดึงรายการที่ถูกปฏิเสธ (rejected)

$sql = "SELECT b.id, b.start_date, b.end_date, b.destination, b.status, b.passengers, 
               v.brand, v.model, v.license_plate, u.fullname
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        JOIN users u ON b.user_id = u.id
        WHERE b.end_date >= NOW() 
        AND (b.status = 'approved' OR b.status = 'pending')";

$result = $conn->query($sql);

$events = array();

while($row = $result->fetch_assoc()) {
    // กำหนดสี: อนุมัติ=เขียว, รอ=เหลือง
    $color = ($row['status'] == 'approved') ? '#198754' : '#ffc107';
    $textColor = ($row['status'] == 'approved') ? '#ffffff' : '#000000';

    $events[] = array(
        'id' => $row['id'],
        'title' => $row['destination'], // แสดงสถานที่ในปฏิทิน
        'start' => $row['start_date'],
        'end' => $row['end_date'],
        'color' => $color,
        'textColor' => $textColor,
        
        // ข้อมูลเพิ่มเติมสำหรับแสดงใน Modal (Popup)
        'extendedProps' => array(
            'status' => $row['status'],
            'car' => $row['brand'] . ' ' . $row['model'] . ' (' . $row['license_plate'] . ')',
            'user' => $row['fullname'],
            'passengers' => $row['passengers']
        )
    );
}

// ส่งค่ากลับเป็น JSON ให้ปฏิทิน
echo json_encode($events);
?>