<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- ส่วนการคำนวณแบ่งหน้า (Pagination Logic) ---
$limit = 4; // จำนวนคนขับที่จะแสดงต่อ 1 หน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// 1. หาจำนวนคนขับทั้งหมดก่อน
$sql_count = "SELECT COUNT(*) as total FROM drivers";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_drivers = $row_count['total'];
$total_pages = ceil($total_drivers / $limit);

// 2. ดึงข้อมูลคนขับเฉพาะหน้านั้นๆ (LIMIT) และคะแนนรีวิว
$sql = "SELECT d.*, v.brand, v.model, v.license_plate,
        (SELECT AVG(rating) FROM bookings WHERE driver_id = d.id AND rating IS NOT NULL) as avg_rating,
        (SELECT COUNT(id) FROM bookings WHERE driver_id = d.id AND rating IS NOT NULL) as total_reviews
        FROM drivers d
        LEFT JOIN vehicles v ON d.vehicle_id = v.id
        ORDER BY d.status ASC, d.name ASC
        LIMIT $start, $limit"; // เพิ่ม LIMIT ตรงนี้
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>พนักงานขับรถ - OVAS</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            --secondary-bg: #f8f9fc; 
            --text-color: #495057; 
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-color);
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 160px;
        }

        .main-content { flex: 1; padding-bottom: 100px; }
        footer { flex-shrink: 0; }

        /* --- Page Header Style --- */
        .page-header {
            background: #fff; padding: 15px 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02); margin-bottom: 25px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .page-header h3 { font-size: 1.2rem; margin: 0; }

        /* Driver Card Styles */
        .driver-card {
            background: #fff; border-radius: 20px; border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: all 0.3s ease;
            text-align: center; overflow: hidden; height: 100%; position: relative; padding-bottom: 20px;
        }
        .driver-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(30, 60, 114, 0.15); }

        .driver-img-wrapper {
            width: 120px; height: 120px; margin: 30px auto 10px;
            border-radius: 50%; overflow: hidden; border: 5px solid #f0f2f5;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .driver-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .driver-placeholder {
            width: 100%; height: 100%; background: #eef2ff; color: #1e3c72;
            display: flex; align-items: center; justify-content: center; font-size: 3rem;
        }

        .status-badge { position: absolute; top: 15px; right: 15px; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .status-active { background: #d1e7dd; color: #0f5132; }
        .status-inactive { background: #f8d7da; color: #842029; }

        .driver-name { font-weight: 700; font-size: 1.2rem; color: #1e3c72; margin-bottom: 2px; }
        .driver-role { font-size: 0.9rem; color: #7f8c8d; margin-bottom: 10px; }
        
        .driver-rating { margin-bottom: 15px; font-size: 0.9rem; color: #ffc107; }
        .rating-number { color: #495057; font-weight: bold; margin-right: 5px; }
        .rating-count { color: #adb5bd; font-size: 0.8rem; margin-left: 5px; }

        .car-assigned {
            background: #f8f9fa; border-radius: 10px; padding: 10px;
            margin: 0 20px 20px; font-size: 0.85rem; border: 1px solid #e9ecef;
        }

        /* ปุ่มโทร */
        .btn-call {
            background: var(--primary-gradient); border: none; color: white; width: 80%;
            border-radius: 50px; padding: 8px 0; margin-bottom: 25px; transition: 0.2s;
            text-decoration: none; display: inline-block; font-weight: 600; 
        }
        .btn-call:hover { opacity: 0.9; color: white; transform: scale(1.05); }

        /* ปุ่มดูรายละเอียด */
        .btn-view {
            background: white; border: 2px solid #1e3c72; color: #1e3c72; width: 80%;
            border-radius: 50px; padding: 6px 0; margin-bottom: 10px; transition: 0.2s;
            text-decoration: none; display: inline-block; font-weight: 600; font-size: 0.9rem;
        }
        .btn-view:hover { background: #1e3c72; color: white; transform: translateY(-2px); }

        /* CSS สำหรับปุ่มเปลี่ยนหน้า (เหมือนหน้าข้อมูลรถ) */
        .pagination .page-link { 
            border: none; color: #6c757d; border-radius: 50%; 
            width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; 
            margin: 0 3px; font-weight: 600; transition: all 0.2s;
        }
        .pagination .page-link:hover { background-color: #e9ecef; color: #1e3c72; }
        .pagination .page-item.active .page-link { 
            background: var(--primary-gradient); color: #fff; 
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); 
        }
        .pagination .page-item.disabled .page-link { background-color: transparent; opacity: 0.5; cursor: default; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="page-header">
                <div>
                    <h3 class="fw-bold text-dark"><i class="fas fa-id-card me-2 text-primary"></i>พนักงานขับรถ</h3>
                </div>
                <div>
                    <span class="badge bg-white text-muted border px-3 py-1 rounded-pill small">
                        ทั้งหมด <?php echo $total_drivers; ?> ท่าน
                    </span>
                </div>
            </div>

            <div class="row g-4 justify-content-center">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="driver-card">
                            <?php if($row['status'] == 'active'): ?>
                                <span class="status-badge status-active"><i class="fas fa-circle fa-xs me-1"></i> ปฏิบัติงาน</span>
                            <?php else: ?>
                                <span class="status-badge status-inactive"><i class="fas fa-bed me-1"></i> ลา/พัก</span>
                            <?php endif; ?>

                            <div class="driver-img-wrapper">
                                <?php if(!empty($row['image']) && file_exists('uploads/'.$row['image'])): ?>
                                    <img src="uploads/<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                                <?php else: ?>
                                    <div class="driver-placeholder"><i class="fas fa-user-tie"></i></div>
                                <?php endif; ?>
                            </div>

                            <div class="driver-name"><?php echo $row['name']; ?></div>
                            <div class="driver-role">พนักงานขับรถมืออาชีพ</div>

                            <div class="driver-rating">
                                <?php 
                                $avg_rating = $row['avg_rating'];
                                $total_reviews = $row['total_reviews'];
                                
                                if($total_reviews > 0): 
                                    $rating_val = number_format($avg_rating, 1);
                                ?>
                                    <span class="rating-number"><?php echo $rating_val; ?></span>
                                    <?php 
                                    for($i=1; $i<=5; $i++){
                                        if($i <= round($avg_rating)){
                                            echo '<i class="fas fa-star"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-secondary opacity-25"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="rating-count">(<?php echo $total_reviews; ?> รีวิว)</span>
                                <?php else: ?>
                                    <span class="text-muted small"><i class="far fa-star"></i> ยังไม่มีคะแนนรีวิว</span>
                                <?php endif; ?>
                            </div>

                            <div class="car-assigned">
                                <div class="text-muted small mb-1">รถประจำตำแหน่ง</div>
                                <?php if($row['brand']): ?>
                                    <div class="fw-bold text-dark">
                                        <i class="fas fa-car me-1 text-secondary"></i> 
                                        <?php echo $row['brand'] . ' ' . $row['model']; ?>
                                    </div>
                                    <div class="small badge bg-light text-dark border mt-1">
                                        <?php echo $row['license_plate']; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">- ไม่ระบุ -</span>
                                <?php endif; ?>
                            </div>

                            <a href="driver_detail.php?id=<?php echo $row['id']; ?>" class="btn-view shadow-sm">
                                <i class="fas fa-info-circle me-1"></i> ดูข้อมูล & รีวิว
                            </a>

                            <a href="tel:<?php echo $row['phone']; ?>" class="btn-call shadow-sm">
                                <i class="fas fa-phone-alt me-2"></i> <?php echo $row['phone']; ?>
                            </a>

                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <h4 class="text-muted">ไม่พบข้อมูลพนักงานขับรถ</h4>
                    </div>
                <?php endif; ?>
            </div>

            <?php if($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-5">
                <nav>
                    <ul class="pagination">
                        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="<?php if($page > 1) echo "?page=".($page-1); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link" href="<?php if($page < $total_pages) echo "?page=".($page+1); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
    $(document).ready(function() {
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