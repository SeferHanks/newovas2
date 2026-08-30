<?php
session_start();
// 1. ตั้งค่า TimeZone ให้เป็นไทย (แก้เรื่องเวลาเพี้ยน)
date_default_timezone_set('Asia/Bangkok'); 

require_once 'db_connect.php';
$conn->query("SET time_zone = '+07:00'");

// ตรวจสอบ Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("ไม่พบรหัสการจอง");
}

$booking_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// ดึงข้อมูล (b.* จะดึง created_at มาด้วยถ้ามีในตาราง)
$sql = "SELECT b.*, u.fullname, u.email, u.phone, u.role, v.brand, v.model, v.license_plate, d.name as driver_name, d.phone as driver_phone
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN vehicles v ON b.vehicle_id = v.id
        LEFT JOIN drivers d ON b.driver_id = d.id
        WHERE b.id = ?";

// ถ้าไม่ใช่ Admin ให้ดูได้เฉพาะของตัวเอง
if ($_SESSION['role'] != 'admin') {
    $sql .= " AND b.user_id = $user_id";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("ไม่พบข้อมูล หรือคุณไม่มีสิทธิ์เข้าถึงรายการนี้");
}

$row = $result->fetch_assoc();

function DateThai($strDate) {
    if(!$strDate) return "-";
    $strYear = date("Y",strtotime($strDate))+543;
    $strMonth= date("n",strtotime($strDate));
    $strDay= date("j",strtotime($strDate));
    $strHour= date("H",strtotime($strDate));
    $strMinute= date("i",strtotime($strDate));
    $strMonthCut = Array("","ม.ค.","ก.พ.","มี.ค.","เม.ย.","พ.ค.","มิ.ย.","ก.ค.","ส.ค.","ก.ย.","ต.ค.","พ.ย.","ธ.ค.");
    $strMonthThai=$strMonthCut[$strMonth];
    return "$strDay $strMonthThai $strYear ($strHour:$strMinute)";
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบยืนยันการจอง - OVAS</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: #525659;
            font-family: 'Sarabun', sans-serif;
        }
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm; /* A4 */
            padding: 15mm 20mm;
            margin: 10mm auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            position: relative;
        }
        
        /* --- สีธีมหลัก (บังคับให้พิมพ์ออกสี) --- */
        .theme-color {
            color: #1e3c72 !important;
            -webkit-print-color-adjust: exact;
        }
        
        .doc-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #1e3c72;
            padding-bottom: 10px;
        }
        .doc-logo {
            font-size: 40px;
            color: #1e3c72 !important; /* บังคับสีโลโก้ */
        }

        .table-custom th {
            background-color: #f8f9fa !important;
            width: 30%;
            vertical-align: middle;
            padding: 8px 10px;
            -webkit-print-color-adjust: exact; /* บังคับสีพื้นหลังตาราง */
        }
        .table-custom td {
            padding: 8px 10px;
        }

        .section-title {
            background-color: #1e3c72 !important; /* บังคับสีพื้นหลังหัวข้อ */
            color: white !important; /* บังคับสีตัวอักษรขาว */
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 1rem;
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact;
        }

        /* --- ตั้งค่าสำหรับการพิมพ์ (สำคัญมาก) --- */
        @media print {
            @page {
                size: A4;
                margin: 0;
            }
            body { 
                background: white; 
                margin: 0;
                -webkit-print-color-adjust: exact !important; /* คำสั่งบังคับพิมพ์สี Chrome/Edge */
                print-color-adjust: exact !important; /* คำสั่งบังคับพิมพ์สี Firefox */
            }
            .page { 
                box-shadow: none; 
                margin: 0; 
                width: 100%; 
                min-height: auto; 
                padding: 10mm 15mm; 
                border: none;
            }
            .no-print { display: none !important; }
            
            /* บังคับสีตัวอักษรเฉพาะจุดอีกครั้งกันพลาด */
            .text-primary { color: #0d6efd !important; }
            .border-success { border-color: #198754 !important; }
            .text-success { color: #198754 !important; }
            .border-danger { border-color: #dc3545 !important; }
            .text-danger { color: #dc3545 !important; }
            .bg-light { background-color: #f8f9fa !important; }
        }
    </style>
</head>
<body>

    <div class="no-print text-center py-3 fixed-top bg-dark text-white shadow-sm">
        <span class="me-3">เอกสารยืนยันการจอง #<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></span>
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4"><i class="fas fa-print me-2"></i>พิมพ์ / บันทึกเป็น PDF</button>
        <button onclick="window.close()" class="btn btn-outline-light rounded-pill px-4 ms-2">ปิดหน้าต่าง</button>
    </div>

    <div style="height: 60px;" class="no-print"></div>

    <div class="page">
        
        <div class="doc-header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-start">
                    <div class="doc-logo">
                        <img src="uploads/logo.png" alt="Logo" style="height: 100px; width: auto;">
                    </div>
                    <div class="fw-bold fs-5 theme-color">OVAS Booking System</div>
                </div>
                <div class="text-end">
                    <h3 class="fw-bold mb-0">ใบยืนยันการจอง</h3>
                    <h6 class="text-muted mb-1">Booking Confirmation</h6>
                    <div class="badge bg-light text-dark border px-3 py-1">
                        Ref: BK-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end mb-3 text-muted small">
            พิมพ์เมื่อ: <?php echo DateThai(date('Y-m-d H:i:s')); ?>
        </div>

        <div class="mb-3">
            <div class="section-title"><i class="fas fa-user me-2"></i>1. ข้อมูลผู้ขอใช้รถ</div>
            <div class="p-2 px-3 border rounded">
                <div class="row g-2">
                    <div class="col-6">
                        <small class="text-muted d-block">ชื่อ-นามสกุล:</small>
                        <span class="fw-bold"><?php echo $row['fullname']; ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">ตำแหน่ง:</small>
                        <span class="fw-bold"><?php echo ucfirst($row['role']); ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">อีเมล:</small>
                        <span class="fw-bold"><?php echo $row['email']; ?></span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted d-block">เบอร์โทรศัพท์:</small>
                        <span class="fw-bold"><?php echo isset($row['phone']) ? $row['phone'] : '-'; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <div class="section-title"><i class="fas fa-map-marker-alt me-2"></i>2. รายละเอียดการเดินทาง</div>
            <table class="table table-bordered table-custom mb-0 text-nowrap">
                <tr>
                    <th>ทำรายการจองเมื่อ</th>
                    <td class="text-primary fw-bold">
                        <?php 
                        // ตรวจสอบชื่อคอลัมน์ใน DB: created_at หรือ booking_date หรือ timestamp
                        // ถ้าใน DB ชื่อ 'booking_date' ให้แก้เป็น $row['booking_date']
                        echo isset($row['created_at']) ? DateThai($row['created_at']) : "-"; 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>สถานที่ไป</th>
                    <td class="fw-bold theme-color text-wrap"><?php echo $row['destination']; ?></td>
                </tr>
                <tr>
                    <th>วัตถุประสงค์</th>
                    <td class="text-wrap"><?php echo $row['purpose']; ?></td>
                </tr>
                <tr>
                    <th>วัน-เวลา เริ่ม</th>
                    <td><?php echo DateThai($row['start_date']); ?></td>
                </tr>
                <tr>
                    <th>วัน-เวลา สิ้นสุด</th>
                    <td><?php echo DateThai($row['end_date']); ?></td>
                </tr>
                <tr>
                    <th>ผู้โดยสาร</th>
                    <td><?php echo $row['passengers']; ?> ท่าน</td>
                </tr>
            </table>
        </div>

        <div class="mb-4">
            <div class="section-title"><i class="fas fa-car me-2"></i>3. ยานพาหนะและพนักงานขับรถ</div>
            <div class="p-3 border rounded bg-light" style="-webkit-print-color-adjust: exact;">
                <div class="row">
                    <div class="col-6">
                        <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">ยานพาหนะ</h6>
                        <div class="mb-1"><span class="text-muted me-2">ยี่ห้อ/รุ่น:</span> <strong><?php echo $row['brand'] . ' ' . $row['model']; ?></strong></div>
                        <div><span class="text-muted me-2">ทะเบียน:</span> <span class="badge bg-white text-dark border"><?php echo $row['license_plate']; ?></span></div>
                    </div>
                    <div class="col-6 border-start border-secondary">
                        <h6 class="fw-bold text-dark border-bottom pb-1 mb-2">พนักงานขับรถ</h6>
                        <?php if($row['driver_name']): ?>
                            <div class="mb-1"><span class="text-muted me-2">ชื่อ:</span> <strong><?php echo $row['driver_name']; ?></strong></div>
                            <div><span class="text-muted me-2">เบอร์โทร:</span> <strong><?php echo $row['driver_phone']; ?></strong></div>
                        <?php else: ?>
                            <p class="text-muted small fst-italic">- ยังไม่ระบุ -</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($row['status'] == 'approved'): ?>
            
            <div class="alert alert-success text-center fw-bold border border-success py-3" style="margin-top: 20px; -webkit-print-color-adjust: exact;">
                <i class="fas fa-check-circle fa-2x mb-1 d-block text-success"></i>
                <span class="fs-5">ได้รับการอนุมัติเรียบร้อยแล้ว (APPROVED)</span>
                <div class="small fw-normal mt-1 text-muted">เอกสารนี้ใช้เป็นหลักฐานยืนยันการจองยานพาหนะในระบบ OVAS'2</div>
                <div class="small fw-normal mt-1 text-muted">Real-time Official Vehicle Allocation System for Educational
Institutions via Application.</div>
            </div>

        <?php elseif ($row['status'] == 'rejected'): ?>
            
            <div class="alert alert-danger text-center fw-bold border border-danger py-3" style="margin-top: 20px; -webkit-print-color-adjust: exact;">
                <i class="fas fa-times-circle fa-2x mb-1 d-block text-danger"></i>
                <span class="fs-5">คำขอถูกปฏิเสธ (REJECTED)</span>
                <div class="mt-2 text-danger text-start px-4" style="background: rgba(220, 53, 69, 0.05); padding: 10px; border-radius: 8px;">
                    <i class="fas fa-exclamation-triangle me-1"></i> <strong>เหตุผลการปฏิเสธ:</strong><br>
                    <?php echo empty($row['reject_reason']) ? "-" : htmlspecialchars($row['reject_reason']); ?>
                </div>
                <div class="small fw-normal mt-2 text-muted">รายการนี้ไม่ได้รับอนุมัติให้ใช้งานยานพาหนะ</div>
                <div class="small fw-normal mt-2 text-muted">Real-time Official Vehicle Allocation System for Educational
Institutions via Application.</div>
            </div>

        <?php else: ?>

            <div class="alert alert-warning text-center fw-bold border border-warning py-3" style="margin-top: 20px; -webkit-print-color-adjust: exact;">
                <i class="fas fa-clock fa-2x mb-1 d-block text-warning"></i>
                <span class="fs-5 text-dark">อยู่ระหว่างรอการอนุมัติ (PENDING)</span>
                <div class="small fw-normal mt-1 text-muted">กรุณารอเจ้าหน้าที่ดำเนินการตรวจสอบและอนุมัติ</div>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>