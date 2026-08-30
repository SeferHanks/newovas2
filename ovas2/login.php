<?php
// เริ่ม Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connect.php';

// --- ส่วนประมวลผล Login (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password']) || $password == $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            
            $redirect_url = ($user['role'] == 'admin') ? 'admin_approve.php' : 'index.php';

            echo json_encode(['status' => 'success', 'redirect' => $redirect_url]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'รหัสผ่านไม่ถูกต้อง']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ไม่พบชื่อผู้ใช้งานนี้']);
    }
    exit(); 
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - OVAS Booking</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
            max-width: 420px;
            padding: 40px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .auth-card::before {
            content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 6px; background: var(--primary-gradient);
        }
        .auth-header { text-align: center; margin-bottom: 35px; }
        
        /* สไตล์สำหรับโลโก้ */
        .logo-img {
            height: 80px; 
            width: auto; 
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .input-group-text {
            background-color: #f8f9fa; border: 1px solid #dee2e6; border-right: none;
            border-top-left-radius: var(--input-radius); border-bottom-left-radius: var(--input-radius);
            color: #adb5bd; padding-left: 15px; padding-right: 15px;
        }
        .form-control {
            border: 1px solid #dee2e6; border-left: none;
            border-top-right-radius: var(--input-radius); border-bottom-right-radius: var(--input-radius);
            padding: 12px;
        }
        .input-group:focus-within .input-group-text { border-color: #1e3c72; color: #1e3c72; }
        .form-control:focus { border-color: #1e3c72; box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.15); }

        .btn-primary-gradient {
            background: var(--primary-gradient);
            border: none; border-radius: var(--input-radius);
            padding: 14px; font-weight: 500; font-size: 16px;
            letter-spacing: 0.5px; width: 100%; margin-top: 10px;
            transition: all 0.4s ease;
        }
        .btn-primary-gradient:hover {
            opacity: 0.95; transform: translateY(-1px); box-shadow: 0 4px 15px rgba(30, 60, 114, 0.2);
        }
        
        /* ปุ่มตอนสำเร็จ */
        .btn-success-state {
            background: var(--success-gradient) !important;
            box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3) !important;
            transform: scale(1.02);
        }

        .auth-footer { text-align: center; margin-top: 25px; font-size: 0.9rem; }
        .auth-footer a { color: #1e3c72; text-decoration: none; font-weight: 600; }
        
        .animate__fadeIn { animation: fadeIn 0.6s cubic-bezier(0.22, 1, 0.36, 1); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        footer { flex-shrink: 0; }
        .text-custom-blue { color: #1e3c72 !important; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="auth-card animate__fadeIn">
            
            <div class="auth-header">
                <img src="uploads/logo.png" alt="Logo" class="logo-img">
                
                <h4 class="fw-bold text-dark">ยินดีต้อนรับกลับมา</h4>
                <p class="text-muted small">เข้าสู่ระบบจองรถ OVAS</p>
            </div>

            <form id="loginForm">
                <div class="mb-3">
                    <label class="form-label small text-muted ms-1">ชื่อผู้ใช้</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" name="username" placeholder="กรอกชื่อผู้ใช้" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label small text-muted ms-1">รหัสผ่าน</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" name="password" placeholder="กรอกรหัสผ่าน" required>
                    </div>
                </div>

                <button type="submit" id="btnLogin" class="btn btn-primary btn-primary-gradient text-white">
                    เข้าสู่ระบบ
                </button>
            </form>

            <div class="auth-footer">
                <span class="text-muted">ยังไม่มีบัญชีใช่ไหม?</span> 
                <a href="register.php">สมัครสมาชิก</a>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    // ตั้งค่า Toast พื้นฐาน
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    $(document).ready(function(){
        $('#loginForm').on('submit', function(e){
            e.preventDefault();

            let btn = $('#btnLogin');
            let originalText = btn.html();
            
            // 1. เปลี่ยนปุ่มเป็น Loading
            btn.prop('disabled', true).html('<i class="fas fa-circle-notch fa-spin me-2"></i> กำลังตรวจสอบ...');

            $.ajax({
                type: 'POST',
                url: 'login.php', // ตรวจสอบ URL ให้ถูกต้อง (ปกติคือไฟล์ตัวเอง)
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response){
                    
                    if(response.status === 'success'){
                        // 2. ถ้าสำเร็จ
                        
                        // เปลี่ยนปุ่มเป็นสีเขียว
                        btn.addClass('btn-success-state').html('<i class="fas fa-check-circle me-2"></i> เข้าสู่ระบบสำเร็จ');
                        
                        // แจ้งเตือน Toast (สีน้ำเงิน)
                        Toast.fire({
                            icon: 'success',
                            title: 'ยินดีต้อนรับกลับมา!',
                            color: '#1e3c72' // สีข้อความแจ้งเตือน
                        });

                        // รอ 1 วินาที แล้วเปลี่ยนหน้า
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 1000);

                    } else {
                        // 3. ถ้าพลาด
                        
                        // คืนค่าปุ่ม
                        btn.prop('disabled', false).removeClass('btn-success-state').html(originalText);
                        
                        // สั่นกล่อง Login
                        $('.auth-card').addClass('animate__animated animate__shakeX');
                        setTimeout(() => $('.auth-card').removeClass('animate__animated animate__shakeX'), 500);

                        // แจ้งเตือน Toast (สีแดงปกติ)
                        Toast.fire({
                            icon: 'error',
                            title: response.message
                        });
                    }
                },
                error: function(){
                    btn.prop('disabled', false).html(originalText);
                    Toast.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ'
                    });
                }
            });
        });
    });
    </script>

</body>
</html>