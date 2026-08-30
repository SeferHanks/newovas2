<?php
session_start();
require_once 'db_connect.php';

// ตรวจสอบสิทธิ์ (Admin เท่านั้น)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// ---------------------------------------------------------------------------
// 1. ดึงข้อมูลรถที่ใช้บ่อย (Top Vehicles) 
// ---------------------------------------------------------------------------
function getTopVehicles($conn, $date_condition) {
    $sql = "SELECT v.brand, v.model, v.license_plate, v.image, COUNT(b.id) as trip_count 
            FROM bookings b
            JOIN vehicles v ON b.vehicle_id = v.id
            WHERE $date_condition
            AND b.status IN ('approved', 'completed') 
            GROUP BY b.vehicle_id
            ORDER BY trip_count DESC
            LIMIT 5";
    return $conn->query($sql);
}

$cars_3days = getTopVehicles($conn, "b.start_date >= DATE_SUB(NOW(), INTERVAL 3 DAY)");
$cars_week = getTopVehicles($conn, "YEARWEEK(b.start_date, 1) = YEARWEEK(CURDATE(), 1)");
$cars_month = getTopVehicles($conn, "MONTH(b.start_date) = MONTH(CURDATE()) AND YEAR(b.start_date) = YEAR(CURDATE())");
$cars_3months = getTopVehicles($conn, "b.start_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)");


// ---------------------------------------------------------------------------
// 2. ข้อมูลสรุปยอดรวม (Cards)
// ---------------------------------------------------------------------------

// A. อนุมัติ (Approved)
$sql_base_app = "FROM bookings WHERE status IN ('approved', 'completed') AND ";
$app_today = $conn->query("SELECT COUNT(*) as total $sql_base_app DATE(start_date) = CURDATE()")->fetch_assoc()['total'];
$app_week  = $conn->query("SELECT COUNT(*) as total $sql_base_app YEARWEEK(start_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['total'];
$app_month = $conn->query("SELECT COUNT(*) as total $sql_base_app MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())")->fetch_assoc()['total'];

// B. ไม่อนุมัติ (Rejected)
$sql_base_rej = "FROM bookings WHERE status = 'rejected' AND ";
$rej_today = $conn->query("SELECT COUNT(*) as total $sql_base_rej DATE(start_date) = CURDATE()")->fetch_assoc()['total'];
$rej_week  = $conn->query("SELECT COUNT(*) as total $sql_base_rej YEARWEEK(start_date, 1) = YEARWEEK(CURDATE(), 1)")->fetch_assoc()['total'];
$rej_month = $conn->query("SELECT COUNT(*) as total $sql_base_rej MONTH(start_date) = MONTH(CURDATE()) AND YEAR(start_date) = YEAR(CURDATE())")->fetch_assoc()['total'];


// --- ข้อมูลกราฟสถานะ ---
$sql_status = "SELECT status, COUNT(*) as count FROM bookings GROUP BY status";
$res_status = $conn->query($sql_status);
$status_data = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'completed' => 0];
while($row = $res_status->fetch_assoc()) {
    $status_data[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard สถิติ - OVAS Admin</title>
    <link rel="icon" type="image/png" href="uploads/logo.png">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-body: #f8f9fc; --primary-color: #1e3c72; }
        body { font-family: 'Sarabun', sans-serif; background-color: var(--bg-body); display: flex; flex-direction: column; min-height: 100vh; padding-top: 160px; }
        .main-content { flex: 1; padding-bottom: 150px; }
        footer { flex-shrink: 0; }

        /* Stat Card */
        .stat-card { background: #fff; border: none; border-radius: 15px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); transition: transform 0.3s; overflow: hidden; position: relative; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon { position: absolute; right: 20px; top: 20px; font-size: 3rem; opacity: 0.1; color: #1e3c72; transition: all 0.3s; }
        .stat-title { font-size: 0.9rem; color: #6c757d; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }
        .stat-value { font-size: 2.5rem; font-weight: 700; color: #1e3c72; transition: all 0.3s; }

        /* Switch Button Group */
        .btn-check:checked + .btn-outline-primary { background-color: #1e3c72; border-color: #1e3c72; color: #fff; }
        .btn-check:checked + .btn-outline-danger { background-color: #dc3545; border-color: #dc3545; color: #fff; }

        /* Chart & List Styling */
        .chart-card { background: #fff; border-radius: 15px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); padding: 25px; height: 100%; }
        .chart-title { font-weight: 700; color: #1e3c72; margin-bottom: 20px; border-left: 4px solid #1e3c72; padding-left: 10px; }
        .nav-pills .nav-link { color: #6c757d; border-radius: 50px; padding: 8px 20px; font-weight: 600; margin-right: 5px; cursor: pointer; }
        .nav-pills .nav-link:hover { background-color: #f0f0f0; }
        .nav-pills .nav-link.active { background-color: #1e3c72; color: #fff; box-shadow: 0 4px 10px rgba(30, 60, 114, 0.3); }
        .usage-item { display: flex; align-items: center; padding: 15px 0; border-bottom: 1px solid #f0f0f0; }
        .usage-item:last-child { border-bottom: none; }
        .car-thumb { width: 50px; height: 50px; border-radius: 10px; object-fit: cover; margin-right: 15px; background: #f8f9fa; }
        .usage-info h6 { margin: 0; font-weight: 700; color: #333; }
        .usage-info small { color: #888; }
        .usage-count { font-size: 1.5rem; font-weight: 800; color: #1e3c72; line-height: 1; }
        .usage-label { font-size: 0.75rem; color: #999; }
        .progress { height: 6px; background-color: #e9ecef; border-radius: 10px; margin-top: 5px; }
    </style>
</head>
<body>

    <?php include 'header.php'; ?>

    <div class="main-content">
        <div class="container">
            
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">สรุปสถิติการใช้งาน</h3>
                    <p class="text-muted mb-0">ภาพรวมการอนุมัติและปฏิเสธงาน</p>
                </div>
                
                <div class="btn-group shadow-sm rounded-pill" role="group">
                    <input type="radio" class="btn-check" name="statMode" id="modeApproved" autocomplete="off" checked onclick="toggleStats('approved')">
                    <label class="btn btn-outline-primary rounded-start-pill px-4" for="modeApproved">
                        <i class="fas fa-check-circle me-1"></i> อนุมัติ
                    </label>

                    <input type="radio" class="btn-check" name="statMode" id="modeRejected" autocomplete="off" onclick="toggleStats('rejected')">
                    <label class="btn btn-outline-danger rounded-end-pill px-4" for="modeRejected">
                        <i class="fas fa-times-circle me-1"></i> ไม่อนุมัติ
                    </label>
                </div>
            </div>

            <div class="row g-4 mb-4">
                
                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <div class="stat-title" id="title-today">วันนี้ (อนุมัติ)</div>
                        <div class="stat-value" id="val-today"><?php echo number_format($app_today); ?></div>
                        <i class="fas fa-check-circle stat-icon" id="icon-today"></i>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-primary" id="prog-today" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <div class="stat-title" id="title-week">สัปดาห์นี้ (อนุมัติ)</div>
                        <div class="stat-value text-success" id="val-week"><?php echo number_format($app_week); ?></div>
                        <i class="fas fa-check-circle stat-icon text-success" id="icon-week"></i>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-success" id="prog-week" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="stat-card p-4">
                        <div class="stat-title" id="title-month">เดือนนี้ (อนุมัติ)</div>
                        <div class="stat-value text-info" id="val-month"><?php echo number_format($app_month); ?></div>
                        <i class="fas fa-check-circle stat-icon text-info" id="icon-month"></i>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-info" id="prog-month" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="chart-card">
                        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                            <h5 class="chart-title mb-0">อันดับรถใช้งานสูงสุด (อนุมัติแล้ว)</h5>
                            <ul class="nav nav-pills" id="pills-tab" role="tablist">
                                <li class="nav-item"><button class="nav-link active" id="pills-3days-tab" data-bs-toggle="pill" data-bs-target="#pills-3days" type="button">3 วัน</button></li>
                                <li class="nav-item"><button class="nav-link" id="pills-week-tab" data-bs-toggle="pill" data-bs-target="#pills-week" type="button">สัปดาห์นี้</button></li>
                                <li class="nav-item"><button class="nav-link" id="pills-month-tab" data-bs-toggle="pill" data-bs-target="#pills-month" type="button">เดือนนี้</button></li>
                                <li class="nav-item"><button class="nav-link" id="pills-3months-tab" data-bs-toggle="pill" data-bs-target="#pills-3months" type="button">3 เดือน</button></li>
                            </ul>
                        </div>

                        <div class="tab-content" id="pills-tabContent">
                            <div class="tab-pane fade show active" id="pills-3days">
                                <?php if($cars_3days->num_rows > 0): ?>
                                    <?php while($car = $cars_3days->fetch_assoc()): 
                                        $percent = ($car['trip_count'] / 10) * 100;
                                        $img = (!empty($car['image'])) ? 'uploads/'.$car['image'] : 'https://via.placeholder.com/50?text=Car';
                                    ?>
                                    <div class="usage-item">
                                        <img src="<?php echo $img; ?>" class="car-thumb">
                                        <div class="flex-grow-1">
                                            <h6><?php echo $car['brand'].' '.$car['model']; ?></h6>
                                            <small><?php echo $car['license_plate']; ?></small>
                                            <div class="progress">
                                                <div class="progress-bar bg-warning" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-end ps-3">
                                            <div class="usage-count text-warning"><?php echo $car['trip_count']; ?></div>
                                            <div class="usage-label">เที่ยว</div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted py-4">ไม่มีรถที่ถูกใช้งานใน 3 วันที่ผ่านมา</p>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="pills-week">
                                <?php if($cars_week->num_rows > 0): ?>
                                    <?php while($car = $cars_week->fetch_assoc()): 
                                        $percent = ($car['trip_count'] / 15) * 100;
                                        $img = (!empty($car['image'])) ? 'uploads/'.$car['image'] : 'https://via.placeholder.com/50?text=Car';
                                    ?>
                                    <div class="usage-item">
                                        <img src="<?php echo $img; ?>" class="car-thumb">
                                        <div class="flex-grow-1">
                                            <h6><?php echo $car['brand'].' '.$car['model']; ?></h6>
                                            <small><?php echo $car['license_plate']; ?></small>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-end ps-3">
                                            <div class="usage-count text-success"><?php echo $car['trip_count']; ?></div>
                                            <div class="usage-label">เที่ยว</div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted py-4">ไม่มีรถที่ถูกใช้งานในสัปดาห์นี้</p>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="pills-month">
                                <?php if($cars_month->num_rows > 0): ?>
                                    <?php while($car = $cars_month->fetch_assoc()): 
                                        $percent = ($car['trip_count'] / 30) * 100;
                                        $img = (!empty($car['image'])) ? 'uploads/'.$car['image'] : 'https://via.placeholder.com/50?text=Car';
                                    ?>
                                    <div class="usage-item">
                                        <img src="<?php echo $img; ?>" class="car-thumb">
                                        <div class="flex-grow-1">
                                            <h6><?php echo $car['brand'].' '.$car['model']; ?></h6>
                                            <small><?php echo $car['license_plate']; ?></small>
                                            <div class="progress">
                                                <div class="progress-bar bg-primary" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-end ps-3">
                                            <div class="usage-count text-primary"><?php echo $car['trip_count']; ?></div>
                                            <div class="usage-label">เที่ยว</div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted py-4">ไม่มีรถที่ถูกใช้งานในเดือนนี้</p>
                                <?php endif; ?>
                            </div>

                            <div class="tab-pane fade" id="pills-3months">
                                <?php if($cars_3months->num_rows > 0): ?>
                                    <?php while($car = $cars_3months->fetch_assoc()): 
                                        $percent = ($car['trip_count'] / 50) * 100;
                                        $img = (!empty($car['image'])) ? 'uploads/'.$car['image'] : 'https://via.placeholder.com/50?text=Car';
                                    ?>
                                    <div class="usage-item">
                                        <img src="<?php echo $img; ?>" class="car-thumb">
                                        <div class="flex-grow-1">
                                            <h6><?php echo $car['brand'].' '.$car['model']; ?></h6>
                                            <small><?php echo $car['license_plate']; ?></small>
                                            <div class="progress">
                                                <div class="progress-bar bg-danger" style="width: <?php echo $percent; ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="text-end ps-3">
                                            <div class="usage-count text-danger"><?php echo $car['trip_count']; ?></div>
                                            <div class="usage-label">เที่ยว</div>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted py-4">ไม่มีข้อมูลใน 3 เดือนที่ผ่านมา</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="chart-card">
                        <h5 class="chart-title">สถานะการจองรวม</h5>
                        <div style="height: 250px; display:flex; justify-content:center;">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="mt-4 text-center">
                            <div class="badge bg-light text-dark border p-2 mb-2">
                                คำขอทั้งหมด: <?php echo array_sum($status_data); ?> รายการ
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
    const stats = {
        approved: {
            today: <?php echo $app_today; ?>,
            week:  <?php echo $app_week; ?>,
            month: <?php echo $app_month; ?>
        },
        rejected: {
            today: <?php echo $rej_today; ?>,
            week:  <?php echo $rej_week; ?>,
            month: <?php echo $rej_month; ?>
        }
    };

    function toggleStats(mode) {
        if(mode === 'approved') {
            $('#title-today').text('วันนี้ (อนุมัติ)');
            $('#title-week').text('สัปดาห์นี้ (อนุมัติ)');
            $('#title-month').text('เดือนนี้ (อนุมัติ)');
            
            animateValue('#val-today', stats.approved.today);
            animateValue('#val-week', stats.approved.week);
            animateValue('#val-month', stats.approved.month);

            updateCardStyle('approved');
        } else {
            $('#title-today').text('วันนี้ (ไม่อนุมัติ)');
            $('#title-week').text('สัปดาห์นี้ (ไม่อนุมัติ)');
            $('#title-month').text('เดือนนี้ (ไม่อนุมัติ)');

            animateValue('#val-today', stats.rejected.today);
            animateValue('#val-week', stats.rejected.week);
            animateValue('#val-month', stats.rejected.month);

            updateCardStyle('rejected');
        }
    }

    function updateCardStyle(mode) {
        if (mode === 'approved') {
            // ไอคอน (สีเดิม)
            $('#icon-today').attr('class', 'fas fa-check-circle stat-icon');
            $('#icon-week').attr('class', 'fas fa-check-circle stat-icon text-success');
            $('#icon-month').attr('class', 'fas fa-check-circle stat-icon text-info');
            // ตัวเลข (สีเดิม)
            $('#val-today').attr('class', 'stat-value');
            $('#val-week').attr('class', 'stat-value text-success');
            $('#val-month').attr('class', 'stat-value text-info');
            
            // Progress Bar (สีเดิม)
            $('#prog-today').attr('class', 'progress-bar bg-primary');
            $('#prog-week').attr('class', 'progress-bar bg-success');
            $('#prog-month').attr('class', 'progress-bar bg-info');
        } else {
            // ไอคอน (สีแดง)
            $('.stat-icon').attr('class', 'fas fa-times-circle stat-icon text-danger');
            // ตัวเลข (สีแดง)
            $('.stat-value').attr('class', 'stat-value text-danger');
            
            // Progress Bar (เฉพาะ 3 อันบนเป็นสีแดง)
            $('#prog-today, #prog-week, #prog-month').attr('class', 'progress-bar bg-danger');
        }
    }

    function animateValue(id, value) {
        $(id).prop('Counter', 0).animate({
            Counter: value
        }, {
            duration: 500,
            easing: 'swing',
            step: function (now) {
                $(this).text(Math.ceil(now).toLocaleString());
            },
            complete: function() {
                $(this).text(value.toLocaleString());
            }
        });
    }

    // กราฟวงกลม
    const ctxStatus = document.getElementById('statusChart').getContext('2d');
    new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: ['รออนุมัติ', 'อนุมัติแล้ว', 'ไม่อนุมัติ', 'จบงาน'],
            datasets: [{
                data: [
                    <?php echo $status_data['pending']; ?>,
                    <?php echo $status_data['approved']; ?>,
                    <?php echo $status_data['rejected']; ?>,
                    <?php echo $status_data['completed'] ?? 0; ?>
                ],
                backgroundColor: ['#ffc107', '#198754', '#dc3545', '#0d6efd'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
            }
        }
    });
    </script>

</body>
</html>