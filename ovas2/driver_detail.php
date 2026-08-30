<?php
session_start();
require_once 'db_connect.php';

// รับค่า ID ของคนขับที่ส่งมา
if (!isset($_GET['id'])) {
    header("Location: drivers.php");
    exit();
}

$driver_id = $_GET['id'];

// 1. ดึงข้อมูลคนขับ + ข้อมูลรถ
$sql_driver = "SELECT d.*, v.brand, v.model, v.license_plate, v.image as car_image
               FROM drivers d
               LEFT JOIN vehicles v ON d.vehicle_id = v.id
               WHERE d.id = ?";
$stmt = $conn->prepare($sql_driver);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$res_driver = $stmt->get_result();
$driver = $res_driver->fetch_assoc();

if (!$driver) {
    echo "<div class='alert alert-danger text-center mt-5'>ไม่พบข้อมูลคนขับ</div>";
    exit();
}

// 2. ดึงประวัติการรีวิว
$sql_reviews = "SELECT b.rating, b.rating_comment, b.end_date, u.fullname
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.driver_id = ? 
                AND b.rating IS NOT NULL 
                ORDER BY b.end_date DESC";
$stmt_r = $conn->prepare($sql_reviews);
$stmt_r->bind_param("i", $driver_id);
$stmt_r->execute();
$res_reviews = $stmt_r->get_result();

// 3. คำนวณคะแนนเฉลี่ย
$sql_avg = "SELECT AVG(rating) as avg_score, COUNT(id) as total_review 
            FROM bookings 
            WHERE driver_id = ? AND rating IS NOT NULL";
$stmt_avg = $conn->prepare($sql_avg);
$stmt_avg->bind_param("i", $driver_id);
$stmt_avg->execute();
$stat = $stmt_avg->get_result()->fetch_assoc();

$avg_score = number_format($stat['avg_score'], 1);
$total_review = $stat['total_review'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ข้อมูลพนักงานขับรถ - <?php echo $driver['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* ปรับระยะห่าง Header และพื้นหลัง */
        body { 
            font-family: 'Sarabun', sans-serif; 
            background: #f8f9fa; 
            padding-top: 160px; /* เพิ่มระยะห่างจาก Header */
            display: flex; 
            flex-direction: column; 
            min-height: 100vh;
        }

        /* ดัน Footer ลงไปล่างสุด และเพิ่มระยะห่าง */
        .main-container {
            flex: 1;
            padding-bottom: 80px; /* เพิ่มระยะห่างจาก Footer */
        }

        .profile-header { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .driver-img-large { width: 150px; height: 150px; object-fit: cover; border-radius: 50%; border: 5px solid #f8f9fa; }
        .rating-box { background: #fff8e1; border: 1px solid #ffecb3; border-radius: 10px; padding: 15px; text-align: center; }
        .review-card { border: none; border-bottom: 1px solid #eee; padding: 15px 0; }
        .review-card:last-child { border-bottom: none; }
        .star-active { color: #ffc107; }
        .star-inactive { color: #e0e0e0; }
        .badge-status { font-size: 0.9rem; padding: 5px 12px; border-radius: 20px; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-container">
        <div class="container">
            
            <div class="mb-3">
                <a href="drivers.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                    <i class="fas fa-arrow-left me-1"></i> ย้อนกลับ
                </a>
            </div>

            <div class="profile-header p-4 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center">
                        <?php if(!empty($driver['image'])): ?>
                            <img src="uploads/<?php echo $driver['image']; ?>" class="driver-img-large shadow-sm">
                        <?php else: ?>
                            <div class="driver-img-large d-flex align-items-center justify-content-center bg-light mx-auto">
                                <i class="fas fa-user fa-4x text-secondary"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-center text-md-start mt-3 mt-md-0">
                        <h3 class="fw-bold text-dark mb-1"><?php echo $driver['name']; ?></h3>
                        <p class="text-muted mb-2"><i class="fas fa-id-card me-2"></i>พนักงานขับรถมืออาชีพ</p>
                        
                        <div class="mb-3">
                            <?php if($driver['status'] == 'active'): ?>
                                <span class="badge bg-success badge-status"><i class="fas fa-check-circle me-1"></i> พร้อมให้บริการ</span>
                            <?php else: ?>
                                <span class="badge bg-danger badge-status"><i class="fas fa-times-circle me-1"></i> ไม่ว่าง / พักงาน</span>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-3 justify-content-center justify-content-md-start">
                            <a href="tel:<?php echo $driver['phone']; ?>" class="btn btn-outline-primary rounded-pill">
                                <i class="fas fa-phone me-2"></i> <?php echo $driver['phone']; ?>
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mt-3 mt-md-0">
                        <div class="rating-box">
                            <h1 class="fw-bold text-warning mb-0"><?php echo $avg_score; ?></h1>
                            <div class="small text-warning mb-1">
                                <?php 
                                for($i=1; $i<=5; $i++) {
                                    echo ($i <= round($stat['avg_score'])) ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                            <div class="text-muted small">จาก <?php echo $total_review; ?> รีวิว</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-header bg-white border-0 fw-bold py-3">
                            <i class="fas fa-car me-2 text-primary"></i> รถประจำตำแหน่ง
                        </div>
                        <div class="card-body text-center">
                            <?php if($driver['brand']): ?>
                                <h5 class="fw-bold"><?php echo $driver['brand'].' '.$driver['model']; ?></h5>
                                <div class="badge bg-light text-dark border mb-3"><?php echo $driver['license_plate']; ?></div>
                                <?php if(!empty($driver['car_image'])): ?>
                                    <img src="uploads/<?php echo $driver['car_image']; ?>" class="img-fluid rounded-3 mb-2">
                                <?php else: ?>
                                    <div class="py-4 bg-light rounded-3 text-muted"><i class="fas fa-car fa-2x"></i><br>ไม่มีรูปรถ</div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted py-3">ยังไม่ได้ระบุรถประจำตำแหน่ง</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-header bg-white border-0 fw-bold py-3 d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-star me-2 text-warning"></i> ความคิดเห็นจากผู้ใช้</span>
                            <span class="badge bg-light text-dark"><?php echo $total_review; ?> ความเห็น</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if($res_reviews->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while($review = $res_reviews->fetch_assoc()): ?>
                                    <div class="list-group-item p-4 review-card">
                                        <div class="d-flex justify-content-between mb-2">
                                            <div class="fw-bold text-dark">
                                                <i class="fas fa-user-circle me-2 text-secondary"></i>
                                                <?php echo $review['fullname']; ?>
                                            </div>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($review['end_date'])); ?></small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <?php 
                                            // แสดงดาวของแต่ละรีวิว
                                            for($k=1; $k<=5; $k++) {
                                                if($k <= $review['rating']){
                                                    echo '<i class="fas fa-star star-active"></i>';
                                                } else {
                                                    echo '<i class="fas fa-star star-inactive"></i>';
                                                }
                                            }
                                            ?>
                                            <span class="ms-2 fw-bold text-dark"><?php echo $review['rating']; ?>.0</span>
                                        </div>
                                        
                                        <?php if(!empty($review['rating_comment'])): ?>
                                            <p class="text-muted mb-0 bg-light p-3 rounded-3 fst-italic">
                                                "<?php echo $review['rating_comment']; ?>"
                                            </p>
                                        <?php else: ?>
                                            <p class="text-muted mb-0 small"><i>(ไม่ได้ระบุความคิดเห็น)</i></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="far fa-comment-dots fa-3x mb-3 opacity-50"></i>
                                    <p>ยังไม่มีรีวิวสำหรับคนขับท่านนี้</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
        // สคริปต์ออกจากระบบ
        $('body').on('click', '.js-logout, .btn-logout', function(e) {
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
                        didOpen: () => { Swal.showLoading(); },
                        willClose: () => { window.location.href = 'logout.php'; }
                    });
                }
            });
        });
    });
    </script>
</body>
</html>