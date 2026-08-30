<?php
session_start();
require_once 'db_connect.php';
date_default_timezone_set('Asia/Bangkok'); 

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// --- 1. ส่วนประมวลผลการจอง (ย้ายมารวมที่นี่ เพื่อให้เหมือน booking.php) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'book_vehicle') {
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
            // เช็คคิวว่าง
            $chk = $conn->prepare("SELECT count(*) as cnt FROM bookings WHERE vehicle_id = ? AND status = 'approved' AND ((start_date BETWEEN ? AND ?) OR (end_date BETWEEN ? AND ?) OR (? BETWEEN start_date AND end_date))");
            $chk->bind_param("isssss", $vehicle_id, $start_date, $end_date, $start_date, $end_date, $start_date);
            $chk->execute();
            
            if ($chk->get_result()->fetch_assoc()['cnt'] > 0) {
                $response = ['status' => 'busy', 'message' => 'ขออภัย รถคันนี้มีผู้จองแล้วในช่วงเวลาดังกล่าว'];
            } else {
                // บันทึกข้อมูล
                $stmt = $conn->prepare("INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, destination, passengers, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iisssis", $user_id, $vehicle_id, $start_date, $end_date, $destination, $passengers, $purpose);

                if ($stmt->execute()) {
                    // --- เริ่มส่งแจ้งเตือน LINE ---
                    $last_id = $conn->insert_id;
                    
                    // ดึงข้อมูลเพิ่มเติมเพื่อส่งไลน์
                    $info_sql = "SELECT u.fullname, v.brand, v.model, v.license_plate 
                                 FROM users u, vehicles v 
                                 WHERE u.id = $user_id AND v.id = $vehicle_id";
                    $info_res = $conn->query($info_sql)->fetch_assoc();
                    
                    $fullname = $info_res['fullname']; // ใช้ชื่อจริงจาก DB ชัวร์กว่า Session
                    $car_info = $info_res['brand'] . " " . $info_res['model'] . " (" . $info_res['license_plate'] . ")";

                    if (file_exists('line_helper.php')) {
                        require_once 'line_helper.php'; 
                        $bookingData = [
                            'id' => $last_id,
                            'fullname' => $fullname,
                            'destination' => $destination,
                            'car_info' => $car_info,
                            'date_range' => date('d/m/y H:i', strtotime($start_date)) . ' - ' . date('d/m/y H:i', strtotime($end_date))
                        ];
                        sendLineBookingAlert($bookingData);
                    }
                    // --- จบส่งไลน์ ---

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
    exit(); // จบการทำงานทันที (ไม่ให้ HTML ด้านล่างติดไปด้วย)
}

// ดึงข้อมูลรถ
$sql = "SELECT * FROM vehicles WHERE status = 'available'";
$vehicles = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ปฏิทินการใช้รถ - OVAS</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        :root { --primary-gradient: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); --secondary-bg: #f8f9fc; --text-color: #495057; --input-radius: 12px; --card-radius: 20px; }

        body {
            font-family: 'Sarabun', sans-serif;
            background-color: var(--secondary-bg);
            color: var(--text-color);
            display: flex; flex-direction: column; min-height: 100vh;
            padding-top: 160px; 
        }

        .main-content { flex: 1; padding-bottom: 150px; margin-top: 50px; }

        /* Glass Card */
        .glass-card { background: #ffffff; border-radius: var(--card-radius); box-shadow: 0 15px 35px rgba(0,0,0,0.06); border: 1px solid rgba(255,255,255,0.8); overflow: hidden; }

        /* Button Style */
        .btn-blue-gradient { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            color: #ffffff !important; 
            border: none; border-radius: 50px; padding: 10px 25px; font-weight: 500; 
            box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }
        .btn-blue-gradient:hover { 
            background: linear-gradient(135deg, #162b52 0%, #1e3c72 100%); 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(30, 60, 114, 0.4); 
        }
        
        /* Loading State Button */
        .btn-loading {
            pointer-events: none;
            opacity: 0.8;
            transform: scale(0.98);
        }

        /* FullCalendar Customization */
        .fc { font-family: 'Sarabun', sans-serif; }
        .fc-toolbar-title { font-size: 1.5rem !important; font-weight: 700; color: #1e3c72; }
        .fc .fc-button { border-radius: 50px !important; border: none !important; background-color: #ffffff !important; color: #555 !important; box-shadow: 0 2px 6px rgba(0,0,0,0.05) !important; font-weight: 600 !important; padding: 8px 20px !important; }
        .fc .fc-button-active, .fc .fc-button:hover { background: var(--primary-gradient) !important; color: #ffffff !important; }
        .fc-day-today { background-color: #f8faff !important; }
        .fc-event { border: none; border-radius: 8px; box-shadow: 0 3px 6px rgba(0,0,0,0.08); cursor: pointer; transition: transform 0.2s; }
        .fc-event:hover { transform: scale(1.02); }

        /* Input Style */
        .form-control, .form-select { border-radius: var(--input-radius); padding: 12px; border: 1px solid #dee2e6; }
        .form-control:focus { border-color: #1e3c72; box-shadow: 0 0 0 0.2rem rgba(30, 60, 114, 0.15); }
        .flatpickr-input[readonly] { background-color: #fff !important; cursor: pointer; }

        #bookingSection { display: none; margin-bottom: 30px; }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-animated { animation: slideDown 0.3s ease-out; }
        
        /* Modern SweetAlert Styling */
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

            <div class="row align-items-center mb-4 g-3">
                <div class="col-md-7">
                    <h2 class="fw-bold text-dark mb-1">
                        <i class="fas fa-calendar-check me-2 text-primary"></i>ตารางการใช้รถ
                    </h2>
                    <p class="text-muted mb-0">ตรวจสอบคิวรถว่าง และจองยานพาหนะสำหรับราชการ</p>
                </div>
                <?php if($_SESSION['role'] == 'teacher' || $_SESSION['role'] == 'admin'): ?>
                <div class="col-md-5 text-md-end">
                    <button class="btn btn-blue-gradient" id="toggleFormBtn">
                        <i class="fas fa-plus-circle me-2"></i>จองรถใหม่
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div id="bookingSection">
                <div class="glass-card p-4 p-md-5" style="border-top: 4px solid #1e3c72;">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold text-dark mb-0"><i class="fas fa-pen-nib me-2 text-primary"></i>กรอกข้อมูลการจอง</h5>
                        <button type="button" class="btn-close" id="closeFormBtn"></button>
                    </div>
                    
                    <form id="bookingForm">
                        <input type="hidden" name="action" value="book_vehicle">
                        <div class="row g-4">
                            <div class="col-md-12">
                                <label class="form-label small text-muted ms-1 fw-bold">ยานพาหนะ</label>
                                <select name="vehicle_id" id="car_id" class="form-select" required>
                                    <option value="" selected disabled>-- เลือกรายการรถ --</option>
                                    <?php 
                                    if ($vehicles->num_rows > 0) {
                                        $vehicles->data_seek(0);
                                        while($row = $vehicles->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>">
                                            <?php echo $row['license_plate'] . ' - ' . $row['brand'] . ' ' . $row['model']; ?>
                                        </option>
                                    <?php endwhile; } ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted ms-1 fw-bold">วัน-เวลา เริ่มต้น</label>
                                <input type="text" name="start_date" id="start_date" class="form-control datetime-picker" placeholder="เลือกวันเวลา..." required>
                                <div class="form-text text-primary small mt-2"><i class="fas fa-clock me-1"></i> เวลา 08:00 - 16:30 น.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted ms-1 fw-bold">วัน-เวลา สิ้นสุด</label>
                                <input type="text" name="end_date" id="end_date" class="form-control datetime-picker" placeholder="เลือกวันเวลา..." required>
                            </div>
                            
                            <div class="col-12">
                                <div id="timeAlert" class="alert d-none alert-animated rounded-3 shadow-sm border-0 small py-3"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-muted ms-1 fw-bold">สถานที่ไป</label>
                                <input type="text" name="destination" class="form-control" placeholder="เช่น กระทรวงศึกษาธิการ" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted ms-1 fw-bold">จำนวนผู้โดยสาร (คน)</label>
                                <input type="number" name="passengers" class="form-control" value="1" min="1" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small text-muted ms-1 fw-bold">เหตุผล/รายละเอียด</label>
                                <input type="text" name="purpose" class="form-control" placeholder="เช่น ส่งเอกสาร, ประชุม" required>
                            </div>
                            
                            <div class="col-12">
                                <div id="availabilityAlert" class="alert d-none text-center shadow-sm rounded-3 py-3"></div>
                            </div>

                            <div class="col-12 text-end mt-3">
                                <button type="button" class="btn btn-light text-muted border me-2 rounded-pill px-4" id="cancelFormBtn">ยกเลิก</button>
                                <button type="submit" id="btnSubmit" class="btn btn-blue-gradient rounded-pill px-5">
                                    <i class="fas fa-paper-plane me-2"></i> ยืนยันการจอง
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mb-4 d-flex align-items-center">
                <span class="text-muted small me-3 fw-bold text-uppercase">สถานะ:</span>
                <div class="me-3">
                    <span class="badge rounded-pill bg-success px-3 py-2"><i class="fas fa-check-circle me-1"></i> อนุมัติแล้ว</span>
                </div>
                <div>
                    <span class="badge rounded-pill bg-warning text-dark px-3 py-2"><i class="fas fa-clock me-1"></i> รออนุมัติ</span>
                </div>
            </div>

            <div class="glass-card">
                <div class="card-body p-3 p-md-4">
                    <div id='calendar'></div>
                </div>
            </div>

        </div>
    </div>

    <div class="modal fade" id="eventModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg rounded-4"><div class="modal-header text-white border-0" style="background: var(--primary-gradient);"><h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>รายละเอียด</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4 bg-white"><div class="mb-3"><label class="small text-muted fw-bold text-uppercase">ผู้จอง</label><div id="modalUser" class="fs-5 fw-bold text-dark"></div></div><div class="mb-3"><label class="small text-muted fw-bold text-uppercase">สถานที่</label><div id="modalDest" class="text-secondary"></div></div><div class="mb-3"><label class="small text-muted fw-bold text-uppercase">รถที่จอง</label><div id="modalCar" class="text-secondary"></div></div><div class="row"><div class="col-12 mb-3"><div class="p-3 bg-light rounded-3 border-start border-4 border-primary"><label class="small text-muted fw-bold mb-1 d-block">ช่วงเวลา</label><span id="modalTime" class="fw-bold text-primary"></span></div></div></div><div><span id="modalStatus"></span></div></div><div class="modal-footer border-0 pt-0 bg-white"><button type="button" class="btn btn-light rounded-pill px-4 w-100" data-bs-dismiss="modal">ปิดหน้าต่าง</button></div></div></div></div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

    <script>
    flatpickr(".datetime-picker", {
        enableTime: true,
        dateFormat: "Y-m-d H:i", altInput: true, altFormat: "j F Y เวลา H:i น.", 
        locale: "th", time_24hr: true, minDate: "today", disableMobile: "true"
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

        $("#toggleFormBtn").click(function(){ $("#bookingSection").slideToggle(400); });
        $("#cancelFormBtn, #closeFormBtn").click(function(){ $("#bookingSection").slideUp(300); });

        // --- ส่วนอนิเมชั่นตอนกด Submit (Modern & Minimal) ---
        $('#bookingForm').on('submit', function(e) {
            e.preventDefault(); 
            
            if ($('#btnSubmit').prop('disabled')) return false;

            let btn = $('#btnSubmit');
            let originalText = btn.html();

            // 1. ปุ่มเปลี่ยนสถานะ (Smooth Transition)
            btn.prop('disabled', true).addClass('btn-loading');
            btn.html('<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> กำลังส่งข้อมูล...');

            // 2. แสดง SweetAlert Loading
            Swal.fire({
                title: 'กำลังบันทึก...',
                html: '<span class="text-secondary">กรุณารอสักครู่ ระบบกำลังบันทึกข้อมูล</span>',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => { Swal.showLoading(); },
                background: '#fff',
                customClass: { popup: 'rounded-4 shadow-lg border-0' }
            });

            // 3. ส่งข้อมูล (AJAX)
            $.ajax({
                type: 'POST', url: 'index.php', data: $(this).serialize(), dataType: 'json', // เรียกไฟล์ตัวเอง
                success: function(response) {
                    if (response.status === 'success') {
                        
                        // 4. Success Popup (Modern Minimal Blue)
                        Swal.fire({ 
                            icon: 'success', 
                            iconColor: '#1e3c72', // สีไอคอนน้ำเงินตามธีม
                            title: 'บันทึกสำเร็จ!', 
                            text: 'ข้อมูลการจองถูกส่งเข้าระบบเรียบร้อยแล้ว',
                            confirmButtonColor: '#1e3c72',
                            confirmButtonText: 'รับทราบ',
                            customClass: { 
                                popup: 'rounded-4 border-0 shadow-lg',
                                confirmButton: 'px-4 py-2 rounded-3 shadow-sm',
                                title: 'fw-bold text-dark mt-2'
                            },
                            allowOutsideClick: false
                        }).then((result) => { 
                            if (result.isConfirmed) {
                                location.reload(); 
                            }
                        });
                        
                        $("#bookingSection").slideUp(); 
                        $('#bookingForm')[0].reset(); 
                        btn.prop('disabled', false).removeClass('btn-loading').html(originalText);

                    } else {
                        // Error State
                        btn.prop('disabled', false).removeClass('btn-loading').html(originalText);
                        
                        let iconType = (response.status === 'time_error') ? 'warning' : 'error';
                        let titleText = (response.status === 'time_error') ? 'เวลาไม่ถูกต้อง' : 'แจ้งเตือน';

                        Swal.fire({ 
                            icon: iconType, 
                            title: titleText, 
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

    // Calendar Script
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        if (calendarEl) {
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth', 
                locale: 'th', 
                height: 'auto',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
                buttonText: { today: 'วันนี้', month: 'เดือน', list: 'รายการ' },
                events: 'fetch_events.php', 
                displayEventEnd: true,
                
                // 1. eventDidMount: จัดการสีพื้นหลัง (เฉพาะมุมมองเดือน)
                eventDidMount: function(info) {
                    var status = info.event.extendedProps.status;
                    
                    // เช็คว่าเป็นมุมมอง List หรือไม่?
                    if (info.view.type === 'listMonth') {
                        // ถ้าเป็น List ให้พื้นหลังเป็นสีขาว ตัวหนังสือสีดำ
                        // แต่ใส่จุดสี (Dot) บอกสถานะแทน (FullCalendar จัดการ Dot ให้อัตโนมัติถ้าไม่กำหนด background)
                        info.el.style.backgroundColor = 'transparent';
                        
                        // กำหนดสีจุด (Dot Color)
                        var dot = info.el.querySelector('.fc-list-event-dot');
                        if (dot) {
                            dot.style.borderColor = (status === 'approved') ? '#198754' : '#ffc107';
                        }
                    } else {
                        // ถ้าเป็นมุมมองเดือน (Grid) ให้เป็นแถบสีเหมือนเดิม
                        if (status === 'pending') {
                            info.el.style.backgroundColor = '#ffc107'; 
                            info.el.style.borderColor = '#ffc107';
                        } else if (status === 'approved') {
                            info.el.style.backgroundColor = '#198754'; 
                            info.el.style.borderColor = '#198754';
                        }
                    }
                },

                // 2. eventContent: จัดการเนื้อหาข้อความให้สวยงาม
                eventContent: function(arg) {
                    var title = arg.event.title; 
                    var car = arg.event.extendedProps.car || '-';
                    var status = arg.event.extendedProps.status; 
                    var start = arg.event.start.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'});
                    var end = arg.event.end ? arg.event.end.toLocaleTimeString('th-TH', {hour: '2-digit', minute:'2-digit'}) : '';
                    
                    // ตรวจสอบมุมมองปัจจุบัน
                    var isList = arg.view.type.includes('list');

                    if (isList) {
                        // --- มุมมองรายการ (List View) แบบใหม่: พื้นขาว อ่านง่าย ---
                        // สถานะสี (เขียว/เหลือง) สำหรับไอคอน
                        var statusColorClass = (status === 'approved') ? 'text-success' : 'text-warning';
                        
                        return { html: `
                            <div class="d-flex align-items-center w-100 py-1">
                                <div class="flex-grow-1">
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">${title}</div>
                                    <div class="small text-muted">
                                        <i class="fas fa-car me-1 ${statusColorClass}"></i> ${car}
                                    </div>
                                    <div class="small text-muted">
                                        <i class="far fa-clock me-1"></i> ${start} - ${end} น.
                                    </div>
                                </div>
                                <div class="ms-2">
                                    ${status === 'approved' 
                                        ? '<span class="badge bg-light text-success border border-success rounded-pill">อนุมัติ</span>' 
                                        : '<span class="badge bg-light text-warning border border-warning rounded-pill text-dark">รออนุมัติ</span>'}
                                </div>
                            </div>
                        ` };

                    } else {
                        // --- มุมมองตารางเดือน (Grid View) แบบเดิม: แถบสี ---
                        var textColor = (status === 'pending' ? 'text-dark' : 'text-white');
                        var iconColor = (status === 'pending' ? 'text-dark' : 'text-white'); 

                        return { html: `
                            <div class="p-1 overflow-hidden lh-1 ${textColor}">
                                <div class="fw-bold text-nowrap" style="font-size: 0.75rem;">
                                    <i class="far fa-clock me-1 ${iconColor}"></i>${start}-${end}
                                </div>
                                <div class="text-truncate small fw-bold" style="font-size: 0.75rem;">
                                    <i class="fas fa-car me-1 ${iconColor}"></i>${car}
                                </div>
                            </div>
                        ` };
                    }
                },
                
                eventClick: function(info) {
                    var props = info.event.extendedProps;
                    $('#modalUser').html(props.user + ' (' + (props.passengers||'-') + ' คน)');
                    document.getElementById('modalDest').innerText = info.event.title; 
                    document.getElementById('modalCar').innerText = props.car || '-';
                    var start = info.event.start.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' });
                    var end = info.event.end ? info.event.end.toLocaleString('th-TH', { dateStyle: 'medium', timeStyle: 'short' }) : '-';
                    document.getElementById('modalTime').innerText = start + ' - ' + end;
                    var statusHtml = (props.status == 'approved') ? '<span class="badge bg-success rounded-pill px-3 py-2">อนุมัติแล้ว</span>' : '<span class="badge bg-warning text-dark rounded-pill px-3 py-2">รออนุมัติ</span>';
                    document.getElementById('modalStatus').innerHTML = statusHtml;
                    new bootstrap.Modal(document.getElementById('eventModal')).show();
                }
            });
            calendar.render();
        }
    });
    </script>
</body>
</html>