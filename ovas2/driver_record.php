<?php
session_start();
require_once 'db_connect.php';

// 1. รับค่า ID จาก URL
$booking_id = isset($_GET['id']) ? $_GET['id'] : 0;

// 2. ดึงข้อมูลการจองปัจจุบัน
$sql = "SELECT b.*, u.fullname, v.brand, v.model, v.license_plate 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN vehicles v ON b.vehicle_id = v.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("<div style='padding:20px;text-align:center;font-family:sans-serif;'>🚫 ไม่พบข้อมูลการเดินทางนี้</div>");
}

$vehicle_id = $booking['vehicle_id'];

// 3. 🔥 แก้ไขตรงนี้: ดึงเลขไมล์สิ้นสุดของงานล่าสุดที่รถคันนี้วิ่งจบไปจริงๆ (ไม่ใช้ MAX แล้วเพื่อไม่ให้หลุดคิว)
$last_mileage = 0;
if (!empty($vehicle_id)) {
    $sql_last_mileage = "SELECT end_mileage FROM bookings 
                         WHERE vehicle_id = ? AND status = 'completed' 
                         ORDER BY id DESC LIMIT 1"; 
    $stmt_mile = $conn->prepare($sql_last_mileage);
    $stmt_mile->bind_param("i", $vehicle_id);
    $stmt_mile->execute();
    $res_mile = $stmt_mile->get_result()->fetch_assoc();
    
    if ($res_mile && $res_mile['end_mileage'] !== NULL) {
        $last_mileage = intval($res_mile['end_mileage']);
    }
    $stmt_mile->close();
}

// --- ส่วนบันทึกข้อมูล (Process Form) ---
$error_msg = "";
$success_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_mileage = intval($_POST['start_mileage']);
    $end_mileage = intval($_POST['end_mileage']);
    $trip_remark = !empty($_POST['trip_remark']) ? $_POST['trip_remark'] : NULL;
    $incident_remark = !empty($_POST['incident_remark']) ? trim($_POST['incident_remark']) : NULL;

    // 🔥 ตรวจสอบ Logic ฝั่ง PHP (Backend Protection) ล็อกประวัติและขากลับห้ามต่ำกว่าขาไป
    if ($start_mileage < $last_mileage) {
        $error_msg = "❌ บันทึกไม่สำเร็จ: เลขไมล์เริ่มต้น ต้องไม่น้อยกว่าเลขไมล์ประวัติล่าสุดในระบบ ($last_mileage กม.)";
    } elseif ($end_mileage < $start_mileage) {
        $error_msg = "❌ บันทึกไม่สำเร็จ: เลขไมล์สิ้นสุด ต้องไม่น้อยกว่าเลขไมล์เริ่มต้น";
    } else {
        // ผ่านเงื่อนไขทั้งหมด -> เริ่มอัปเดตข้อมูล
        $sql_update = "UPDATE bookings SET 
                        start_mileage = ?, 
                        end_mileage = ?, 
                        trip_remark = ?, 
                        incident_remark = ?, 
                        status = 'completed' 
                       WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("iissi", $start_mileage, $end_mileage, $trip_remark, $incident_remark, $booking_id);

        if ($stmt_update->execute()) {
            
            // ตรวจสอบสถานะรถ: ถ้าระบุเหตุไม่คาดฝัน = maintenance | ถ้าไม่มี = available
            if (!empty($vehicle_id)) {
                $car_status = (!empty($incident_remark)) ? 'maintenance' : 'available';
                
                $sql_car = "UPDATE vehicles SET status = ? WHERE id = ?";
                $stmt_car = $conn->prepare($sql_car);
                $stmt_car->bind_param("si", $car_status, $vehicle_id);
                $stmt_car->execute();
                $stmt_car->close();
            }

            if ($car_status == 'maintenance') {
                $success_msg = "🎉 บันทึกข้อมูลเรียบร้อย! ระบบได้ปรับสถานะรถคันนี้เป็น 'แจ้งซ่อม/บำรุง' อัตโนมัติเนื่องจากมีเหตุไม่คาดฝัน";
            } else {
                $success_msg = "🎉 บันทึกข้อมูลการเดินทางเสร็จสิ้น และคืนสถานะรถว่างเรียบร้อยแล้ว!";
            }
        } else {
            $error_msg = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการเดินทาง #<?php echo $booking_id; ?></title>
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
        <h5 class="fw-bold mb-0"><i class="fas fa-route me-2"></i>บันทึกการเดินทาง (พนักงานขับรถ)</h5>
        <small>Ref: BK-<?php echo str_pad($booking_id, 5, '0', STR_PAD_LEFT); ?></small>
    </div>

    <div class="container" style="max-width: 600px;">
        
        <?php if($success_msg): ?>
            <div class="card-custom p-4 text-center mt-5">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h4 class="fw-bold text-success">บันทึกสำเร็จ</h4>
                <p class="text-muted"><?php echo $success_msg; ?></p>
                <button onclick="window.close()" class="btn btn-secondary rounded-pill px-4 mt-2">ปิดหน้าต่าง</button>
            </div>
            <?php exit(); ?>
        <?php endif; ?>

        <div class="card-custom p-3 mb-3 mt-4">
            <div class="mb-2">
                <small class="text-muted d-block">ผู้ขอใช้รถ / ปลายทาง</small>
                <strong><?php echo $booking['fullname']; ?></strong> -> <span class="text-primary fw-bold"><?php echo $booking['destination']; ?></span>
            </div>
            <div class="small text-muted">
                <i class="fas fa-car me-1 text-secondary"></i> 
                <?php echo $booking['brand'].' '.$booking['model']; ?> (<?php echo $booking['license_plate']; ?>)
            </div>
            <div class="mt-2 text-end">
                <span class="badge bg-secondary p-2">📍 เลขไมล์ล่าสุดในระบบ: <?php echo number_format($last_mileage); ?> กม.</span>
            </div>
        </div>

        <?php if($error_msg): ?>
            <div class="alert alert-danger role='alert' border-0 shadow-sm mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <div class="card-custom p-4 mb-4">
            <form method="POST" onsubmit="return validateMileage()">
                
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label small text-muted fw-bold">เลขไมล์เริ่มต้น (กม.) <span class="text-danger">*</span></label>
                        <input type="number" id="start_mileage" name="start_mileage" data-last="<?php echo $last_mileage; ?>" class="form-control form-control-lg border-primary shadow-sm" placeholder="เช่น 120500" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small text-muted fw-bold">เลขไมล์สิ้นสุด (กม.) <span class="text-danger">*</span></label>
                        <input type="number" id="end_mileage" name="end_mileage" class="form-control form-control-lg border-primary shadow-sm" placeholder="เช่น 120650" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-muted fw-bold">หมายเหตุ / เรื่องทั่วไประหว่างทาง <span class="text-muted">(ไม่บังคับกรอก)</span></label>
                    <textarea name="trip_remark" class="form-control" rows="2" placeholder="เช่น รถติดมาก, คนจองพูดมาก..."></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label small text-danger fw-bold">⚠️ เหตุไม่คาดฝัน / ปัญหารถที่ยังไม่แก้ไข <span class="text-muted">(หากกรอกระบบจะปรับสถานะรถเป็นซ่อมบำรุงทันที)</span></label>
                    <textarea name="incident_remark" class="form-control border-danger shadow-sm" rows="2" placeholder="เช่น เครื่องยนต์ความร้อนขึ้น, ยางแตกยังไม่ได้เปลี่ยน (หากรถปกติให้ปล่อยว่างไว้)"></textarea>
                </div>

                <button type="submit" class="btn btn-success w-100 btn-lg rounded-pill shadow mt-2">
                    <i class="fas fa-save me-2"></i> บันทึกข้อมูล & จบงาน
                </button>
            </form>
        </div>

    </div>

    <script>
    function validateMileage() {
        var startInput = document.getElementById('start_mileage');
        var endInput = document.getElementById('end_mileage');
        
        var start = parseInt(startInput.value);
        var end = parseInt(endInput.value);
        var lastHistory = parseInt(startInput.getAttribute('data-last'));

        // 1. เช็คว่าเลขไมล์เริ่มรอบนี้ น้อยกว่าประวัติเก่าไหม
        if (start < lastHistory) {
            alert("❌ ผิดพลาด: เลขไมล์เริ่มต้น (" + start + ") ห้ามกรอกน้อยกว่าประวัติล่าสุดในระบบ (" + lastHistory + " กม.) เด็ดขาด!");
            startInput.focus();
            return false;
        }

        // 2. เช็คว่าขากลับ น้อยกว่าขาไปไหม
        if (end < start) {
            alert("❌ ผิดพลาด: เลขไมล์สิ้นสุดขากลับ (" + end + ") ห้ามกรอกน้อยกว่าเลขไมล์เริ่มต้น (" + start + ") เด็ดขาด!");
            endInput.focus();
            return false;
        }
        
        return true;
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>