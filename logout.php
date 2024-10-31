<?php
session_start(); // เริ่มต้น session

// ลบข้อมูล session ทั้งหมด
session_unset();

// ทำลาย session
session_destroy();

// เปลี่ยนเส้นทางผู้ใช้ไปยังหน้า login หลังจากออกจากระบบ
header('Location: ../auth/login.php'); // ตรวจสอบเส้นทางนี้
exit();
