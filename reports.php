<?php
session_start();
include('../../config/db.php');

// ตรวจสอบสิทธิ์ของผู้ใช้
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Guest';
$surname = isset($_SESSION['surname']) ? $_SESSION['surname'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- เพิ่มไลบรารี SheetJS สำหรับการจัดการ Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
    <style>
        /* Base styles */
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
            flex-wrap: wrap;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
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
            border-right: 2px solid #2c3e50;
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

        #main-content {
            margin-left: auto;
            margin-right: auto;
            margin-top: 80px;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: margin-left 0.3s ease-in-out;
            max-width: 1400px;
            width: calc(100% - 40px);
        }

        /* Responsive styles */
        @media screen and (max-width: 768px) {
            #sidebar {
                width: 100%;
                left: -100%;
            }

            #sidebar.active {
                transform: translateX(100%);
            }

            #main-content.sidebar-active {
                margin-left: 270px;
            }
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
    <button id="menu-toggle">☰</button>
    <header id="banner">
        <a id="user-info">Name: <?php echo htmlspecialchars($name . ' ' . $surname); ?> | Role: <?php echo htmlspecialchars($user_role); ?></a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>
    <div id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_user.php">Manage Users</a>
        <a href="manage_store.php">Manage Stores</a>
        <a href="product_menu.php">Product Menu</a>
        <a href="order_management.php">Order Requests</a>
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>

    <div class="container-fluid" id="main-content">
        <h3>Download Report</h3>
        <form method="POST" id="date-range-form">
            <div class="form-row">
                <div class="col-md-3">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label for="report-type">Report Type</label>
                    <select id="report-type" name="report_type" class="form-control">
                        <option value="stock">Stock Report</option>
                        <option value="used">Used Products Report</option>
                        <option value="order">Order Report</option>
                        <option value="damaged">Damaged Products Report</option>
                        <option value="transaction">Transaction Report</option>
                        <option value="monthly_alert">Monthly Alert Report</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="button" class="btn btn-secondary" id="preview-btn">Preview</button>
                <button type="button" class="btn btn-primary" id="download-btn">Download Excel</button>
            </div>
        </form>

        <!-- ส่วนนี้จะใช้แสดงตัวอย่างรายงาน -->
        <div class="mt-3">
            <h4>Report Preview</h4>
            <table class="table table-bordered" id="report-preview" style="display:none;">
                <thead>
                    <tr id="preview-header"></tr>
                </thead>
                <tbody id="preview-body"></tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('preview-btn').addEventListener('click', previewReport);
        document.getElementById('download-btn').addEventListener('click', exportToExcel);

        async function fetchData() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const reportType = document.getElementById('report-type').value;

            try {
                const response = await fetch(`../../api/reports.php?report_type=${reportType}&start_date=${startDate}&end_date=${endDate}`);

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                const data = await response.json();

                if (!data || data.error) {
                    console.error('Error fetching data:', data.error || 'Unknown error');
                    alert('Error fetching data: ' + (data.error || 'Unknown error'));
                    return null;
                }

                return { reportType, data };
            } catch (error) {
                console.error('Error fetching or processing data:', error);
                alert('Error fetching or processing data: ' + error.message);
                return null;
            }
        }

        async function previewReport() {
            const result = await fetchData();
            if (!result) return;

            const { reportType, data } = result;

            let headers = [];
            let rows = [];

            // เตรียมข้อมูลตามประเภทของรายงาน
            switch (reportType) {
                case 'stock':
                    headers = ['Product ID', 'Product Name', 'Category', 'Quantity', 'Price'];
                    rows = data.map(item => [item.listproduct_id, item.product_name, item.category, item.quantity_set, item.price_set]);
                    break;
                case 'used':
                    headers = ['Product ID', 'Product Name', 'Total Used'];
                    rows = data.map(item => [item.listproduct_id, item.product_name, item.total_used]);
                    break;
                case 'order':
                    headers = ['Order ID', 'Order Date', 'Product Name', 'Quantity', 'Price'];
                    rows = data.map(item => [item.order_id, item.order_date, item.product_name, item.quantity_set, item.price]);
                    break;
                case 'damaged':
                    headers = ['Order ID', 'Product Name', 'Quantity', 'Price'];
                    rows = data.map(item => [item.order_id, item.product_name, item.quantity_set, item.price]);
                    break;
                case 'transaction':
                    headers = ['Transaction ID', 'User ID', 'Store ID', 'Payment Method', 'Amount', 'Transaction Date'];
                    rows = data.map(item => [item.transaction_id, item.user_id, item.store_id, item.payment_method, item.amount, item.transaction_date]);
                    break;
                case 'monthly_alert':
                    headers = ['Product Name', 'Quantity', 'Reorder Level', 'Alert Date'];
                    rows = data.map(item => [item.product_name, item.quantity_set, item.reorder_level, item.alert_date]);
                    break;
                default:
                    console.error('Invalid report type');
                    return;
            }

            // อัปเดตหัวตาราง
            const headerRow = document.getElementById('preview-header');
            headerRow.innerHTML = '';
            headers.forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });

            // อัปเดตข้อมูลในตาราง
            const tbody = document.getElementById('preview-body');
            tbody.innerHTML = '';
            rows.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });

            // แสดงตาราง preview
            document.getElementById('report-preview').style.display = 'table';
        }

        async function exportToExcel() {
            const result = await fetchData();
            if (!result) return;

            const { reportType, data } = result;

            // สร้างไฟล์ Excel
            let excelData = [];
            switch (reportType) {
                case 'stock':
                    excelData.push(['Product ID', 'Product Name', 'Category', 'Quantity', 'Price']);
                    data.forEach(item => {
                        excelData.push([item.listproduct_id, item.product_name, item.category, item.quantity_set, item.price_set]);
                    });
                    break;
                case 'used':
                    excelData.push(['Product ID', 'Product Name', 'Total Used']);
                    data.forEach(item => {
                        excelData.push([item.listproduct_id, item.product_name, item.total_used]);
                    });
                    break;
                case 'order':
                    excelData.push(['Order ID', 'Order Date', 'Product Name', 'Quantity', 'Price']);
                    data.forEach(item => {
                        excelData.push([item.order_id, item.order_date, item.product_name, item.quantity_set, item.price]);
                    });
                    break;
                case 'damaged':
                    excelData.push(['Order ID', 'Product Name', 'Quantity', 'Price']);
                    data.forEach(item => {
                        excelData.push([item.order_id, item.product_name, item.quantity_set, item.price]);
                    });
                    break;
                case 'transaction':
                    excelData.push(['Transaction ID', 'User ID', 'Store ID', 'Payment Method', 'Amount', 'Transaction Date']);
                    data.forEach(item => {
                        excelData.push([item.transaction_id, item.user_id, item.store_id, item.payment_method, item.amount, item.transaction_date]);
                    });
                    break;
                case 'monthly_alert':
                    excelData.push(['Product Name', 'Quantity', 'Reorder Level', 'Alert Date']);
                    data.forEach(item => {
                        excelData.push([item.product_name, item.quantity_set, item.reorder_level, item.alert_date]);
                    });
                    break;
                default:
                    console.error('Invalid report type');
                    return;
            }

            var ws = XLSX.utils.aoa_to_sheet(excelData);
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, reportType);
            XLSX.writeFile(wb, reportType + '_report.xlsx');
        }
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
