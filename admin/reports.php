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
            width: 250px;
            background-color: #4caf50;
            color: #ffffff;
            padding-top: 20px;
            position: fixed;
            height: 100%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
            top: 0;
            left: -250px;
            z-index: 1001;
        }

        #sidebar.active {
            transform: translateX(250px);
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
<header id="banner">
        <a id="user-info">Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?></a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>

    <!-- ปุ่มเปิด/ปิด Sidebar -->
    <div id="menu-toggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar Menu -->
    <div id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_user.php">Manage Users</a>
        <a href="manage_store.php">Manage Stores</a>
        <a href="product_menu.php">Product Menu</a>
        <a href="order_management.php">Order reqeuest</a>
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>
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
                    <select id="reportType" class="form-select" required>
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
