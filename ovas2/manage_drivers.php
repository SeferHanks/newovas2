<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// --- 1. เตรียมข้อมูลรถ (สำหรับ Dropdown) ---
$vehicles_sql = "SELECT id, brand, model, license_plate FROM vehicles ORDER BY brand";
$vehicles_res = $conn->query($vehicles_sql);
$vehicle_options = "";
if ($vehicles_res->num_rows > 0) {
    while($v = $vehicles_res->fetch_assoc()) {
        $vehicle_options .= '<option value="'.$v['id'].'">'.$v['license_plate'].' - '.$v['brand'].' '.$v['model'].'</option>';
    }
}

// --- 2. Pagination Logic ---
$limit = 8; 
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if($page < 1) $page = 1;
$start = ($page - 1) * $limit;

$sql_count = "SELECT COUNT(*) as total FROM drivers";
$res_count = $conn->query($sql_count);
$row_count = $res_count->fetch_assoc();
$total_rows = $row_count['total'];
$total_pages = ceil($total_rows / $limit);

// --- 3. ดึงข้อมูลคนขับ ---
$sql = "SELECT d.*, v.brand, v.model, v.license_plate 
        FROM drivers d
        LEFT JOIN vehicles v ON d.vehicle_id = v.id
        ORDER BY d.status ASC, d.id DESC 
        LIMIT $start, $limit";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคนขับรถ - OVAS Admin</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

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

        .main-content { flex: 1; padding-bottom: 150px; }
        footer { flex-shrink: 0; }

        /* Card Design */
        .driver-card { background: #fff; border-radius: 12px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 15px; transition: all 0.2s; border-left: 5px solid transparent; padding: 15px 20px; }
        .driver-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .driver-card.active { border-left-color: #198754; } 
        .driver-card.inactive { border-left-color: #dc3545; background-color: #fff5f5; }

        .avatar-box { width: 60px; height: 60px; border-radius: 50%; overflow: hidden; box-shadow: 0 3px 6px rgba(0,0,0,0.1); flex-shrink: 0; background: #eef2ff; color: #1e3c72; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }

        .status-pill { padding: 4px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; }
        .status-pill.active { background-color: #d1e7dd; color: #0f5132; }
        .status-pill.inactive { background-color: #f8d7da; color: #842029; }

        .btn-circle { width: 38px; height: 38px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-circle.edit { background-color: #ffc107; color: #333; }
        .btn-circle.edit:hover { background-color: #e0a800; transform: scale(1.1); }
        .btn-circle.delete { background-color: #fff; color: #dc3545; border: 1px solid #dc3545; }
        .btn-circle.delete:hover { background-color: #dc3545; color: #fff; transform: scale(1.1); }
        
        .upload-area { border: 2px dashed #ced4da; border-radius: 10px; padding: 15px; text-align: center; cursor: pointer; transition: 0.3s; background: #fff; }
        .upload-area:hover { border-color: #1e3c72; background: #f0f4ff; }

        /* ปรับสีข้อความใน SweetAlert (Toast) */
        div:where(.swal2-container).swal2-top-end > .swal2-popup {
            border-left: 5px solid #1e3c72 !important;
        }
        .swal2-popup.swal2-toast .swal2-title {
            color: #1e3c72 !important;
            font-weight: 600;
        }

        @media (min-width: 992px) { 
            .col-avatar { width: 8%; } .col-info { width: 25%; } .col-car { width: 27%; } 
            .col-phone { width: 20%; } .col-status { width: 10%; text-align: center; } .col-action { width: 10%; text-align: end; } 
        }

        .pagination .page-link { border: none; color: #6c757d; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; margin: 0 3px; font-weight: 600; }
        .pagination .page-link:hover { background-color: #e9ecef; color: #1e3c72; }
        .pagination .page-item.active .page-link { background-color: #1e3c72; color: #fff; box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); }
        .pagination .page-item.disabled .page-link { background-color: transparent; opacity: 0.5; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-id-card me-2 text-primary"></i>จัดการคนขับรถ</h3>
                    <p class="text-muted mb-0">เพิ่ม/ลบ/แก้ไข รายชื่อพนักงานขับรถ</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addDriverModal">
                    <i class="fas fa-user-plus me-2"></i>เพิ่มคนขับ
                </button>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status_class = ($row['status'] == 'active') ? 'active' : 'inactive';
                            
                            $img_html = '<i class="fas fa-user-tie"></i>';
                            if(!empty($row['image']) && file_exists('uploads/'.$row['image'])) {
                                $img_html = '<img src="uploads/'.$row['image'].'">';
                            }
                            
                            $car_info = '<span class="text-muted small">- ไม่ระบุ -</span>';
                            if(!empty($row['brand'])) {
                                $car_info = '<div class="fw-bold text-dark">'.$row['brand'].' '.$row['model'].'</div>
                                             <div class="badge bg-light text-dark border">'.$row['license_plate'].'</div>';
                            }
                        ?>
                        <div class="driver-card <?php echo $status_class; ?>" id="card-<?php echo $row['id']; ?>">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                                <div class="col-avatar"><div class="avatar-box"><?php echo $img_html; ?></div></div>
                                <div class="col-info">
                                    <h5 class="fw-bold text-dark mb-1"><?php echo $row['name']; ?></h5>
                                    <div class="text-muted small">ID: DRV-<?php echo str_pad($row['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                </div>
                                <div class="col-car">
                                    <div class="d-flex align-items-start gap-2">
                                        <div class="pt-1 text-primary"><i class="fas fa-car"></i></div>
                                        <div><?php echo $car_info; ?></div>
                                    </div>
                                </div>
                                <div class="col-phone">
                                    <div class="d-flex align-items-center text-secondary fw-bold">
                                        <i class="fas fa-phone-alt me-2 text-success"></i> <?php echo $row['phone']; ?>
                                    </div>
                                    <?php if(!empty($row['line_user_id'])): ?>
                                        <small class="text-success"><i class="fab fa-line me-1"></i>เชื่อมต่อแล้ว</small>
                                    <?php else: ?>
                                        <small class="text-muted"><i class="fab fa-line me-1"></i>ยังไม่เชื่อมต่อ</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-status">
                                    <?php if($row['status'] == 'active'): ?>
                                        <span class="status-pill active"><i class="fas fa-circle fa-xs me-1"></i> ปกติ</span>
                                    <?php else: ?>
                                        <span class="status-pill inactive"><i class="fas fa-times-circle me-1"></i> พักงาน</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-action d-flex gap-2 justify-content-lg-end mt-2 mt-lg-0">
                                    <button class="btn-circle edit btn-edit" data-id="<?php echo $row['id']; ?>" title="แก้ไข"><i class="fas fa-pen"></i></button>
                                    <button class="btn-circle delete btn-delete" data-id="<?php echo $row['id']; ?>" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>

                        <?php if($total_pages > 1): ?>
                        <div class="d-flex justify-content-center mt-5">
                            <nav>
                                <ul class="pagination">
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
                        <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm"><i class="fas fa-users-slash fa-3x mb-3 text-secondary opacity-25"></i><h4>ยังไม่มีข้อมูลคนขับ</h4><p>กดปุ่ม "เพิ่มคนขับ" เพื่อเริ่มใช้งาน</p></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addDriverModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-white">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-user-plus me-2"></i>เพิ่มคนขับรถใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addDriverForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-4 text-center">
                            <label class="upload-area w-50 mx-auto rounded-circle d-flex align-items-center justify-content-center" style="height: 120px; overflow: hidden; position: relative;">
                                <input type="file" name="driver_image" class="d-none" accept="image/*" onchange="previewImage(this, '#add_preview')">
                                <div id="add_preview" style="position: absolute; width: 100%; height: 100%; top: 0; left: 0; display: flex; align-items: center; justify-content: center; background: #fff;">
                                    <div class="text-center"><i class="fas fa-camera fa-2x text-muted mb-1"></i><div class="small text-muted" style="font-size: 0.7rem;">รูปโปรไฟล์</div></div>
                                </div>
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">ชื่อ-นามสกุล</label>
                            <input type="text" name="name" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold text-success"><i class="fab fa-line me-1"></i>LINE User ID (สำหรับแจ้งงาน)</label>
                            <input type="text" name="line_user_id" class="form-control bg-light border-0" placeholder="Uxxxxxxxxxxxxxxxxxxxx...">
                        </div>

                        <div class="mb-4">
                            <label class="form-label small text-muted">รถที่ขับประจำ</label>
                            <select name="vehicle_id" class="form-select bg-light border-0">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php echo $vehicle_options; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">บันทึกข้อมูล</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editDriverModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-white">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-user-edit me-2"></i>แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editDriverForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="driver_id" id="edit_id">
                        
                        <div class="d-flex align-items-center mb-4 gap-3">
                            <div id="current_img_container" class="flex-shrink-0"></div>
                            <div class="flex-grow-1">
                                <label class="form-label small text-muted mb-1">เปลี่ยนรูปภาพ</label>
                                <input type="file" name="driver_image" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this, '#edit_preview_area')">
                                <div id="edit_preview_area" class="mt-2" style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden;"></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">ชื่อ-นามสกุล</label>
                            <input type="text" name="name" id="edit_name" class="form-control bg-light border-0" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">เบอร์โทรศัพท์</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control bg-light border-0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold text-success"><i class="fab fa-line me-1"></i>LINE User ID</label>
                            <input type="text" name="line_user_id" id="edit_line_user_id" class="form-control bg-light border-0" placeholder="Uxxxxxxxxxxxxxxxxxxxx...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small text-muted">รถที่ขับประจำ</label>
                            <select name="vehicle_id" id="edit_vehicle_id" class="form-select bg-light border-0">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php echo $vehicle_options; ?>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small text-muted">สถานะ</label>
                            <select name="status" id="edit_status" class="form-select bg-light border-0">
                                <option value="active">🟢 พร้อมปฏิบัติงาน</option>
                                <option value="inactive">🔴 พักงาน / ลาออก</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning text-dark w-100 rounded-pill py-2 fw-bold shadow-sm">อัปเดตข้อมูล</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    function previewImage(input, target) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $(target).html('<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $(document).ready(function(){

        // --- Config Toast (แจ้งเตือนมุมขวาบน) ---
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        // 1. เพิ่มคนขับ
        $('#addDriverForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            // ไม่มีอนิเมชั่นโหลด

            $.ajax({
                url: 'manage_drivers_process.php', type: 'POST', data: formData, dataType: 'json',
                contentType: false, processData: false,
                success: function(res){
                    if(res.status == 'success'){ 
                        // แจ้งเตือนมุมขวาบน (Toast)
                        Toast.fire({ icon: 'success', title: 'เพิ่มคนขับเรียบร้อย', iconColor: '#198754' }).then(() => location.reload()); 
                    } else { 
                        Toast.fire({ icon: 'error', title: 'เพิ่มข้อมูลผิดพลาด', text: res.message });
                    }
                }
            });
        });

        // 2. ดึงข้อมูลแก้ไข
        $('.btn-edit').click(function(){
            var id = $(this).data('id');
            // ไม่มีอนิเมชั่นโหลด

            $.ajax({
                url: 'manage_drivers_process.php', type: 'POST', data: {action: 'fetch_single', id: id}, dataType: 'json',
                success: function(data){
                    $('#edit_id').val(data.id);
                    $('#edit_name').val(data.name);
                    $('#edit_phone').val(data.phone);
                    $('#edit_status').val(data.status);
                    $('#edit_vehicle_id').val(data.vehicle_id);
                    $('#edit_line_user_id').val(data.line_user_id); 

                    if(data.image) {
                        $('#current_img_container').html('<img src="uploads/'+data.image+'" style="width:60px; height:60px; object-fit:cover; border-radius:50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">');
                    } else {
                        $('#current_img_container').html('<div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:60px; height:60px; color:#1e3c72; font-size:1.5rem;"><i class="fas fa-user-tie"></i></div>');
                    }
                    $('#edit_preview_area').html(''); 
                    $('#editDriverModal').modal('show');
                }
            });
        });

        // 3. บันทึกแก้ไข (Update)
        $('#editDriverForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            // ไม่มีอนิเมชั่นโหลด

            $.ajax({
                url: 'manage_drivers_process.php', type: 'POST', data: formData, dataType: 'json',
                contentType: false, processData: false,
                success: function(res){
                    if(res.status == 'success'){ 
                        // แจ้งเตือนมุมขวาบน (Toast)
                        Toast.fire({ icon: 'success', title: 'แก้ไขข้อมูลเรียบร้อย', iconColor: '#198754' }).then(() => location.reload()); 
                    } else { 
                        Toast.fire({ icon: 'error', title: 'แก้ไขข้อมูลผิดพลาด', text: res.message });
                    }
                }
            });
        });

        // 4. ลบคนขับ
        $('.btn-delete').click(function(){
            var id = $(this).data('id');
            Swal.fire({
                title: 'ยืนยันการลบ?', text: "ข้อมูลนี้จะหายไปถาวร!", icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบเลย', cancelButtonText: 'ยกเลิก', reverseButtons: true,
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'manage_drivers_process.php', type: 'POST', data: {action: 'delete', id: id}, dataType: 'json',
                        success: function(res){
                            if(res.status == 'success'){ 
                                // แจ้งเตือนมุมขวาบน (Toast)
                                Toast.fire({ icon: 'success', title: 'ลบข้อมูลเรียบร้อย', iconColor: '#198754' }).then(() => location.reload()); 
                            } else { 
                                Toast.fire({ icon: 'error', title: 'ลบข้อมูลผิดพลาด', text: res.message });
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