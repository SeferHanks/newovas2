<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok'); 

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// --- 0. คำนวณยอดสรุป ---
$count_pending = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetch_row()[0];
$today = date('Y-m-d');
$count_today = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'approved' AND DATE(start_date) = '$today'")->fetch_row()[0];
$count_cars = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'")->fetch_row()[0];

// 1. ดึงรายการ "รออนุมัติ" 
// *** แก้ไขตรงนี้: เปลี่ยน u.image เป็น u.profile_image ***
$sql_pending = "SELECT b.*, u.fullname, u.phone, u.email, u.role, u.profile_image as user_image, v.brand, v.model, v.license_plate 
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN vehicles v ON b.vehicle_id = v.id
                WHERE b.status = 'pending'
                ORDER BY b.created_at ASC";
$result_pending = $conn->query($sql_pending);

// 2. ส่วน Pagination ประวัติ
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$sql_count = "SELECT COUNT(*) as total FROM bookings WHERE status != 'pending'";
$total_rows = $conn->query($sql_count)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// 3. ดึงข้อมูลประวัติ
$sql_history = "SELECT b.*, u.fullname, u.email, u.phone, v.brand, v.model, v.license_plate, 
                d.name as driver_name, d.phone as driver_phone
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN vehicles v ON b.vehicle_id = v.id
                LEFT JOIN drivers d ON b.driver_id = d.id
                WHERE b.status != 'pending'
                ORDER BY b.id DESC LIMIT $start, $limit";
$result_history = $conn->query($sql_history);

// 4. ดึงรายชื่อคนขับ
$drivers = $conn->query("SELECT * FROM drivers WHERE status = 'active'");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ผู้ดูแลระบบ - OVAS</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            --secondary-bg: #f0f2f5; 
            --card-radius: 16px;
        }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: var(--secondary-bg); 
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 100px;
        }

        .main-content { flex: 1; padding-bottom: 100px; }

        /* Stats Card */
        .stat-card {
            background: #fff; border-radius: var(--card-radius); padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03); border: none;
            display: flex; align-items: center; justify-content: space-between;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; color: #fff;
        }

        /* Glass Card (Pending) */
        .glass-card {
            background: #fff; border-radius: var(--card-radius); border: none;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05); position: relative; overflow: hidden;
            margin-bottom: 20px; transition: all 0.3s;
        }
        .glass-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        .glass-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 5px;
            background: #ffc107;
        }

        /* Avatar Box */
        .user-avatar {
            width: 55px; height: 55px; background: #eef2ff; color: #1e3c72;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; font-weight: bold; margin-right: 15px; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden; 
        }
        .user-avatar img {
            width: 100%; height: 100%; object-fit: cover;
        }
        
        .role-badge { font-size: 0.7rem; padding: 3px 8px; border-radius: 20px; background: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }

        /* Actions */
        .btn-action {
            width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: none; transition: 0.2s; box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .btn-approve { background: #198754; color: white; }
        .btn-approve:hover { background: #146c43; transform: scale(1.1); }
        .btn-reject { background: #fff; color: #dc3545; border: 1px solid #dc3545; }
        .btn-reject:hover { background: #dc3545; color: white; transform: scale(1.1); }

        /* History Table */
        .history-table th { background: #f8f9fa; color: #6c757d; font-weight: 600; border: none; vertical-align: middle; }
        .history-table td { vertical-align: top; border-bottom: 1px solid #f0f0f0; padding: 20px 15px; font-size: 0.95rem; }
        .history-table tr:hover { background-color: #fcfcfc; }
        .badge-soft-success { background-color: #d1e7dd; color: #0f5132; padding: 6px 12px; border-radius: 30px; font-weight: 500; }
        .badge-soft-danger { background-color: #f8d7da; color: #842029; padding: 6px 12px; border-radius: 30px; font-weight: 500; }

        .nav-pills .nav-link { border-radius: 50px; color: #6c757d; font-weight: 500; padding: 10px 25px; }
        .nav-pills .nav-link.active { background: var(--primary-gradient); color: white; box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); }

        @media (max-width: 992px) {
            .glass-card .d-flex-desktop { flex-direction: column; align-items: flex-start !important; }
            .glass-card .action-group { width: 100%; flex-direction: row !important; justify-content: flex-end; margin-top: 15px; }
            .glass-card .border-start { border-left: none !important; border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px; margin-left: 0 !important; padding-left: 0 !important; }
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div>
                            <div class="text-muted small">รออนุมัติ</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($count_pending); ?></h3>
                        </div>
                        <div class="stat-icon bg-warning"><i class="fas fa-clock"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div>
                            <div class="text-muted small">อนุมัติวันนี้</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($count_today); ?></h3>
                        </div>
                        <div class="stat-icon bg-success"><i class="fas fa-check-circle"></i></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <div>
                            <div class="text-muted small">รถพร้อมใช้</div>
                            <h3 class="fw-bold text-dark mb-0"><?php echo number_format($count_cars); ?></h3>
                        </div>
                        <div class="stat-icon bg-primary"><i class="fas fa-car"></i></div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item">
                    <button class="nav-link <?php echo (!isset($_GET['page'])) ? 'active' : ''; ?>" id="tab-pending" data-bs-toggle="pill" data-bs-target="#pills-pending">
                        <i class="fas fa-inbox me-2"></i>รอตรวจสอบ
                        <?php if($count_pending > 0) echo '<span class="badge bg-danger ms-1 rounded-pill">'.$count_pending.'</span>'; ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link <?php echo (isset($_GET['page'])) ? 'active' : ''; ?>" id="tab-history" data-bs-toggle="pill" data-bs-target="#pills-history">
                        <i class="fas fa-history me-2"></i>ประวัติย้อนหลัง
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                
                <div class="tab-pane fade <?php echo (!isset($_GET['page'])) ? 'show active' : ''; ?>" id="pills-pending">
                    <?php if ($result_pending->num_rows > 0): ?>
                        <?php while($row = $result_pending->fetch_assoc()): ?>
                        <div class="glass-card p-4" id="card-<?php echo $row['id']; ?>">
                            <div class="d-flex d-flex-desktop justify-content-between align-items-center w-100">
                                
                                <div class="d-flex align-items-start mb-3 mb-lg-0" style="min-width: 280px;">
                                    <div class="user-avatar mt-1 flex-shrink-0">
                                        <?php if(!empty($row['user_image']) && file_exists('uploads/'.$row['user_image'])): ?>
                                            <img src="uploads/<?php echo $row['user_image']; ?>" alt="User Image">
                                        <?php else: ?>
                                            <?php echo mb_substr($row['fullname'], 0, 1); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-5 lh-1 mb-1"><?php echo $row['fullname']; ?></div>
                                        <div class="mb-2"><span class="role-badge"><?php echo ucfirst($row['role']); ?></span></div>
                                        <div class="small text-muted"><i class="fas fa-phone-alt me-2 text-secondary" style="width:15px;"></i> <?php echo $row['phone']; ?></div>
                                        <div class="small text-muted"><i class="far fa-envelope me-2 text-secondary" style="width:15px;"></i> <?php echo $row['email']; ?></div>
                                    </div>
                                </div>

                                <div class="flex-grow-1 px-lg-4 border-start border-end mx-lg-3 w-100">
                                    <div class="row g-2">
                                        <div class="col-12 mb-1">
                                            <div class="fw-bold text-primary fs-5"><i class="fas fa-map-marker-alt me-2"></i><?php echo $row['destination']; ?></div>
                                            <div class="small text-muted mt-1 text-truncate"><i class="fas fa-comment-dots me-1"></i> <?php echo $row['purpose']; ?></div>
                                        </div>
                                        
                                        <div class="col-md-5">
                                            <div class="bg-light p-2 rounded border h-100">
                                                <div class="small text-success fw-bold"><i class="fas fa-play me-1"></i> <?php echo date('d/m H:i', strtotime($row['start_date'])); ?></div>
                                                <div class="small text-danger fw-bold"><i class="fas fa-stop me-1"></i> <?php echo date('d/m H:i', strtotime($row['end_date'])); ?></div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-7">
                                            <div class="d-flex flex-column h-100 justify-content-center ps-lg-2">
                                                <div class="fw-bold text-dark"><i class="fas fa-car me-1"></i> <?php echo $row['brand'].' '.$row['model']; ?></div>
                                                <div class="small text-muted">ทะเบียน: <span class="badge bg-secondary"><?php echo $row['license_plate']; ?></span></div>
                                                <div class="small text-muted mt-1"><i class="fas fa-users me-1"></i> <?php echo $row['passengers']; ?> ท่าน</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-group d-flex flex-column gap-2" style="min-width: 50px;">
                                    <button class="btn-action btn-approve btn-approve-modal" 
                                            data-id="<?php echo $row['id']; ?>" 
                                            data-car="<?php echo $row['brand'].' '.$row['model']; ?>" 
                                            data-dest="<?php echo $row['destination']; ?>"
                                            title="อนุมัติ">
                                        <i class="fas fa-check fa-lg"></i>
                                    </button>
                                    <button class="btn-action btn-reject btn-reject-action" data-id="<?php echo $row['id']; ?>" title="ปฏิเสธ">
                                        <i class="fas fa-times fa-lg"></i>
                                    </button>
                                </div>

                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" width="100" class="mb-3 opacity-50">
                            <h5 class="text-muted">ไม่มีรายการรอตรวจสอบ</h5>
                            <p class="small text-muted">พักผ่อนได้เลย! งานทั้งหมดเรียบร้อยแล้ว</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="tab-pane fade <?php echo (isset($_GET['page'])) ? 'show active' : ''; ?>" id="pills-history">
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
                        <div class="table-responsive">
                            <table class="table history-table mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-4" style="width: 20%;">วันเดินทาง</th>
                                        <th style="width: 25%;">ข้อมูลผู้จอง</th>
                                        <th style="width: 20%;">สถานที่ไป</th>
                                        <th style="width: 25%;">รถ/คนขับ</th>
                                        <th class="text-center" style="width: 10%;">สถานะ/เอกสาร</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($result_history->num_rows > 0): ?>
                                        <?php while($h = $result_history->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center mb-1 text-success">
                                                    <i class="fas fa-play-circle me-2 small"></i> 
                                                    <span class="fw-bold"><?php echo date('d/m/y H:i', strtotime($h['start_date'])); ?></span>
                                                </div>
                                                <div class="d-flex align-items-center text-danger">
                                                    <i class="fas fa-stop-circle me-2 small"></i> 
                                                    <span class="fw-bold"><?php echo date('d/m/y H:i', strtotime($h['end_date'])); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark mb-1"><?php echo $h['fullname']; ?></div>
                                                <div class="small text-muted mb-1"><i class="fas fa-phone-alt me-1 text-secondary"></i> <?php echo $h['phone']; ?></div>
                                                <div class="small text-muted"><i class="far fa-envelope me-1 text-secondary"></i> <?php echo $h['email']; ?></div>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-medium"><?php echo $h['destination']; ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo $h['license_plate']; ?></div>
                                                <div class="small text-secondary mb-1"><?php echo $h['brand'].' '.$h['model']; ?></div>
                                                
                                                <?php if(!empty($h['driver_name'])): ?>
                                                    <div class="bg-light border rounded px-2 py-1 d-inline-block mt-1">
                                                        <div class="small text-dark fw-bold"><i class="fas fa-user-tie me-1 text-primary"></i> <?php echo $h['driver_name']; ?></div>
                                                        <div class="small text-muted" style="font-size: 0.75rem;"><i class="fas fa-phone me-1"></i> <?php echo $h['driver_phone']; ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">ไม่ระบุคนขับ</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center gap-2">
                                                    
                                                    <?php if($h['status'] == 'approved'): ?>
                                                        <span class="badge rounded-pill bg-success px-3 py-2">อนุมัติแล้ว</span>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill bg-danger px-3 py-2">ไม่อนุมัติ</span>
                                                    <?php endif; ?>

                                                    <a href="print_booking.php?id=<?php echo $h['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm text-nowrap">
                                                        <i class="fas fa-print me-1"></i> ใบงาน
                                                    </a>

                                                    <?php if($h['status'] != 'approved' && !empty($h['reject_reason'])): ?>
                                                        <div class="w-100 text-start">
                                                            <div class="p-2 rounded bg-danger bg-opacity-10 border border-danger border-opacity-25 text-danger small" style="font-size: 0.75rem;">
                                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                                <?php echo htmlspecialchars($h['reject_reason']); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มีประวัติการจอง</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination">
                                <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&tab=history"><i class="fas fa-chevron-left"></i></a>
                                </li>
                                <?php for($i=1; $i<=$total_pages; $i++): ?>
                                <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&tab=history"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&tab=history"><i class="fas fa-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-white border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark ps-2">ยืนยันการอนุมัติ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <form id="approveForm">
                        <input type="hidden" name="booking_id" id="modal_booking_id">
                        <input type="hidden" name="action" value="approve">
                        
                        <div class="alert alert-light border rounded-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">สถานที่</span>
                                <strong class="text-primary" id="modal_dest_show"></strong>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="text-muted small">รถ</span>
                                <span class="text-dark small" id="modal_car_show"></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted text-uppercase">มอบหมายคนขับรถ</label>
                            <select name="driver_id" class="form-select form-select-lg shadow-sm" required>
                                <option value="" selected disabled>-- เลือกคนขับ --</option>
                                <?php 
                                if($drivers->num_rows > 0) { 
                                    $drivers->data_seek(0); 
                                    while($d = $drivers->fetch_assoc()) { 
                                        echo '<option value="'.$d['id'].'">'.$d['name'].' ('.$d['phone'].')</option>'; 
                                    } 
                                } 
                                ?>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary rounded-pill btn-lg shadow-sm">
                                <i class="fas fa-check-circle me-2"></i>อนุมัติรายการ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    $(document).ready(function(){
        
        $('.btn-approve-modal').click(function(){
            var id = $(this).data('id');
            var dest = $(this).data('dest');
            var car = $(this).data('car');
            $('#modal_booking_id').val(id);
            $('#modal_dest_show').text(dest);
            $('#modal_car_show').text(car);
            new bootstrap.Modal(document.getElementById('approveModal')).show();
        });

        $('#approveForm').on('submit', function(e){
            e.preventDefault();
            var bookingId = $('#modal_booking_id').val();
            $.ajax({
                type: 'POST', url: 'admin_process.php', data: $(this).serialize(), dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        $('#approveModal').modal('hide'); 
                        Swal.fire({ icon: 'success', title: 'อนุมัติเรียบร้อย', showConfirmButton: false, timer: 1500 });
                        $('#card-' + bookingId).slideUp(300, function(){ $(this).remove(); location.reload(); });
                    } else { Swal.fire('Error', res.message, 'error'); }
                }
            });
        });

        $('.btn-reject-action').click(function(){
            var id = $(this).data('id');
            Swal.fire({
                title: 'ปฏิเสธคำขอ?', input: 'textarea', inputPlaceholder: 'ระบุเหตุผลที่ไม่อนุมัติ...',
                showCancelButton: true, confirmButtonText: 'ยืนยันปฏิเสธ', confirmButtonColor: '#dc3545',
                preConfirm: (reason) => {
                    if (!reason) { Swal.showValidationMessage('กรุณาระบุเหตุผล'); return false; }
                    return $.ajax({ type: 'POST', url: 'admin_process.php', data: { action: 'reject', booking_id: id, reject_reason: reason }, dataType: 'json' });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value.status === 'success') {
                    Swal.fire({ icon: 'success', title: 'ปฏิเสธเรียบร้อย', showConfirmButton: false, timer: 1500 });
                    $('#card-' + id).slideUp(300, function(){ $(this).remove(); location.reload(); });
                }
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('tab') && urlParams.get('tab') === 'history') {
            var tabTrigger = new bootstrap.Tab(document.querySelector('#tab-history'));
            tabTrigger.show();
        }
    });
    </script>
</body>
</html>