<?php
session_start();
require_once 'db_connect.php';

// --- ส่วนการคำนวณแบ่งหน้า (Pagination Logic) ---
$limit = 6; // จำนวนรถที่จะแสดงต่อ 1 หน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// 1. หาจำนวนรถทั้งหมดก่อน
$sql_count = "SELECT COUNT(*) as total FROM vehicles";
$result_count = $conn->query($sql_count);
$row_count = $result_count->fetch_assoc();
$total_cars = $row_count['total'];
$total_pages = ceil($total_cars / $limit);

// 2. ดึงข้อมูลรถเฉพาะหน้านั้นๆ (LIMIT)
$sql = "SELECT * FROM vehicles ORDER BY status ASC, brand ASC LIMIT $start, $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยานพาหนะทั้งหมด - OVAS</title>
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

        /* Car Card Style */
        .car-card {
            background: #fff; border-radius: 20px; border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: all 0.3s ease;
            overflow: hidden; height: 100%; display: flex; flex-direction: column;
        }
        .car-card:hover { transform: translateY(-5px); box-shadow: 0 15px 40px rgba(30, 60, 114, 0.15); }

        .card-img-top-wrapper {
            height: 200px; overflow: hidden; position: relative; background-color: #f0f2f5;
        }
        .card-img-top-wrapper img {
            width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;
        }
        .car-card:hover .card-img-top-wrapper img { transform: scale(1.05); }
        
        .placeholder-car {
            display: flex; align-items: center; justify-content: center;
            height: 100%; color: #cbd5e1; font-size: 4rem;
        }

        .card-status-badge {
            position: absolute; top: 15px; right: 15px; padding: 5px 12px;
            border-radius: 30px; font-size: 0.8rem; font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1); backdrop-filter: blur(5px);
        }
        .badge-available { background: rgba(25, 135, 84, 0.9); color: white; }
        .badge-maintenance { background: rgba(220, 53, 69, 0.9); color: white; }

        .card-body { padding: 25px; flex: 1; display: flex; flex-direction: column; }
        .car-title { font-size: 1.25rem; font-weight: 700; color: #1e3c72; margin-bottom: 5px; }
        .car-plate { 
            background: #f8f9fa; border: 1px solid #dee2e6; padding: 2px 10px;
            border-radius: 6px; font-size: 0.9rem; color: #555; display: inline-block; margin-bottom: 15px;
        }

        .car-specs { display: flex; gap: 15px; margin-bottom: 20px; color: #6c757d; font-size: 0.9rem; }
        .spec-item { display: flex; align-items: center; gap: 6px; }

        .btn-book {
            margin-top: auto; width: 100%; border-radius: 50px; padding: 10px;
            font-weight: 600; background: var(--primary-gradient); border: none; color: white; transition: all 0.2s;
        }
        .btn-book:hover { opacity: 0.95; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(30, 60, 114, 0.3); color: white; }
        .btn-disabled { background: #e9ecef; color: #adb5bd; border: none; cursor: not-allowed; }

        /* CSS สำหรับปุ่มเปลี่ยนหน้า */
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
                    <h3 class="fw-bold text-dark"><i class="fas fa-car-alt me-2 text-primary"></i>ข้อมูลรถทั้งหมด</h3>
                </div>
                <div>
                    <span class="badge bg-white text-muted border px-3 py-1 rounded-pill small">
                        ทั้งหมด <?php echo $total_cars; ?> คัน
                    </span>
                </div>
            </div>

            <div class="row g-4">
                <?php if ($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): 
                        $is_available = ($row['status'] == 'available');
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="car-card">
                            <div class="card-img-top-wrapper">
                                <?php if($is_available): ?>
                                    <span class="card-status-badge badge-available"><i class="fas fa-check-circle me-1"></i> พร้อมใช้งาน</span>
                                <?php else: ?>
                                    <span class="card-status-badge badge-maintenance"><i class="fas fa-tools me-1"></i> ซ่อมบำรุง</span>
                                <?php endif; ?>

                                <?php if(!empty($row['image']) && file_exists('uploads/'.$row['image'])): ?>
                                    <img src="uploads/<?php echo $row['image']; ?>" alt="<?php echo $row['brand']; ?>">
                                <?php else: ?>
                                    <div class="placeholder-car"><i class="fas fa-car"></i></div>
                                <?php endif; ?>
                            </div>

                            <div class="card-body">
                                <h5 class="car-title"><?php echo $row['brand'] . ' ' . $row['model']; ?></h5>
                                <div>
                                    <span class="car-plate"><?php echo $row['license_plate']; ?></span>
                                </div>

                                <div class="car-specs">
                                    <div class="spec-item" title="จำนวนที่นั่ง">
                                        <i class="fas fa-users text-primary"></i> <?php echo $row['seat_capacity']; ?> ที่นั่ง
                                    </div>
                                    <div class="spec-item" title="ประเภทเชื้อเพลิง (ตัวอย่าง)">
                                        <i class="fas fa-gas-pump text-warning"></i> ดีเซล
                                    </div>
                                </div>

                                <?php if($is_available): ?>
                                    <a href="booking.php?vehicle_id=<?php echo $row['id']; ?>" class="btn btn-book">
                                        จองคันนี้
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-book btn-disabled" disabled>
                                        ไม่สามารถจองได้
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5">
                        <h4 class="text-muted">ไม่พบข้อมูลรถ</h4>
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