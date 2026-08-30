<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok'); 

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- ส่วนประมวลผล (AJAX Request) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    $response = ['status' => 'error', 'message' => 'เกิดข้อผิดพลาด'];
    
    try {
        $user_id = $_SESSION['user_id'];
        $vehicle_id = $_POST['vehicle_id'];
        $destination = $_POST['destination'];
        $passengers = $_POST['passengers'];
        $purpose = $_POST['purpose'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];

        if ($start_date >= $end_date) {
            $response = ['status' => 'time_error', 'message' => 'เวลาสิ้นสุดต้องหลังจากเวลาเริ่มต้น'];
        } else {
            $chk = $conn->prepare("SELECT count(*) as cnt FROM bookings WHERE vehicle_id = ? AND status = 'approved' AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))");
            $chk->bind_param("isssss", $vehicle_id, $start_date, $end_date, $start_date, $end_date, $start_date);
            $chk->execute();
            
            if ($chk->get_result()->fetch_assoc()['cnt'] > 0) {
                $response = ['status' => 'busy', 'message' => 'ขออภัย รถคันนี้มีผู้จองแล้วในช่วงเวลาดังกล่าว'];
            } else {
                $stmt = $conn->prepare("INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, destination, passengers, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iisssis", $user_id, $vehicle_id, $start_date, $end_date, $destination, $passengers, $purpose);

                if ($stmt->execute()) {
                    // --- ส่วนแจ้งเตือน LINE (คงเดิม) ---
                    $last_id = $conn->insert_id;
                    $q_info = $conn->query("SELECT brand, model, license_plate FROM vehicles WHERE id = $vehicle_id")->fetch_assoc();
                    $car_info = $q_info['brand'] . " " . $q_info['model'] . " (" . $q_info['license_plate'] . ")";

                    if (file_exists('line_helper.php')) {
                        require_once 'line_helper.php'; 
                        $bookingData = [
                            'id' => $last_id,
                            'fullname' => isset($_SESSION['fullname']) ? $_SESSION['fullname'] : 'User',
                            'destination' => $destination,
                            'car_info' => $car_info,
                            'date_range' => date('d/m/y H:i', strtotime($start_date)) . ' - ' . date('d/m/y H:i', strtotime($end_date))
                        ];
                        sendLineBookingAlert($bookingData);
                    }
                    // --- จบส่วนแจ้งเตือน LINE ---

                    $response = ['status' => 'success', 'message' => 'บันทึกข้อมูลสำเร็จ'];
                } else {
                    $response = ['status' => 'error', 'message' => $conn->error];
                }
            }
        }
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }

    echo json_encode($response);
    exit(); 
}

// ดึงข้อมูลรถ
$sql = "SELECT * FROM vehicles WHERE status = 'available' ORDER BY brand ASC";
$vehicles = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองยานพาหนะ - OVAS</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { 
            --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            --secondary-bg: #f8f9fc; 
            --text-color: #495057; 
            --input-radius: 12px; 
            --card-radius: 20px; 
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-color);
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 0 !important;
        }

        .main-content { flex: 1; margin-top: 150px !important; padding-bottom: 150px; }

        .glass-card { 
            background: #ffffff; 
            border-radius: var(--card-radius); 
            box-shadow: 0 15px 35px rgba(0,0,0,0.06); 
            border: 1px solid rgba(255,255,255,0.8); 
            overflow: hidden; 
            border-top: 4px solid #1e3c72; 
        }

        /* Button Style & Animation */
        .btn-blue-gradient { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            color: #ffffff !important; 
            border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; 
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); 
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }
        .btn-blue-gradient:hover { 
            background: linear-gradient(135deg, #162b52 0%, #1e3c72 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.4); 
        }
        .btn-loading { pointer-events: none; opacity: 0.8; transform: scale(0.98); }

        .form-control, .form-select { border-radius: var(--input-radius); padding: 12px; border: 1px solid #dee2e6; }
        .form-control:focus, .form-select:focus { border-color: #1e3c72; box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.15); }
        .flatpickr-input[readonly] { background-color: #fff !important; cursor: pointer; }

        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-animated { animation: slideDown 0.3s ease-out; }
        
        /* --- Modern SweetAlert Customization --- */
        div:where(.swal2-container) div:where(.swal2-popup) {
            border-radius: 24px !important;
            padding: 2rem !important;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15) !important;
        }
        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            border-radius: 50px !important;
            padding: 10px 30px !important;
            font-size: 1rem !important;
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3) !important;
        }
        
        footer { flex-shrink: 0; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="row justify-content-center">
                <div class="col-lg-8 col-md-10">
                    
                    <div class="d-flex align-items-center mb-4">
                        <a href="index.php" class="btn btn-light rounded-circle shadow-sm me-3" style="width: 45px; height: 45px; display:flex; align-items:center; justify-content:center;">
                            <i class="fas fa-arrow-left text-primary"></i>
                        </a>
                        <div>
                            <h2 class="fw-bold text-dark mb-0">จองยานพาหนะ</h2>
                            <p class="text-muted mb-0 small">กรอกรายละเอียดเพื่อขอใช้รถราชการ</p>
                        </div>
                    </div>

                    <div class="glass-card p-4 p-md-5">
                        <form action="" method="POST" id="bookingForm">
                            <div class="row g-4">
                                
                                <div class="col-md-12">
                                    <label class="form-label small text-muted ms-1 fw-bold">ยานพาหนะ <span class="text-danger">*</span></label>
                                    <select name="vehicle_id" id="car_id" class="form-select" required>
                                        <option value="" selected disabled>-- เลือกรายการรถ --</option>
                                        <?php 
                                        if ($vehicles->num_rows > 0) {
                                            $vehicles->data_seek(0);
                                            while($row = $vehicles->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $row['id']; ?>" <?php if(isset($_GET['vehicle_id']) && $_GET['vehicle_id'] == $row['id']) echo 'selected'; ?>>
                                                <?php echo $row['license_plate'] . ' - ' . $row['brand'] . ' ' . $row['model']; ?>
                                            </option>
                                        <?php endwhile; } ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small text-muted ms-1 fw-bold">วัน-เวลา เริ่มต้น <span class="text-danger">*</span></label>
                                    <input type="text" name="start_date" id="start_date" class="form-control datetime-picker" placeholder="เลือกวันเวลา..." required>
                                    <div class="form-text text-primary small mt-2"><i class="fas fa-clock me-1"></i> เวลา 08:00 - 16:30 น.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted ms-1 fw-bold">วัน-เวลา สิ้นสุด <span class="text-danger">*</span></label>
                                    <input type="text" name="end_date" id="end_date" class="form-control datetime-picker" placeholder="เลือกวันเวลา..." required>
                                </div>
                                
                                <div class="col-12">
                                    <div id="timeAlert" class="alert d-none alert-animated rounded-3 shadow-sm border-0 small py-3"></div>
                                </div>

                                <div class="col-md-8">
                                    <label class="form-label small text-muted ms-1 fw-bold">สถานที่ไป <span class="text-danger">*</span></label>
                                    <input type="text" name="destination" class="form-control" placeholder="เช่น กระทรวงศึกษาธิการ" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted ms-1 fw-bold">ผู้โดยสาร (คน) <span class="text-danger">*</span></label>
                                    <input type="number" name="passengers" class="form-control" value="1" min="1" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted ms-1 fw-bold">เหตุผล/รายละเอียด <span class="text-danger">*</span></label>
                                    <input type="text" name="purpose" class="form-control" placeholder="เช่น ส่งเอกสาร, ประชุม" required>
                                </div>
                                
                                <div class="col-12">
                                    <div id="availabilityAlert" class="alert d-none text-center shadow-sm rounded-3 py-3"></div>
                                </div>

                                <div class="col-12 text-end mt-4 d-flex gap-2 justify-content-end">
                                    <a href="index.php" class="btn btn-light text-muted border rounded-pill px-4 py-2">
                                        ยกเลิก
                                    </a>
                                    <button type="submit" id="btnSubmit" class="btn btn-blue-gradient rounded-pill px-5">
                                        <i class="fas fa-paper-plane me-2"></i> ยืนยันการจอง
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

    <script>
    flatpickr(".datetime-picker", {
        enableTime: true,
        dateFormat: "Y-m-d H:i",      
        altInput: true,               
        altFormat: "j F Y เวลา H:i น.", 
        locale: "th",
        time_24hr: true,
        minDate: "today",
        disableMobile: "true"
    });

    $(document).ready(function(){
        
        function validateAndCheck() {
            var startVal = $('#start_date').val();
            var endVal = $('#end_date').val();
            var vehicleId = $('#car_id').val();
            var submitBtn = $('#btnSubmit');
            var timeAlert = $('#timeAlert');
            var availAlert = $('#availabilityAlert');

            timeAlert.addClass('d-none').removeClass('alert-warning alert-danger');
            
            var isValidOfficeHour = true;
            [startVal, endVal].forEach(function(val) {
                if (val) {
                    var timePart = val.split(' ')[1]; 
                    if (timePart < "08:00" || timePart > "16:30") {
                        isValidOfficeHour = false;
                    }
                }
            });

            if (!isValidOfficeHour) {
                timeAlert.removeClass('d-none').addClass('alert-warning')
                    .html('<i class="fas fa-clock me-2"></i> กรุณาเลือกเวลาในช่วง <strong>08:00 - 16:30 น.</strong> เท่านั้น');
                submitBtn.prop('disabled', true).removeClass('btn-blue-gradient').addClass('btn-secondary-disabled');
                availAlert.addClass('d-none'); 
                return; 
            }

            if (startVal && endVal && startVal >= endVal) {
                timeAlert.removeClass('d-none').addClass('alert-danger')
                    .html('<i class="fas fa-exclamation-triangle me-2"></i> เวลาสิ้นสุดต้อง <strong>มากกว่า</strong> เวลาเริ่มต้น');
                submitBtn.prop('disabled', true).removeClass('btn-blue-gradient').addClass('btn-secondary-disabled');
                availAlert.addClass('d-none');
                return; 
            }

            if (vehicleId && startVal && endVal) {
                $.ajax({
                    url: 'check_availability.php',
                    type: 'POST',
                    data: { vehicle_id: vehicleId, start_date: startVal, end_date: endVal },
                    success: function(response) {
                        response = response.trim();
                        if(response == 'busy') {
                            availAlert.removeClass('d-none alert-success').addClass('alert-danger')
                                .html('<i class="fas fa-exclamation-circle me-2"></i> รถคันนี้ <strong>มีผู้จองแล้ว</strong> ในช่วงเวลาดังกล่าว');
                            submitBtn.prop('disabled', true).removeClass('btn-blue-gradient').addClass('btn-secondary-disabled'); 
                        } else {
                            availAlert.removeClass('d-none alert-danger').addClass('alert-success')
                                .html('<i class="fas fa-check-circle me-2"></i> รถว่าง! สามารถจองได้');
                            submitBtn.prop('disabled', false).removeClass('btn-secondary-disabled').addClass('btn-blue-gradient'); 
                            setTimeout(function(){ if(availAlert.hasClass('alert-success')) availAlert.addClass('d-none'); }, 3000);
                        }
                    }
                });
            } else {
                availAlert.addClass('d-none');
            }
        }

        $('#start_date, #end_date, #car_id').change(validateAndCheck);

        // --- ส่วนที่ปรับปรุง: Popup Loading & Success แบบในรูป ---
        $('#bookingForm').on('submit', function(e) {
            e.preventDefault(); 
            
            if ($('#btnSubmit').prop('disabled')) return false;

            let btn = $('#btnSubmit');
            let originalText = btn.html();

            // 1. ปุ่มสถานะ Loading (Spinner Modern)
            btn.prop('disabled', true).addClass('btn-loading');
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังส่งข้อมูล...');

            // 2. แสดง Popup "กำลังบันทึก..." (Clean Style)
            Swal.fire({
                title: 'กำลังบันทึก...',
                html: '<span class="text-secondary">กรุณารอสักครู่ ระบบกำลังบันทึกข้อมูล</span>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                background: '#fff',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            });

            // 3. ส่งข้อมูล (AJAX)
            $.ajax({
                type: 'POST', url: 'booking.php', data: $(this).serialize(), dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        
                        // 4. แสดง Popup "บันทึกสำเร็จ!" (ไอคอนสีน้ำเงิน Modern)
                        Swal.fire({ 
                            icon: 'success', 
                            iconColor: '#1e3c72', // สีไอคอนน้ำเงิน
                            title: 'บันทึกสำเร็จ!', 
                            text: 'ข้อมูลการจองถูกส่งเข้าระบบเรียบร้อยแล้ว',
                            confirmButtonColor: '#1e3c72', // ปุ่มสีน้ำเงิน
                            confirmButtonText: 'รับทราบ',
                            customClass: { 
                                popup: 'rounded-4 border-0 shadow-lg',
                                confirmButton: 'px-4 py-2 rounded-3 shadow-sm',
                                title: 'fw-bold text-dark mt-2'
                            },
                            allowOutsideClick: false
                        }).then((result) => { 
                            if (result.isConfirmed) {
                                window.location.href = 'index.php'; // หรือ my_history.php
                            }
                        });

                    } else {
                        // กรณี Error
                        btn.prop('disabled', false).removeClass('btn-loading').html(originalText);
                        
                        let iconType = (response.status === 'time_error') ? 'warning' : 'error';
                        Swal.fire({ 
                            icon: iconType, 
                            title: 'แจ้งเตือน', 
                            text: response.message, 
                            confirmButtonColor: '#1e3c72',
                            customClass: { popup: 'rounded-4 shadow-sm' } 
                        });
                    }
                },
                error: function() { 
                    btn.prop('disabled', false).removeClass('btn-loading').html(originalText);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์', customClass: { popup: 'rounded-4 shadow-sm' } }); 
                }
            });
        });
    });
    </script>
</body>
</html>