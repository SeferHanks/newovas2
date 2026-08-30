<?php session_start(); ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองรถราชการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h3>แบบฟอร์มขอใช้รถ</h3>
        <form action="process_booking.php" method="POST">
            <div class="mb-3">
                <label>สถานที่ไป:</label>
                <input type="text" name="destination" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>วัตถุประสงค์:</label>
                <textarea name="purpose" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label>วันเวลาเดินทางไป:</label>
                <input type="datetime-local" name="start_date" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>วันเวลาเดินทางกลับ:</label>
                <input type="datetime-local" name="end_date" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">ส่งคำขอจอง</button>
        </form>
    </div>
</body>
</html>