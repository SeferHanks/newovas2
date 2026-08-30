<?php
// ไฟล์: manage_cars_process.php
session_start();
header('Content-Type: application/json');
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ Admin (Security)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// ตรวจสอบว่ามีการส่ง Action มาหรือไม่
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    // =========================================================
    // 1. เพิ่มข้อมูลรถใหม่ (ADD)
    // =========================================================
    if ($action == 'add') {
        $brand = $_POST['brand'];
        $model = $_POST['model'];
        $license_plate = $_POST['license_plate'];
        $seat_capacity = $_POST['seat_capacity'];
        $status = 'available'; // เริ่มต้นให้เป็นพร้อมใช้งานเสมอ
        $image = '';

        // จัดการอัปโหลดรูปภาพ
        if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0) {
            $ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
            $new_name = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_name;
            
            if (move_uploaded_file($_FILES['car_image']['tmp_name'], $upload_path)) {
                $image = $new_name;
            }
        }

        $sql = "INSERT INTO vehicles (brand, model, license_plate, seat_capacity, image, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $brand, $model, $license_plate, $seat_capacity, $image, $status);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // =========================================================
    // 2. ดึงข้อมูลรถ 1 คัน เพื่อแก้ไข (FETCH SINGLE)
    // =========================================================
    elseif ($action == 'fetch_single') {
        $id = $_POST['id'];
        $sql = "SELECT * FROM vehicles WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        echo json_encode($row);
    }

    // =========================================================
    // 3. อัปเดตข้อมูลรถ (UPDATE)
    // =========================================================
    elseif ($action == 'update') {
        $id = $_POST['vehicle_id'];
        $brand = $_POST['brand'];
        $model = $_POST['model'];
        $license_plate = $_POST['license_plate'];
        $seat_capacity = $_POST['seat_capacity'];
        $status = $_POST['status'];

        // กรณีมีการอัปโหลดรูปใหม่
        if (isset($_FILES['car_image']) && $_FILES['car_image']['error'] == 0) {
            $ext = pathinfo($_FILES['car_image']['name'], PATHINFO_EXTENSION);
            $new_name = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_name;
            
            if (move_uploaded_file($_FILES['car_image']['tmp_name'], $upload_path)) {
                // ลบรูปเก่าทิ้ง (ถ้าต้องการ)
                /*
                $old_sql = "SELECT image FROM vehicles WHERE id = ?";
                $stmt_old = $conn->prepare($old_sql);
                $stmt_old->bind_param("i", $id);
                $stmt_old->execute();
                $old_res = $stmt_old->get_result()->fetch_assoc();
                if($old_res['image'] && file_exists('uploads/'.$old_res['image'])) {
                    unlink('uploads/'.$old_res['image']);
                }
                */

                // อัปเดตข้อมูลพร้อมรูป
                $sql = "UPDATE vehicles SET brand=?, model=?, license_plate=?, seat_capacity=?, status=?, image=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $brand, $model, $license_plate, $seat_capacity, $status, $new_name, $id);
            }
        } else {
            // อัปเดตข้อมูลแบบไม่เปลี่ยนรูป
            $sql = "UPDATE vehicles SET brand=?, model=?, license_plate=?, seat_capacity=?, status=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $brand, $model, $license_plate, $seat_capacity, $status, $id);
        }

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }

    // =========================================================
    // 4. ลบข้อมูลรถ (DELETE)
    // =========================================================
    elseif ($action == 'delete') {
        $id = $_POST['id'];

        // ลบไฟล์รูปภาพก่อน (ถ้ามี)
        $sql_img = "SELECT image FROM vehicles WHERE id = ?";
        $stmt_img = $conn->prepare($sql_img);
        $stmt_img->bind_param("i", $id);
        $stmt_img->execute();
        $res = $stmt_img->get_result()->fetch_assoc();
        
        if ($res['image'] && file_exists('uploads/' . $res['image'])) {
            unlink('uploads/' . $res['image']);
        }

        // ลบข้อมูลจากฐานข้อมูล
        $sql = "DELETE FROM vehicles WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
    }
}
$conn->close();
?>