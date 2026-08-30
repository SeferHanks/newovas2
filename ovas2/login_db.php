<?php 
session_start();
include('db_connect.php'); // อย่าลืมสร้างไฟล์เชื่อมต่อฐานข้อมูลตามข้อ 1

if (isset($_POST['username'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. ดึงข้อมูล User จากฐานข้อมูล (ใช้ Prepared Statement เพื่อความปลอดภัย)
    $stmt = $conn->prepare("SELECT id, fullname, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username); // "s" หมายถึง string
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row['password'];

        // 2. ตรวจสอบรหัสผ่าน (Verify Hash)
        if (password_verify($password, $hashed_password)) {
            // Login สำเร็จ -> เก็บ Session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['fullname'] = $row['fullname'];
            $_SESSION['role'] = $row['role'];

            // 3. แยกหน้าตาม Role
            if ($row['role'] == 'admin') {
                header("Location: admin_dashboard.php"); // หน้าเจ้าหน้าที่
            } else {
                header("Location: teacher_booking.php"); // หน้าจองรถของครู
            }
        } else {
            // รหัสผ่านผิด
            $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง";
            header("Location: login.php");
        }
    } else {
        // ไม่พบ Username
        $_SESSION['error'] = "ไม่พบชื่อผู้ใช้งานนี้";
        header("Location: login.php");
    }
} else {
    header("Location: login.php");
}
?>