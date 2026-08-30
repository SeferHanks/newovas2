<?php
// manage_drivers_process.php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? '';

// --- 1. เพิ่มคนขับ (ADD) ---
if ($action == 'add') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $line_id = $_POST['line_user_id'] ?? ''; 
    $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : NULL;
    $status = 'active';
    
    $image = '';
    // จัดการอัปโหลดรูป
    if (isset($_FILES['driver_image']) && $_FILES['driver_image']['error'] == 0) {
        $ext = pathinfo($_FILES['driver_image']['name'], PATHINFO_EXTENSION);
        $new_name = 'driver_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['driver_image']['tmp_name'], 'uploads/' . $new_name)) {
            $image = $new_name;
        }
    }

    $stmt = $conn->prepare("INSERT INTO drivers (name, phone, line_user_id, vehicle_id, status, image) VALUES (?, ?, ?, ?, ?, ?)");
    // แก้ไข: sssiss (เพิ่ม s ตัวท้ายสำหรับ image)
    $stmt->bind_param("sssiss", $name, $phone, $line_id, $vehicle_id, $status, $image);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
} 

// --- 2. ดึงข้อมูลมาแก้ไข (FETCH SINGLE) ---
elseif ($action == 'fetch_single') {
    $id = $_POST['id'];
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    echo json_encode($row);
} 

// --- 3. อัปเดตข้อมูล (UPDATE) ---
elseif ($action == 'update') {
    $id = $_POST['driver_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $line_id = $_POST['line_user_id'] ?? ''; 
    $vehicle_id = !empty($_POST['vehicle_id']) ? $_POST['vehicle_id'] : NULL;
    $status = $_POST['status'];

    // กรณีเปลี่ยนรูปภาพ
    if (isset($_FILES['driver_image']) && $_FILES['driver_image']['error'] == 0) {
        $ext = pathinfo($_FILES['driver_image']['name'], PATHINFO_EXTENSION);
        $new_name = 'driver_' . uniqid() . '.' . $ext;
        if (move_uploaded_file($_FILES['driver_image']['tmp_name'], 'uploads/' . $new_name)) {
            // ลบรูปเก่า
            $old_sql = $conn->query("SELECT image FROM drivers WHERE id = $id");
            $old_img = $old_sql->fetch_assoc()['image'];
            if($old_img && file_exists('uploads/'.$old_img)){ unlink('uploads/'.$old_img); }

            $sql = "UPDATE drivers SET name=?, phone=?, line_user_id=?, vehicle_id=?, status=?, image=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            // sssissi (ถูกต้องแล้วสำหรับกรณีมีรูป)
            $stmt->bind_param("sssissi", $name, $phone, $line_id, $vehicle_id, $status, $new_name, $id);
        }
    } else {
        // อัปเดตแบบไม่มีรูป (กรณีที่คุณเจอปัญหา)
        $sql = "UPDATE drivers SET name=?, phone=?, line_user_id=?, vehicle_id=?, status=? WHERE id=?";
        $stmt = $conn->prepare($sql);
        
        // *** แก้ไขตรงนี้ครับ ***
        // ของเดิม: "sssiis" (ตัวที่ 5 เป็น i ทำให้ status กลายเป็น 0)
        // ของใหม่: "sssisi" (เปลี่ยนตัวที่ 5 เป็น s เพื่อรับค่า string 'active'/'inactive')
        $stmt->bind_param("sssisi", $name, $phone, $line_id, $vehicle_id, $status, $id);
    }

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
} 

// --- 4. ลบข้อมูล (DELETE) ---
elseif ($action == 'delete') {
    $id = $_POST['id'];
    
    $old_sql = $conn->query("SELECT image FROM drivers WHERE id = $id");
    $old_img = $old_sql->fetch_assoc()['image'];
    if($old_img && file_exists('uploads/'.$old_img)){ unlink('uploads/'.$old_img); }

    $stmt = $conn->prepare("DELETE FROM drivers WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}

$conn->close();
?>