</div> <footer class="text-white pt-5 pb-4 mt-auto" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); box-shadow: 0 -5px 20px rgba(0,0,0,0.1);">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                <h5 class="text-uppercase fw-bold mb-3" style="letter-spacing: 1px;">
                    <i class="fas fa-shuttle-van me-2"></i>OVAS Booking
                </h5>
                <p class="small mb-4" style="color: rgba(255,255,255,0.8);">
                    ระบบจองยานพาหนะออนไลน์สำหรับบุคลากรภายใน ช่วยให้การจัดการคิวรถเป็นเรื่องง่าย สะดวก และตรวจสอบได้ตลอด 24 ชั่วโมง
                </p>
                <div class="d-flex">
                    <a href="#" class="me-3 text-white social-icon"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="me-3 text-white social-icon"><i class="fab fa-line fa-lg"></i></a>
                    <a href="#" class="text-white social-icon"><i class="fas fa-envelope fa-lg"></i></a>
                </div>
            </div>

            <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                <h6 class="text-uppercase fw-bold mb-3">เมนูลัด</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="index.php" class="footer-link">หน้าหลัก</a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if ($_SESSION['role'] == 'teacher'): ?>
                            <li class="mb-2"><a href="my_history.php" class="footer-link">ประวัติการจอง</a></li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li class="mb-2"><a href="admin_approve.php" class="footer-link">อนุมัติคำขอ</a></li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="mb-2"><a href="login.php" class="footer-link">เข้าสู่ระบบ</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 mb-4 mb-lg-0">
                <h6 class="text-uppercase fw-bold mb-3">ช่วยเหลือ</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="#" class="footer-link">คู่มือการใช้งาน</a></li>
                    <li class="mb-2"><a href="#" class="footer-link">แจ้งปัญหา</a></li>
                    <li class="mb-2"><a href="#" class="footer-link">ติดต่อผู้ดูแล</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-6">
                <h6 class="text-uppercase fw-bold mb-3">ติดต่อเรา</h6>
                <ul class="list-unstyled small" style="color: rgba(255,255,255,0.8);">
                    <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 อาคารเรียนรวม ชั้น 1 ห้องธุรการ</li>
                    <li class="mb-2"><i class="fas fa-phone me-2"></i> 02-123-4567 ต่อ 101</li>
                    <li class="mb-2"><i class="fas fa-clock me-2"></i> จันทร์ - ศุกร์: 08:30 - 16:30 น.</li>
                </ul>
            </div>
        </div>

        <hr class="my-4" style="border-color: rgba(255,255,255,0.3);">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start">
                <p class="small mb-0" style="color: rgba(255,255,255,0.7);">
                    &copy; <?php echo date("Y"); ?> <strong>OVAS Booking System</strong>. All rights reserved.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                <p class="small mb-0" style="color: rgba(255,255,255,0.7);">
                    Developed by Pleng <span class="text-danger">♥</span>
                </p>
            </div>
        </div>
    </div>

    <style>
        .footer-link {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        .footer-link:hover {
            color: #ffffff;
            padding-left: 5px;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        .social-icon {
            transition: transform 0.3s ease;
        }
        .social-icon:hover {
            transform: translateY(-3px);
            color: #ffc107 !important; /* เปลี่ยนเป็นสีเหลืองทองเมื่อชี้ */
        }
    </style>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>