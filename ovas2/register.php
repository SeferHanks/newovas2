<?php
// เริ่ม Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// เคลียร์ค่าตัวแปรป้องกัน Error
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $username = "";
    $email = "";
    $fullname = "";
    $phone = ""; // เพิ่มตัวแปร phone
}

// ถ้าล็อกอินแล้ว ให้เด้งไปหน้า index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']); // รับค่าเบอร์โทร
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if ($password != $confirm_password) {
        $error_msg = "รหัสผ่านยืนยันไม่ตรงกัน!";
    } else {
        // 1. ตรวจสอบว่า Username หรือ Email ซ้ำไหม
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error_msg = "ชื่อผู้ใช้ หรือ อีเมลนี้ ถูกใช้งานแล้ว";
        } else {
            // 2. บันทึกข้อมูล (เพิ่ม phone ลงใน SQL)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'teacher'; 

            // แก้ไข SQL: เพิ่ม phone เข้าไป
            $insert_query = "INSERT INTO users (username, password, fullname, email, phone, role) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_query);
            
            // แก้ไข bind_param: เพิ่ม s อีกตัว และเพิ่มตัวแปร $phone
            $stmt->bind_param("ssssss", $username, $hashed_password, $fullname, $email, $phone, $role);

            if ($stmt->execute()) {
                $success_msg = "สมัครสมาชิกสำเร็จ! กำลังไปหน้าเข้าสู่ระบบ...";
                header("refresh:2;url=login.php");
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - OVAS Booking</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --text-color: #495057;
            --input-radius: 12px;
        }
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f0f2f5;
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 160px;
        }
        
        .main-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-bottom: 100px; 
            padding-left: 20px;
            padding-right: 20px;
        }

        .auth-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: none;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .auth-card::before {
            content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: var(--primary-gradient);
        }
        .auth-header { text-align: center; margin-bottom: 30px; }
        .auth-header h4 { font-weight: 700; color: #333; }
        
        .input-group-text {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-right: none;
            border-top-left-radius: var(--input-radius);
            border-bottom-left-radius: var(--input-radius);
            color: #adb5bd;
            padding-left: 15px; padding-right: 15px;
        }
        .form-control {
            border: 1px solid #dee2e6;
            border-left: none;
            border-top-right-radius: var(--input-radius);
            border-bottom-right-radius: var(--input-radius);
            padding: 12px;
        }
        .input-group:focus-within .input-group-text { border-color: #1e3c72; color: #1e3c72; }
        .form-control:focus { border-color: #1e3c72; box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.15); }

        .btn-primary-gradient {
            background: var(--primary-gradient);
            border: none; border-radius: var(--input-radius);
            padding: 14px; font-weight: 500; width: 100%; margin-top: 15px; transition: all 0.3s;
        }
        .btn-primary-gradient:hover {
            opacity: 0.95; transform: translateY(-1px); box-shadow: 0 4px 15px rgba(30, 60, 114, 0.2);
        }
        .auth-footer { text-align: center; margin-top: 25px; font-size: 0.9rem; }
        .auth-footer a { color: #1e3c72; text-decoration: none; font-weight: 600; }
        
        .animate__fadeIn { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        footer { flex-shrink: 0; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="auth-card animate__fadeIn">
            
            <div class="auth-header">
                <h4 class="mb-2">สร้างบัญชีผู้ใช้</h4>
                <p class="text-muted small">กรอกข้อมูลเพื่อเริ่มต้นใช้งานระบบ</p>
            </div>

            <?php if($error_msg): ?>
                <div class="alert alert-danger border-0 bg-danger-subtle text-danger rounded-3 small">
                    <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <?php if($success_msg): ?>
                <div class="alert alert-success border-0 bg-success-subtle text-success rounded-3 text-center py-4">
                    <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                    <?php echo $success_msg; ?>
                </div>
            <?php else: ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label small text-muted ms-1">ชื่อ-นามสกุล (ภาษาไทย)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                        <input type="text" class="form-control" name="fullname" placeholder="เช่น ครูสมชาย ใจดี" required value="<?php echo isset($fullname)?$fullname:''; ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted ms-1">อีเมล</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        <input type="email" class="form-control" name="email" placeholder="name@example.com" required value="<?php echo isset($email)?$email:''; ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted ms-1">เบอร์โทรศัพท์</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="text" class="form-control" name="phone" placeholder="08x-xxx-xxxx" required value="<?php echo isset($phone)?$phone:''; ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted ms-1">ชื่อผู้ใช้ (Username)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="ภาษาอังกฤษ หรือ เบอร์โทร" required value="<?php echo isset($username)?$username:''; ?>">
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label small text-muted ms-1">รหัสผ่าน</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="ตั้งรหัสผ่าน" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label small text-muted ms-1">ยืนยันรหัสผ่าน</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-check"></i></span>
                                <input type="password" class="form-control" name="confirm_password" placeholder="พิมพ์อีกครั้ง" required>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-primary-gradient text-white">
                    ลงทะเบียนใช้งาน
                </button>
            </form>

            <div class="auth-footer">
                <span class="text-muted">มีบัญชีอยู่แล้ว?</span> 
                <a href="login.php">เข้าสู่ระบบ</a>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>