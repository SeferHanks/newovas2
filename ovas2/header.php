<?php
// เช็คว่า Session เริ่มทำงานหรือยัง
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// [เพิ่ม] เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล เพื่อดึงรูปภาพล่าสุด
require_once 'db_connect.php';

$profile_img = ''; // ตัวแปรสำหรับเก็บชื่อไฟล์รูป

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    // ดึงข้อมูลรูปภาพล่าสุดจาก Database (เพื่อให้เป็นปัจจุบันเสมอ)
    $stmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $profile_img = $row['profile_image'];
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(90deg, #1e3c72 0%, #2a5298 100%); box-shadow: 0 4px 6px rgba(0,0,0,0.1); padding: 12px 0;">
    <div class="container">
        
        <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php" style="color: white !important;">
            <img src="uploads/logo.png" alt="Logo" style="height: 45px; width: auto;" class="me-2">
            OVAS System
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            
            <ul class="navbar-nav me-auto align-items-center">
                <li class="nav-item">
                    <a class="nav-link text-white" href="index.php">
                        <i class="fas fa-calendar-alt me-1"></i> ปฏิทินการใช้รถ
                    </a>
                </li>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="booking.php">
                            <i class="fas fa-plus-circle me-1"></i> จองรถ
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white" href="booking_history.php">
                            <i class="fas fa-history me-1"></i> ประวัติการจอง
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white" href="cars.php">
                            <i class="fas fa-car me-1"></i> รถ
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link text-white" href="drivers.php">
                            <i class="fas fa-id-card me-1"></i> คนขับรถ
                        </a>
                    </li>
                <?php endif; ?>

                <?php if(isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-warning fw-bold" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-shield me-1"></i> ผู้ดูแลระบบ
                        </a>
                        <ul class="dropdown-menu border-0 shadow-lg mt-2" style="border-radius: 12px;">
                            <li><a class="dropdown-item py-2" href="admin_approve.php"><i class="fas fa-tasks me-2 text-primary"></i> อนุมัติคำขอ</a></li>
                            <li><a class="dropdown-item py-2" href="manage_cars.php"><i class="fas fa-car me-2 text-primary"></i> จัดการข้อมูลรถ</a></li>
                            <li><a class="dropdown-item py-2" href="manage_drivers.php"><i class="fas fa-id-card me-2 text-primary"></i> จัดการคนขับรถ</a></li>
                            <li><a class="dropdown-item py-2" href="dashboard.php"><i class="fas fa-chart-pie me-2 text-primary"></i> Dashboard สถิติ</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-center">
                <?php if(isset($_SESSION['user_id'])): ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="text-end me-2 d-none d-lg-block">
                                <small class="opacity-75" style="font-size: 0.75rem;">ยินดีต้อนรับ,</small><br>
                                <span class="fw-bold"><?php echo $_SESSION['fullname']; ?></span>
                            </div>
                            
                            <div class="bg-light text-primary rounded-circle d-flex align-items-center justify-content-center overflow-hidden shadow-sm" style="width: 40px; height: 40px; border: 2px solid rgba(255,255,255,0.3);">
                                <?php if (!empty($profile_img) && file_exists('uploads/' . $profile_img)): ?>
                                    <img src="uploads/<?php echo $profile_img; ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>

                        </a>
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2" style="border-radius: 12px;">
                            <li>
                                <a class="dropdown-item py-2" href="profile.php">
                                    <i class="fas fa-user-cog me-2 text-primary"></i> ข้อมูลส่วนตัว
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger js-logout btn-logout" href="#">
                                    <i class="fas fa-sign-out-alt me-2"></i> ออกจากระบบ
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item me-2">
                        <a href="register.php" class="btn btn-outline-light rounded-pill px-3 btn-sm">
                            <i class="fas fa-user-plus me-1"></i> สมัครสมาชิก
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="login.php" class="btn btn-light text-primary rounded-pill px-3 fw-bold shadow-sm btn-sm">
                            <i class="fas fa-sign-in-alt me-1"></i> เข้าสู่ระบบ
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

        </div>
    </div>
</nav>

<style>
    .navbar-brand { font-size: 1.35rem; letter-spacing: 0.5px; }
    .nav-link { font-size: 0.95rem; margin-right: 10px; transition: all 0.2s; opacity: 0.9; }
    .nav-link:hover { opacity: 1; transform: translateY(-1px); }
    .dropdown-item:hover { background-color: #f8f9fa; color: #1e3c72; }
    
    .dropdown-menu { margin-top: 10px; overflow: hidden; border: none; }
    .dropdown-item { font-size: 0.9rem; transition: 0.2s; padding: 10px 20px; }
    .dropdown-item i { width: 20px; text-align: center; }
</style>

<script>
$(document).ready(function(){
    // ใช้ on click ที่ body เพื่อรองรับการโหลด content ใหม่ด้วย Ajax (ถ้ามี)
    $('body').on('click', '.js-logout, .btn-logout', function(e){
        e.preventDefault(); 

        Swal.fire({
            title: 'ออกจากระบบ?',
            text: "คุณต้องการลงชื่อออกใช่หรือไม่",
            icon: 'question',
            showCancelButton: true,
            
            confirmButtonText: 'ใช่, ออกจากระบบ',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true, 
            
            buttonsStyling: false,
            customClass: {
                popup: 'rounded-4 shadow-sm',
                confirmButton: 'btn btn-danger px-4 py-2 rounded-3 ms-2',
                cancelButton: 'btn btn-secondary px-4 py-2 rounded-3'
            }
            
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'กำลังออกจากระบบ...',
                    html: 'ขอบคุณที่ใช้บริการ',
                    timer: 1000, 
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    willClose: () => {
                        window.location.href = 'logout.php';
                    }
                });
            }
        });
    });
});
</script>