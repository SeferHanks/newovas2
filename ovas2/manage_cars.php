<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$sql = "SELECT * FROM vehicles ORDER BY status ASC, id DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลรถ - OVAS Admin</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-body: #f8f9fc; --primary-color: #1e3c72; }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--bg-body); display: flex; flex-direction: column; min-height: 100vh; padding-top: 160px; }
        .main-content { flex: 1; padding-bottom: 150px; }
        footer { flex-shrink: 0; }

        /* Card Design */
        .car-manage-card { background: #fff; border-radius: 12px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 15px; transition: all 0.2s; border-left: 4px solid transparent; padding: 15px 20px; }
        .car-manage-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .car-manage-card.available { border-left-color: #198754; } 
        .car-manage-card.maintenance { border-left-color: #dc3545; background-color: #fff5f5; }
        
        .car-img-box { width: 90px; height: 65px; border-radius: 10px; overflow: hidden; box-shadow: 0 3px 6px rgba(0,0,0,0.1); flex-shrink: 0; background: #fff; }
        .car-img-box img { width: 100%; height: 100%; object-fit: cover; }
        .car-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #eef2ff; color: #1e3c72; font-size: 1.8rem; }
        
        .plate-badge { border: 2px solid #e9ecef; border-radius: 8px; padding: 5px 15px; font-weight: 700; background-color: #fff; color: #343a40; letter-spacing: 0.5px; min-width: 110px; text-align: center; }
        
        .status-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
        .status-pill.active { background-color: #d1e7dd; color: #0f5132; }
        .status-pill.inactive { background-color: #f8d7da; color: #842029; }
        .dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .dot.green { background-color: #198754; }
        .dot.red { background-color: #dc3545; }
        
        .btn-circle { width: 40px; height: 40px; border-radius: 50%; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: none; transition: 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-circle.edit { background-color: #ffc107; color: #333; }
        .btn-circle.edit:hover { background-color: #e0a800; transform: scale(1.1); }
        .btn-circle.delete { background-color: #fff; color: #dc3545; border: 1px solid #dc3545; }
        .btn-circle.delete:hover { background-color: #dc3545; color: #fff; transform: scale(1.1); }
        
        .upload-area { border: 2px dashed #ced4da; border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: 0.3s; background: #fff; }
        .upload-area:hover { border-color: #1e3c72; background: #f0f4ff; }
        
        /* CSS Toast Customization */
        div:where(.swal2-container).swal2-top-end > .swal2-popup {
            border-left: 5px solid #1e3c72 !important;
        }
        .swal2-popup.swal2-toast .swal2-title {
            color: #1e3c72 !important;
            font-weight: 600;
        }
        .swal2-popup.swal2-toast {
            border-radius: 12px !important;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1) !important;
        }
        
        @media (min-width: 992px) { 
            .col-img { width: 12%; } .col-info { width: 25%; } .col-plate { width: 20%; text-align: center; } 
            .col-status { width: 15%; text-align: center; } .col-seat { width: 13%; text-align: center; } .col-action { width: 15%; text-align: end; } 
        }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h3 class="fw-bold text-dark mb-1"><i class="fas fa-car me-2 text-primary"></i>จัดการข้อมูลรถ</h3>
                    <p class="text-muted mb-0">เพิ่ม/แก้ไข ยานพาหนะในระบบ</p>
                </div>
                <button class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addCarModal">
                    <i class="fas fa-plus me-2"></i>เพิ่มรถใหม่
                </button>
            </div>

            <div class="row">
                <div class="col-12">
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): 
                            $status_class = ($row['status'] == 'available') ? 'available' : 'maintenance';
                            $img_html = (!empty($row['image']) && file_exists('uploads/'.$row['image'])) ? '<img src="uploads/'.$row['image'].'">' : '<div class="car-placeholder"><i class="fas fa-car-side"></i></div>';
                            $seat = $row['seat_capacity'] ?? '-';
                        ?>
                        <div class="car-manage-card <?php echo $status_class; ?>" id="card-<?php echo $row['id']; ?>">
                            <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3">
                                <div class="col-img"><div class="car-img-box"><?php echo $img_html; ?></div></div>
                                <div class="col-info"><h5 class="fw-bold text-dark mb-1"><?php echo $row['brand']; ?></h5><div class="text-muted small"><?php echo $row['model']; ?></div></div>
                                <div class="col-plate"><div class="plate-badge shadow-sm"><?php echo $row['license_plate']; ?></div></div>
                                <div class="col-seat"><span class="text-secondary fw-bold"><i class="fas fa-chair me-1 text-muted"></i> <?php echo $seat; ?> ที่นั่ง</span></div>
                                <div class="col-status">
                                    <?php if($row['status'] == 'available'): ?>
                                        <span class="status-pill active"><span class="dot green"></span> พร้อมใช้</span>
                                    <?php else: ?>
                                        <span class="status-pill inactive"><span class="dot red"></span> ซ่อมบำรุง</span>
                                    <?php endif; ?>
                                </div>
                                <div class="col-action d-flex gap-2 justify-content-lg-end mt-2 mt-lg-0">
                                    <button class="btn-circle edit btn-edit" data-id="<?php echo $row['id']; ?>" title="แก้ไข"><i class="fas fa-pen"></i></button>
                                    <button class="btn-circle delete btn-delete" data-id="<?php echo $row['id']; ?>" title="ลบ"><i class="fas fa-trash-alt"></i></button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted bg-white rounded-4 shadow-sm"><i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-25"></i><h4>ยังไม่มีข้อมูลรถ</h4><p>กดปุ่ม "เพิ่มรถใหม่" เพื่อเริ่มใช้งาน</p></div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="addCarModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-white">
                    <h5 class="modal-title fw-bold text-primary"><i class="fas fa-plus-circle me-2"></i>เพิ่มยานพาหนะ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="addCarForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-uppercase text-muted">รูปรถ</label>
                            <label class="upload-area w-100">
                                <input type="file" name="car_image" class="d-none" accept="image/*" onchange="previewImage(this, '#add_preview')">
                                <div id="add_preview"><i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2 opacity-50"></i><div class="text-muted small">คลิกเพื่ออัปโหลดรูปภาพ</div></div>
                            </label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small text-muted">ยี่ห้อ</label><input type="text" name="brand" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">รุ่น</label><input type="text" name="model" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">ทะเบียน</label><input type="text" name="license_plate" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">จำนวนที่นั่ง</label><input type="number" name="seat_capacity" class="form-control bg-light border-0" value="10" required></div>
                            <div class="col-12 mt-4"><button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">บันทึกข้อมูล</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editCarModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-white">
                    <h5 class="modal-title fw-bold text-warning"><i class="fas fa-pen me-2"></i>แก้ไขข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editCarForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="vehicle_id" id="edit_id">
                        
                        <div class="d-flex align-items-center mb-4 gap-3">
                            <div id="current_img_container" class="flex-shrink-0"></div>
                            <div class="flex-grow-1">
                                <label class="form-label small text-muted mb-1">เปลี่ยนรูปภาพ</label>
                                <input type="file" name="car_image" class="form-control form-control-sm" accept="image/*" onchange="previewImage(this, '#edit_preview_area')">
                                <div id="edit_preview_area" class="mt-2"></div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label small text-muted">ยี่ห้อ</label><input type="text" name="brand" id="edit_brand" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">รุ่น</label><input type="text" name="model" id="edit_model" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">ทะเบียน</label><input type="text" name="license_plate" id="edit_plate" class="form-control bg-light border-0" required></div>
                            <div class="col-md-6"><label class="form-label small text-muted">จำนวนที่นั่ง</label><input type="number" name="seat_capacity" id="edit_seats" class="form-control bg-light border-0" required></div>
                            <div class="col-12"><label class="form-label small text-muted">สถานะ</label><select name="status" id="edit_status" class="form-select bg-light border-0"><option value="available">🟢 พร้อมใช้งาน</option><option value="maintenance">🔴 ซ่อมบำรุง / งดใช้</option></select></div>
                            <div class="col-12 mt-4"><button type="submit" class="btn btn-warning text-dark w-100 rounded-pill py-2 fw-bold shadow-sm">อัปเดตข้อมูล</button></div>
                        </div>
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
                $(target).html('<img src="' + e.target.result + '" style="max-height: 100px; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    $(document).ready(function(){

        // --- ตั้งค่า Toast (เอา iconColor ออก เพื่อใช้สี Default) ---
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

        // 1. เพิ่มรถ (Add)
        $('#addCarForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            // ไม่มีอนิเมชั่นโหลด

            $.ajax({
                url: 'manage_cars_process.php', type: 'POST', data: formData, dataType: 'json',
                contentType: false, processData: false,
                success: function(res){
                    if(res.status == 'success'){ 
                        // เพิ่มรถสำเร็จ: ไอคอนเขียว
                        Toast.fire({ icon: 'success', title: 'เพิ่มรถใหม่เรียบร้อย', iconColor: '#198754' }).then(() => location.reload()); 
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
                url: 'manage_cars_process.php', type: 'POST', data: {action: 'fetch_single', id: id}, dataType: 'json',
                success: function(data){
                    $('#edit_id').val(data.id);
                    $('#edit_brand').val(data.brand);
                    $('#edit_model').val(data.model);
                    $('#edit_plate').val(data.license_plate);
                    $('#edit_seats').val(data.seat_capacity || '');
                    $('#edit_status').val(data.status);
                    
                    if(data.image) {
                        $('#current_img_container').html('<img src="uploads/'+data.image+'" style="width:80px; height:60px; object-fit:cover; border-radius:8px;">');
                    } else {
                        $('#current_img_container').html('<div class="bg-light rounded p-2 text-center" style="width:80px"><i class="fas fa-image text-muted"></i></div>');
                    }
                    $('#edit_preview_area').html('');
                    $('#editCarModal').modal('show');
                }
            });
        });

        // 3. บันทึกแก้ไข (Update)
        $('#editCarForm').on('submit', function(e){
            e.preventDefault();
            var formData = new FormData(this);
            // ไม่มีอนิเมชั่นโหลด

            $.ajax({
                url: 'manage_cars_process.php', type: 'POST', data: formData, dataType: 'json',
                contentType: false, processData: false,
                success: function(res){
                    if(res.status == 'success'){ 
                        // แก้ไขสำเร็จ: ไอคอนเขียว
                        Toast.fire({ icon: 'success', title: 'แก้ไขข้อมูลเรียบร้อย', iconColor: '#198754' }).then(() => location.reload()); 
                    } else { 
                        Toast.fire({ icon: 'error', title: 'แก้ไขข้อมูลผิดพลาด', text: res.message });
                    }
                }
            });
        });

        // 4. ลบรถ (Delete)
        $('.btn-delete').click(function(){
            var id = $(this).data('id');
            
            Swal.fire({
                title: 'ยืนยันการลบ?',
                text: "ข้อมูลจะหายไปถาวร",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'ลบเลย',
                cancelButtonText: 'ยกเลิก',
                reverseButtons: true,
                customClass: { popup: 'rounded-4' }
            }).then((result) => {
                if (result.isConfirmed) {
                    // ไม่มีอนิเมชั่นโหลด
                    $.ajax({
                        url: 'manage_cars_process.php', type: 'POST', data: {action: 'delete', id: id}, dataType: 'json',
                        success: function(res){
                            if(res.status == 'success'){ 
                                // ลบสำเร็จ: ไอคอนเขียว
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