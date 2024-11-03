<?php
session_start();
include('../../config/db.php');

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "SELECT u.name, u.surname, u.role, u.store_id, s.store_name 
          FROM users u
          LEFT JOIN stores s ON u.store_id = s.store_id 
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $name = $user['name'];
    $surname = $user['surname'];
    $role = $user['role'];
} else {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        /* สไตล์สำหรับ Sidebar */
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background-color: #f5f5f5;
            color: #2c3e50;
            font-family: Arial, sans-serif;
        }

        #banner {
            background-color: #ffffff;
            border-bottom: 2px solid #2c3e50;
            padding: 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            height: 60px;
        }

        #user-info {
            margin-left: auto;
            color: black;
            font-size: 14px;
        }

        #sidebar {
        transform: translateX(-250px); /* ซ่อน Sidebar ออกไปทางซ้าย */
        transition: all 0.3s ease;
        width: 250px;
    }

        #sidebar.active {
        transform: translateX(0); /* แสดง Sidebar */
    }

        #sidebar a {
            color: #ffffff;
            text-decoration: none;
            padding: 15px;
            display: block;
            transition: background-color 0.3s;
        }

        #sidebar a:hover {
            background-color: #66bb6a;
        }

        #menu-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1002;
            cursor: pointer;
            font-size: 20px;
            color: #4caf50;
        }

        #main-content {
            margin: 80px auto 0;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 1400px;
            width: calc(100% - 40px);
        }

        table th {
            background-color: #4caf50;
            color: #ffffff;
        }

        .btn-primary {
            background-color: #4caf50;
            border-color: #4caf50;
        }

        .btn-primary:hover {
            background-color: #66bb6a;
            border-color: #66bb6a;
        }

        .btn-danger {
            background-color: #e53935;
            border-color: #e53935;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
    <div class="container-fluid">
        <!-- ปุ่ม Menu Toggle สำหรับ Sidebar -->
        <button id="menu-toggle" class="btn btn btn-success btn-sm me-3">☰</button>
        <!-- ข้อมูลผู้ใช้และปุ่ม Log Out ในส่วนขวา -->
        <div class="d-flex ms-auto align-items-center">
            <span class="me-3 text-secondary-emphasis">
                Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?>
            </span>
            <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
        </div>
    </div>
</nav>

<!-- Sidebar Menu -->
<div id="sidebar" class="bg-success vh-100 p-3 text-white" style="position: fixed; top: 0; left: 0; z-index: 1000;">
    <h4 class="text-center mb-4">Menu</h4>
    <a href="dashboard.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
    </a>
    <a href="manage_user.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-users me-2"></i> Manage Users
    </a>
    <a href="manage_store.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-store me-2"></i> Manage Stores
    </a>
    <a href="product_menu.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-box-open me-2"></i> Product Menu
    </a>
    <a href="order_management.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-truck me-2"></i> Order Request
    </a>
    <a href="notification-settings.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-bell me-2"></i> Notification Settings
    </a>
    <a href="reports.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-file-alt me-2"></i> Reports
    </a>
</div>

<!-- Font Awesome and Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</body>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card shadow-sm" style="width: 300%; max-width: 900px;">
        <div class="card-header bg-success text-white">
            <h3 class="text-center mb-0">Report System</h3>
        </div>
        <div class="card-body">
            <form id="reportForm" onsubmit="handleFormSubmit(event)">
                <div class="mb-3">
                    <label for="reportType" class="form-label">ประเภทรายงาน</label>
                    <select id="reportType" class="form-select" aria-label="Report Type" required>
                        <option value="">กรุณาเลือกประเภทของรายงาน</option>
                        <option value="inventory_report">รายงานในคลังสินค้า</option>
                        <option value="requisitioned_report">รายงานสินค้าเบิกใช้</option>
                        <option value="orderlist_report">รายงานการสั่งสินค้า</option>
                        <option value="issue_report">รายงานสินค้าเสียหาย</option>
                        <option value="transaction_report">รายงานธุรกรรมของผู้ใช้</option>
                        <option value="alert_setting_report">รายงานการตั้งค่าการแจ้งเตือนสินค้า</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="reportFirstDate" class="form-label">First Date:</label>
                    <input type="date" id="reportFirstDate" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label for="reportLastDate" class="form-label">Last Date:</label>
                    <input type="date" id="reportLastDate" class="form-control" required>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success me-2">Export File</button>
                    <button type="button" class="btn btn-danger" onclick="cancelReport()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Sidebar toggle
    document.getElementById('menu-toggle').onclick = () => {
        document.getElementById('sidebar').classList.toggle('active');
    };

    // Form handling
    function handleFormSubmit(event) {
        event.preventDefault();
        const reportType = document.getElementById('reportType').value;

        if (!reportType) {
            alert('กรุณาเลือกประเภทรายงานให้ถูกต้อง');
            return;
        }

        const reportPages = {
            inventory_report: 'inventory_report.php',
            requisitioned_report: 'requisitioned_report.php',
            orderlist_report: 'orderlist_report.php',
            issue_report: 'issue_report.php',
            transaction_report: 'transaction_report.php',
            alert_setting_report: 'alert_setting_report.php'
        };

        window.location.href = reportPages[reportType] || '#';
    }

    function cancelReport() {
        document.getElementById('reportForm').reset();
    }
</script>
</body>
</html>
