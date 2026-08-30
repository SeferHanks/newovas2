<?php
session_start();
require_once 'db_connect.php';
require_once 'line_helper.php'; // เรียกใช้ไฟล์ส่งไลน์

// 1. รับค่า ID จาก URL
$booking_id = isset($_GET['id']) ? $_GET['id'] : 0;

// 2. ดึงข้อมูลการจอง (Booking Data)
$sql = "SELECT b.*, u.fullname, u.phone, u.email, v.brand, v.model, v.license_plate 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("<div style='padding:20px;text-align:center;font-family:sans-serif;'>🚫 ไม่พบข้อมูลการจอง หรือรายการนี้ถูกยกเลิกไปแล้ว</div>");
}

// 3. ดึงรายชื่อคนขับ (สำหรับ Dropdown)
$drivers = $conn->query("SELECT * FROM drivers WHERE status = 'active'");

// --- ส่วนบันทึกข้อมูล (Process Form) ---
$success_msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // ==================================================================================
    // 1. กรณีอนุมัติ (Approve)
    // ==================================================================================
    if ($_POST['action'] == 'approve') {
        $driver_id = $_POST['driver_id'];
        
        // อัปเดตสถานะการจอง
        $update = $conn->prepare("UPDATE bookings SET status = 'approved', driver_id = ?, reject_reason = NULL WHERE id = ?");
        $update->bind_param("ii", $driver_id, $booking_id);
        
        if($update->execute()){
            
            // 🔥 [เพิ่มใหม่ตรงนี้] อัปเดตสถานะรถในตาราง vehicles ให้เป็น 'busy' ทันที
            $vehicle_id = $booking['vehicle_id'];
            if (!empty($vehicle_id)) {
                $sql_update_car = "UPDATE vehicles SET status = 'busy' WHERE id = ?";
                $stmt_update_car = $conn->prepare($sql_update_car);
                $stmt_update_car->bind_param("i", $vehicle_id);
                $stmt_update_car->execute();
                $stmt_update_car->close();
            }
            
            // 1. ดึงข้อมูลคนขับ
            $sql_d = "SELECT name, phone, line_user_id FROM drivers WHERE id = ?";
            $stmt_d = $conn->prepare($sql_d);
            $stmt_d->bind_param("i", $driver_id);
            $stmt_d->execute();
            $driver_res = $stmt_d->get_result()->fetch_assoc();
            
            $driverLineId = isset($driver_res['line_user_id']) ? trim($driver_res['line_user_id']) : "";

            // 2. เตรียมข้อมูลงาน
            $jobData = [
                'id' => $booking_id,
                'destination' => $booking['destination'],
                'date_range' => date('d/m H:i', strtotime($booking['start_date'])) . ' - ' . date('d/m H:i', strtotime($booking['end_date'])),
                'user_name' => $booking['fullname'],
                'user_phone' => $booking['phone'],
                'car_info' => $booking['brand'] . " " . $booking['model'] . " (" . $booking['license_plate'] . ")",
                'remark' => $booking['purpose'],
                'driver_info' => $driver_res['name'] . " (" . $driver_res['phone'] . ")",
                'passengers' => $booking['passengers']
            ];

            // 3. แจ้งเตือนคนขับ (ส่วนตัว)
            if (!empty($driverLineId)) {
                sendLineToDriver($driverLineId, $jobData);
            }

            // 4. แจ้งเตือนเข้า "กลุ่มไลน์" (สีเขียว)
            sendApproveFlexToGroup($jobData);
            
            $success_msg = "✅ อนุมัติเรียบร้อย! แจ้งคนขับและกลุ่มไลน์แล้ว และล็อกสถานะรถไม่ว่างแล้ว";
        }

    // ==================================================================================
    // 2. กรณีปฏิเสธ (Reject)
    // ==================================================================================
    } elseif ($_POST['action'] == 'reject') {
        $reason = $_POST['reject_reason'];
        
        $update = $conn->prepare("UPDATE bookings SET status = 'rejected', driver_id = NULL, reject_reason = ? WHERE id = ?");
        $update->bind_param("si", $reason, $booking_id);
        
        if($update->execute()){
            
            // --- [เพิ่มใหม่] ส่งแจ้งเตือนไม่อนุมัติเข้ากลุ่ม ---
            $rejectData = [
                'id' => $booking_id,
                'user_name' => $booking['fullname'],
                'destination' => $booking['destination'],
                'reason' => $reason,
                'date_range' => date('d/m H:i', strtotime($booking['start_date'])) . ' - ' . date('d/m H:i', strtotime($booking['end_date'])),
                'car_info' => $booking['brand'] . " " . $booking['model'] . " (" . $booking['license_plate'] . ")"
            ];
            
            sendRejectFlexToGroup($rejectData);
            
            $success_msg = "❌ ปฏิเสธคำขอเรียบร้อยแล้ว";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง #<?php echo $booking_id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Sarabun', sans-serif; }
        .card-custom { border-radius: 16px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.05); background: white; }
        .header-bg { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 20px; border-radius: 0 0 20px 20px; margin-bottom: -40px; padding-bottom: 50px; }
    </style>
</head>
<body>

    <div class="header-bg shadow-sm text-center">
        <h5 class="fw-bold mb-0">จัดการคำขอใช้รถ</h5>
        <small>Ref: BK-<?php echo str_pad($booking_id, 5, '0', STR_PAD_LEFT); ?></small>
    </div>

    <div class="container" style="max-width: 600px;">
        
        <?php if($success_msg): ?>
            <div class="card-custom p-4 text-center mt-5">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h4 class="fw-bold text-success">เสร็จสมบูรณ์</h4>
                <p class="text-muted"><?php echo $success_msg; ?></p>
                <button onclick="window.close()" class="btn btn-secondary rounded-pill px-4 mt-2">ปิดหน้าต่าง</button>
            </div>
            <?php exit(); ?>
        <?php endif; ?>

        <div class="card-custom p-3 mb-3 mt-4">
            <div class="d-flex align-items-start mb-3 border-bottom pb-3">
                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3 flex-shrink-0" style="width:50px; height:50px;">
                    <i class="fas fa-user text-primary fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold mb-1"><?php echo $booking['fullname']; ?></h6>
                    <div class="small text-muted mb-1">
                        <i class="fas fa-phone-alt me-2 text-secondary" style="width:15px;"></i> 
                        <a href="tel:<?php echo $booking['phone']; ?>" class="text-decoration-none text-muted"><?php echo $booking['phone']; ?></a>
                    </div>
                    <div class="small text-muted">
                        <i class="fas fa-envelope me-2 text-secondary" style="width:15px;"></i> 
                        <?php echo $booking['email']; ?>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="small text-muted fw-bold">ไปที่:</label>
                <div class="fw-bold text-dark fs-5"><?php echo $booking['destination']; ?></div>
            </div>
            
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div class="bg-light p-2 rounded border h-100">
                        <small class="text-muted d-block">ไป</small>
                        <strong><?php echo date('d/m H:i', strtotime($booking['start_date'])); ?></strong>
                    </div>
                </div>
                <div class="col-6">
                    <div class="bg-light p-2 rounded border h-100">
                        <small class="text-muted d-block">กลับ</small>
                        <strong><?php echo date('d/m H:i', strtotime($booking['end_date'])); ?></strong>
                    </div>
                </div>
            </div>

            <div class="small text-muted mb-2">
                <i class="fas fa-car me-1 text-primary"></i> 
                <?php echo $booking['brand'].' '.$booking['model']; ?> 
                <span class="badge bg-secondary ms-1"><?php echo $booking['license_plate']; ?></span>
            </div>

            <div class="small text-muted mb-2">
                <i class="fas fa-users me-1 text-primary"></i> 
                จำนวนผู้โดยสาร: <strong><?php echo $booking['passengers']; ?> ท่าน</strong>
            </div>

            <div class="small text-muted mt-2 border-top pt-2">
                <i class="fas fa-comment-dots me-1 text-primary"></i> 
                หมายเหตุ: <?php echo $booking['purpose']; ?>
            </div>
        </div>

        <?php if($booking['status'] == 'pending'): ?>
        <div class="card-custom p-4">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                
                <div class="mb-3">
                    <label class="form-label fw-bold text-primary">เลือกพนักงานขับรถ <span class="text-danger">*</span></label>
                    <select name="driver_id" class="form-select form-select-lg shadow-sm border-primary" required>
                        <option value="" selected disabled>-- เลือกคนขับ --</option>
                        <?php 
                        if($drivers->num_rows > 0) {
                            while($d = $drivers->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $d['id']; ?>">
                                <?php echo $d['name']; ?> (<?php echo $d['phone']; ?>)
                            </option>
                        <?php 
                            endwhile; 
                        } else {
                            echo "<option disabled>ไม่พบข้อมูลคนขับ</option>";
                        }
                        ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-success w-100 btn-lg rounded-pill shadow mb-3">
                    <i class="fas fa-check-circle me-2"></i> อนุมัติ & มอบหมายงาน
                </button>
            </form>

            <hr>

            <button class="btn btn-outline-danger w-100 rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#rejectArea">
                <i class="fas fa-times me-2"></i> ไม่อนุมัติรายการนี้
            </button>

            <div class="collapse mt-3" id="rejectArea">
                <form method="POST">
                    <input type="hidden" name="action" value="reject">
                    <textarea name="reject_reason" class="form-control mb-2" placeholder="ระบุเหตุผลที่ไม่อนุมัติ..." required rows="2"></textarea>
                    <button type="submit" class="btn btn-danger w-100 rounded-pill">ยืนยันการปฏิเสธ</button>
                </form>
            </div>
        </div>
        
        <?php else: ?>
            <div class="alert alert-warning text-center rounded-4 shadow-sm py-4">
                <i class="fas fa-info-circle fa-2x mb-2 text-warning"></i><br>
                รายการนี้ดำเนินการไปแล้ว<br>
                สถานะ: <strong><?php echo strtoupper($booking['status']); ?></strong>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>