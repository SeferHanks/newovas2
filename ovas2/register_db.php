<?php 
session_start();
include('db_connect.php');

if (isset($_POST['username'])) {
    // รับค่าจากฟอร์ม
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']); // <-- 1. เพิ่มรับค่าเบอร์โทร
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'teacher'; 

    if ($password != $confirm_password) {
        $_SESSION['error'] = "รหัสผ่านไม่ตรงกัน";
        header("Location: register.php");
        exit();
    }

    // เช็คว่า username ซ้ำไหม
    $check_query = "SELECT * FROM users WHERE username = '$username' LIMIT 1";
    $result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($result) > 0) {
        $_SESSION['error'] = "ชื่อผู้ใช้งานนี้มีคนใช้แล้ว";
        header("Location: register.php");
        exit();
    }

    // เข้ารหัสรหัสผ่าน
    $password_hashed = password_hash($password, PASSWORD_DEFAULT);

    // บันทึกข้อมูล (เพิ่ม phone ลงในคำสั่ง SQL)
    // <-- 2. เพิ่ม column phone และตัวแปร '$phone' ใน SQL
    $sql = "INSERT INTO users (username, password, fullname, phone, role) 
            VALUES ('$username', '$password_hashed', '$fullname', '$phone', '$role')";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ";
        header("Location: login.php");
    } else {
        $_SESSION['error'] = "เกิดข้อผิดพลาด: " . mysqli_error($conn);
        header("Location: register.php");
    }
}
?>