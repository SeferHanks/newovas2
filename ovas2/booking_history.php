<?php
session_start();
require_once 'db_connect.php';

// ตั้งเวลาให้ตรงกับประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_time = time(); // เวลาปัจจุบัน

// --- ส่วนการคำนวณแบ่งหน้า ---
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

// หาจำนวนรายการทั้งหมด (เฉพาะที่ยังไม่ให้คะแนน)
$sql_count = "SELECT COUNT(*) as total FROM bookings WHERE user_id = ? AND rating IS NULL";
$stmt_count = $conn->prepare($sql_count);
$stmt_count->bind_param("i", $user_id);
$stmt_count->execute();
$res_count = $stmt_count->get_result();
$row_count = $res_count->fetch_assoc();
$total_rows = $row_count['total'];
$total_pages = ceil($total_rows / $limit);

// ดึงข้อมูล (เฉพาะที่ยังไม่ให้คะแนน rating IS NULL)
$sql = "SELECT b.*, v.brand, v.model, v.license_plate, v.image, d.name as driver_name, d.phone as driver_phone
        FROM bookings b
        JOIN vehicles v ON b.vehicle_id = v.id
        LEFT JOIN drivers d ON b.driver_id = d.id
        WHERE b.user_id = ? AND b.rating IS NULL
        ORDER BY b.start_date DESC
        LIMIT ?, ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $start, $limit);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการจอง - OVAS</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --bg-body: #f4f7f6; 
            --primary-color: #1e3c72; 
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
        }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: var(--bg-body); 
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 160px; 
        }

        .main-content { flex: 1; padding-bottom: 80px; }
        footer { flex-shrink: 0; }

        /* Header */
        .page-header {
            background: #fff; padding: 15px 20px; border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02); margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .page-header h3 { font-size: 1.2rem; margin: 0; }

        /* Booking Card */
        .booking-card {
            background: #fff; border-radius: 12px; border: 1px solid #edf2f7;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02); margin-bottom: 15px;
            transition: all 0.2s; overflow: hidden; position: relative;
        }
        .booking-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.06); }
        
        .booking-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; }
        .booking-card.pending::before { background: #ffc107; }
        .booking-card.approved::before { background: #198754; }
        .booking-card.rejected::before { background: #dc3545; }

        .card-content { padding: 15px; }

        .car-img-wrapper {
            width: 110px; height: 80px; border-radius: 8px; overflow: hidden;
            flex-shrink: 0; border: 1px solid #f1f5f9;
        }
        .car-img-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .car-placeholder { width: 100%; height: 100%; background: #f8f9fa; color: #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }

        .dest-title { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 2px; }
        .car-info { font-size: 0.85rem; color: var(--text-muted); }
        .license-badge { background: #edf2f7; color: #4a5568; padding: 1px 6px; border-radius: 4px; font-weight: 600; font-size: 0.75rem; margin-left: 5px; border: 1px solid #e2e8f0; }

        .driver-box, .reject-box {
            border-radius: 8px; padding: 8px 12px; font-size: 0.8rem; margin-top: 8px;
        }
        .driver-box { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .reject-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        
        .badge-status {
            padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 0.75rem;
            display: inline-flex; align-items: center; gap: 4px; margin-bottom: 10px;
        }
        .bg-pending-soft { background: #fffbeb; color: #b45309; }
        .bg-success-soft { background: #dcfce7; color: #15803d; }
        .bg-danger-soft { background: #fee2e2; color: #b91c1c; }

        .btn-action { font-size: 0.8rem; padding: 5px 15px; }

        @media (max-width: 991px) {
            .card-body-flex { flex-direction: column; }
            .car-img-wrapper { width: 100%; height: 150px; margin-bottom: 10px; }
            .col-actions { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #eee; display: flex; justify-content: space-between; align-items: center; }
        }

        .pagination .page-link { border: none; color: #7f8c8d; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; margin: 0 2px; }
        .pagination .page-item.active .page-link { background: var(--primary-color); color: #fff; }

        /* --- CSS สำหรับดาวให้คะแนน --- */
        .star-rating { direction: rtl; display: inline-flex; font-size: 2rem; justify-content: center; }
        .star-rating input { display: none; }
        .star-rating label { color: #ddd; cursor: pointer; padding: 0 5px; transition: 0.2s; }
        .star-rating input:checked ~ label,
        .star-rating label:hover,
        .star-rating label:hover ~ label { color: #ffc107; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="page-header">
                <div>
                    <h3 class="fw-bold text-dark"><i class="fas fa-history me-2 text-primary"></i>ประวัติการจอง</h3>
                </div>
                <div>
                    <span class="badge bg-white text-muted border px-3 py-1 rounded-pill small">
                        รอให้คะแนน/ดำเนินการ <?php echo $total_rows; ?> รายการ
                    </span>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status_class = $row['status'];
                            $img_show = (!empty($row['image']) && file_exists('uploads/'.$row['image'])) 
                                ? '<img src="uploads/'.$row['image'].'">' 
                                : '<div class="car-placeholder"><i class="fas fa-car-side"></i></div>';
                            
                            // คำนวณเวลาจบงาน
                            $end_time = strtotime($row['end_date']);
                            $is_finished = ($current_time > $end_time); 
                        ?>
                        
                        <div class="booking-card <?php echo $status_class; ?>" id="card-<?php echo $row['id']; ?>">
                            <div class="card-content">
                                <div class="d-flex card-body-flex gap-3">
                                    
                                    <div class="car-img-wrapper">
                                        <?php echo $img_show; ?>
                                    </div>

                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="dest-title"><i class="fas fa-map-marker-alt me-2 text-danger"></i><?php echo $row['destination']; ?></div>
                                                <div class="car-info">
                                                    <?php echo $row['brand'].' '.$row['model']; ?> 
                                                    <span class="license-badge"><?php echo $row['license_plate']; ?></span>
                                                </div>
                                            </div>
                                            
                                            <div class="d-none d-lg-block text-end">
                                                <?php if($row['status']=='pending'): ?><span class="badge-status bg-pending-soft">รออนุมัติ</span>
                                                <?php elseif($row['status']=='approved'): ?>
                                                    <?php if($is_finished): ?>
                                                        <span class="badge-status bg-success-soft">จบงานแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge-status bg-success-soft">อนุมัติแล้ว</span>
                                                    <?php endif; ?>
                                                <?php else: ?><span class="badge-status bg-danger-soft">ไม่อนุมัติ</span><?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-2 gx-3 gy-1" style="font-size: 0.85rem;">
                                            <div class="col-md-auto text-success">
                                                <i class="fas fa-play-circle me-1"></i> <span class="fw-bold">ไป:</span> <?php echo date('d/m/y H:i', strtotime($row['start_date'])); ?>
                                            </div>
                                            <div class="col-md-auto text-danger">
                                                <i class="fas fa-stop-circle me-1"></i> <span class="fw-bold">กลับ:</span> <?php echo date('d/m/y H:i', strtotime($row['end_date'])); ?>
                                            </div>
                                            <div class="col-md-auto text-muted ms-md-auto">
                                                <i class="fas fa-users me-1"></i> <?php echo $row['passengers']; ?> ท่าน | <i class="far fa-comment-dots me-1"></i> <?php echo $row['purpose']; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-actions text-end" style="min-width: 180px;">
                                        <div class="d-lg-none mb-2 text-start">
                                            <?php if($row['status']=='pending'): ?><span class="badge-status bg-pending-soft">รออนุมัติ</span>
                                            <?php elseif($row['status']=='approved'): ?><span class="badge-status bg-success-soft">อนุมัติแล้ว</span>
                                            <?php else: ?><span class="badge-status bg-danger-soft">ไม่อนุมัติ</span><?php endif; ?>
                                        </div>

                                        <?php if($row['status'] == 'pending'): ?>
                                            <button class="btn btn-outline-danger rounded-pill w-100 btn-cancel btn-action" data-id="<?php echo $row['id']; ?>">
                                                <i class="fas fa-times me-1"></i> ยกเลิก
                                            </button>
                                        
                                        <?php elseif($row['status'] == 'approved'): ?>
                                            
                                            <a href="print_booking.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-primary rounded-pill w-100 mb-2 shadow-sm btn-action">
                                                <i class="fas fa-print me-1"></i> พิมพ์ใบยืนยัน
                                            </a>

                                            <?php if($is_finished): ?>
                                                <button class="btn btn-warning rounded-pill w-100 mb-2 shadow-sm btn-action fw-bold btn-rate-modal" 
                                                        data-id="<?php echo $row['id']; ?>"
                                                        data-driver="<?php echo $row['driver_name']; ?>">
                                                    <i class="fas fa-star me-1"></i> จบงาน & ให้คะแนน
                                                </button>
                                            <?php endif; ?>

                                            <div class="driver-box text-start">
                                                <div class="fw-bold text-truncate"><i class="fas fa-user-tie me-1"></i> <?php echo $row['driver_name'] ? $row['driver_name'] : '-'; ?></div>
                                                <div class="text-truncate"><i class="fas fa-phone me-1"></i> <?php echo $row['driver_phone'] ? $row['driver_phone'] : '-'; ?></div>
                                            </div>
                                        
                                        <?php else: ?>
                                            <?php if(!empty($row['reject_reason'])): ?>
                                                <div class="reject-box text-start">
                                                    <strong>เหตุผล:</strong> <?php echo $row['reject_reason']; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>

                        <?php if($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-4">
                            <nav>
                                <ul class="pagination pagination-sm">
                                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($page > 1) echo "?page=".($page-1); ?>"><i class="fas fa-chevron-left"></i></a>
                                    </li>
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                                        <a class="page-link" href="<?php if($page < $total_pages) echo "?page=".($page+1); ?>"><i class="fas fa-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="text-center py-5 bg-white rounded-3 shadow-sm mt-3">
                            <i class="fas fa-clipboard-list fa-3x text-secondary opacity-25 mb-3"></i>
                            <h5 class="text-secondary fw-bold">ไม่มีรายการที่ต้องดำเนินการ</h5>
                            <a href="index.php" class="btn btn-primary btn-sm rounded-pill px-4 mt-2">จองรถใหม่</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <div class="modal fade" id="ratingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold">ให้คะแนนคนขับ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center pt-0">
                    <p class="text-muted">คนขับ: <strong id="modal_driver_name" class="text-dark"></strong></p>
                    
                    <form id="ratingForm">
                        <input type="hidden" name="booking_id" id="modal_booking_id">
                        
                        <div class="star-rating mb-3">
                            <input type="radio" id="star5" name="rating" value="5" required><label for="star5" title="5 ดาว">★</label>
                            <input type="radio" id="star4" name="rating" value="4"><label for="star4" title="4 ดาว">★</label>
                            <input type="radio" id="star3" name="rating" value="3"><label for="star3" title="3 ดาว">★</label>
                            <input type="radio" id="star2" name="rating" value="2"><label for="star2" title="2 ดาว">★</label>
                            <input type="radio" id="star1" name="rating" value="1"><label for="star1" title="1 ดาว">★</label>
                        </div>

                        <textarea name="comment" class="form-control bg-light mb-3" placeholder="ข้อเสนอแนะเพิ่มเติม..." rows="3"></textarea>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill btn-lg">บันทึกคะแนน</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function(){
        
        // ฟังก์ชันยกเลิกจอง
        $('.btn-cancel').click(function(){
            var id = $(this).data('id');
            Swal.fire({
                title: 'ยืนยันยกเลิก?', text: "ต้องการยกเลิกรายการนี้ใช่หรือไม่", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
                confirmButtonText: 'ยกเลิกจอง', cancelButtonText: 'ปิด'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'cancel_booking.php', type: 'POST', data: {id: id}, dataType: 'json',
                        success: function(res){
                            if(res.status == 'success'){ 
                                Swal.fire({icon:'success', title:'ยกเลิกสำเร็จ', showConfirmButton:false, timer:1200}).then(()=> location.reload()); 
                            } else { Swal.fire('Error', res.message, 'error'); }
                        }
                    });
                }
            });
        });

        // ฟังก์ชันเปิด Modal ให้คะแนน
        $('.btn-rate-modal').click(function() {
            var id = $(this).data('id');
            var driver = $(this).data('driver');
            
            $('#modal_booking_id').val(id);
            $('#modal_driver_name').text(driver);
            
            // รีเซ็ตค่าเก่า
            $('input[name="rating"]').prop('checked', false);
            $('textarea[name="comment"]').val('');
            
            new bootstrap.Modal(document.getElementById('ratingModal')).show();
        });

        // ฟังก์ชันบันทึกคะแนนและลบการ์ด
        $('#ratingForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                type: 'POST',
                url: 'save_rating.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        $('#ratingModal').modal('hide');
                        
                        Swal.fire({
                            icon: 'success', title: 'ขอบคุณครับ', text: 'บันทึกคะแนนเรียบร้อยแล้ว',
                            timer: 1500, showConfirmButton: false
                        });

                        var bid = $('#modal_booking_id').val();
                        $('#card-' + bid).fadeOut(500, function() {
                            $(this).remove();
                            if($('.booking-card').length == 0) location.reload();
                        });

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                }
            });
        });

    });
    </script>
</body>
</html>