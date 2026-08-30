<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ข้อมูลส่วนตัว - OVAS</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-body: #f8f9fc; --primary-color: #1e3c72; }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 160px;
        }

        .main-content { flex: 1; padding-bottom: 100px; }
        footer { flex-shrink: 0; }

        .profile-card {
            background: #fff; border-radius: 20px; border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); overflow: hidden;
        }
        
        .profile-header-bg {
            height: 150px;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }

        .profile-avatar-container {
            margin-top: -75px; text-align: center;
            position: relative; display: inline-block;
        }

        .profile-avatar {
            width: 150px; height: 150px;
            background-color: #fff;
            border-radius: 50%;
            padding: 5px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 4rem; color: #1e3c72;
            overflow: hidden; position: relative;
        }

        .profile-avatar img {
            width: 100%; height: 100%; object-fit: cover; border-radius: 50%;
        }

        /* ปุ่มกล้องถ่ายรูป */
        .avatar-edit-btn {
            position: absolute; bottom: 5px; right: 5px;
            background: #ffc107; color: #333;
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; border: 3px solid #fff;
            transition: all 0.2s; box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        .avatar-edit-btn:hover { transform: scale(1.1); background: #e0a800; }

        .form-control {
            border-radius: 10px; padding: 12px 15px; border: 1px solid #dee2e6;
            background-color: #fcfcfc;
        }
        .form-control:focus {
            border-color: #1e3c72; box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.1);
            background-color: #fff;
        }
        .form-label { font-weight: 500; color: #555; font-size: 0.9rem; }
        
        .role-badge {
            background-color: #eef2ff; color: #1e3c72; 
            padding: 5px 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 600;
            border: 1px solid #dce4ff; margin-top: 10px; display: inline-block;
        }

        /* --- แก้ไข: เปลี่ยนเฉพาะสีตัวหนังสือเป็นสีน้ำเงิน --- */
        div:where(.swal2-container).swal2-top-end > .swal2-popup {
            border-left: 5px solid #1e3c72 !important; /* ขอบซ้ายยังคงธีมไว้เพื่อให้ดูสวย */
        }
        .swal2-popup.swal2-toast .swal2-title {
            color: #1e3c72 !important; /* ตัวหนังสือสีน้ำเงิน (ตามที่ขอ) */
            font-weight: 600;
        }
        .swal2-popup.swal2-toast {
            border-radius: 12px !important;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    
                    <div class="d-flex align-items-center mb-4">
                        <a href="index.php" class="btn btn-light rounded-circle shadow-sm me-3" style="width: 40px; height: 40px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-arrow-left text-primary"></i>
                        </a>
                        <h3 class="fw-bold text-dark mb-0">ข้อมูลส่วนตัว</h3>
                    </div>

                    <div class="profile-card">
                        <div class="profile-header-bg"></div>
                        
                        <div class="card-body p-4 p-md-5 pt-0">
                            
                            <form id="profileForm" enctype="multipart/form-data">
                                
                                <div class="text-center mb-4">
                                    <div class="profile-avatar-container">
                                        <div class="profile-avatar" id="avatarPreviewArea">
                                            <?php if (!empty($user['profile_image']) && file_exists('uploads/' . $user['profile_image'])): ?>
                                                <img src="uploads/<?php echo $user['profile_image']; ?>" alt="Profile">
                                            <?php else: ?>
                                                <i class="fas fa-user-circle"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <label for="profileUpload" class="avatar-edit-btn" title="เปลี่ยนรูปโปรไฟล์">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                        <input type="file" id="profileUpload" name="profile_image" class="d-none" accept="image/*" onchange="uploadProfilePicture(this)">
                                    </div>

                                    <div class="mt-3">
                                        <h4 class="fw-bold text-dark mb-0"><?php echo $user['fullname']; ?></h4>
                                        <span class="role-badge"><i class="fas fa-shield-alt me-1"></i> <?php echo ucfirst($user['role']); ?></span>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="fas fa-info-circle me-2"></i>ข้อมูลทั่วไป</h6>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อ-นามสกุล</label>
                                        <input type="text" name="fullname" class="form-control" value="<?php echo $user['fullname']; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                                        <input type="text" name="username" class="form-control" value="<?php echo $user['username']; ?>" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทรศัพท์</label>
                                        <input type="text" name="phone" class="form-control" value="<?php echo isset($user['phone']) ? $user['phone'] : ''; ?>" placeholder="ยังไม่มีข้อมูล">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">อีเมล</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <h6 class="text-primary fw-bold mb-3 border-bottom pb-2"><i class="fas fa-lock me-2"></i>เปลี่ยนรหัสผ่าน <small class="text-muted fw-normal ms-2">(เว้นว่างไว้ถ้าไม่ต้องการเปลี่ยน)</small></h6>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">รหัสผ่านใหม่</label>
                                        <input type="password" name="new_password" class="form-control" placeholder="ตั้งรหัสผ่านใหม่">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                        <input type="password" name="confirm_password" class="form-control" placeholder="พิมพ์อีกครั้ง">
                                    </div>

                                    <div class="col-12 mt-4 text-center">
                                        <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); border:none;">
                                            <i class="fas fa-save me-2"></i> บันทึกการเปลี่ยนแปลง
                                        </button>
                                    </div>
                                </div>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    const BlueToast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true,
        // เอา iconColor ออก เพื่อให้ใช้สี Default (เขียว/แดง/ฟ้า)
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    function uploadProfilePicture(input) {
        if (input.files && input.files[0]) {
            
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#avatarPreviewArea').html('<img src="' + e.target.result + '" alt="Preview">');
            }
            reader.readAsDataURL(input.files[0]);

            var formData = new FormData();
            formData.append('profile_image', input.files[0]);

            BlueToast.fire({ icon: 'info', title: 'กำลังอัปโหลดรูป...' });

            $.ajax({
                url: 'upload_avatar_process.php',
                type: 'POST',
                data: formData,
                contentType: false, 
                processData: false,
                dataType: 'json',
                success: function(res){
                    if(res.status == 'success'){
                        // ไอคอนจะเป็นสีเขียว (Default ของ success) ตัวหนังสือเป็นสีน้ำเงิน (ตาม CSS)
                        BlueToast.fire({ 
                            icon: 'success', 
                            title: 'เปลี่ยนรูปโปรไฟล์เรียบร้อย'
                        });
                    } else {
                        Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'เชื่อมต่อ Server ไม่ได้', 'error');
                }
            });
        }
    }

    $(document).ready(function(){
        $('#profileForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);

            Swal.fire({
                title: 'บันทึกข้อมูล?',
                text: "ยืนยันการแก้ไขข้อมูลส่วนตัว",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#1e3c72',
                confirmButtonText: 'บันทึก',
                cancelButtonText: 'ยกเลิก',
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    
                    BlueToast.fire({ icon: 'info', title: 'กำลังบันทึกข้อมูล...' });

                    $.ajax({
                        url: 'update_profile.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        contentType: false,
                        processData: false,
                        success: function(res){
                            if(res.status == 'success'){
                                // ไอคอนสีเขียว ตัวหนังสือสีน้ำเงิน
                                BlueToast.fire({
                                    icon: 'success',
                                    title: 'บันทึกข้อมูลสำเร็จ!'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                            }
                        }
                    });
                }
            });
        });
    });
    </script>

</body>
</html>