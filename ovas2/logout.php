<?php 
session_start();
session_destroy(); // ล้างข้อมูล Session ทั้งหมด
header("Location: login.php"); // ดีดกลับไปหน้า Login
?>