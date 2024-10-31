<?php
session_start();
include('../../config/db.php');

// ตรวจสอบสิทธิ์การเข้าใช้งาน
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// ดึงข้อมูล DB จาก Stocks
$query = "SELECT 
          FROM 
          LEFT JOIN  ON 
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

// Query to fetch order data
$orderQuery = "SELECT order_id, store_id, order_status, total_amount, order_date FROM orders";
$orderResult = mysqli_query($conn, $orderQuery);

if (!$orderResult) {
    die('Query Failed: ' . mysqli_error($conn));
}

$orders = mysqli_fetch_all($orderResult, MYSQLI_ASSOC);
mysqli_close($conn);
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

.table-container {
    margin: 80px auto;
    padding: 30px;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 1000px;
}

.table th {
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

    <!-- Sidebar toggle button -->
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
        <a href="order_management.php">Order Management</a>
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>

    <!-- Main content -->
    <div class="container table-container">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-success">
                    <tr>
                        <th>1</th>
                        <th>2</th>
                        <th>3</th>
                        <th>4</th>
                        <th>5</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['store_id']) ?></td>
                            <td><?= htmlspecialchars($order['order_status']) ?></td>
                            <td><?= htmlspecialchars($order['total_amount']) ?></td>
                            <td><?= htmlspecialchars($order['order_date']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-end">
            <button class="btn btn-success me-2" onclick="exportToCSV()">Export</button>
            <a href="reports.php" class="btn btn-secondary">Back to Report Menu</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar
        document.getElementById('menu-toggle').onclick = function() {
            document.getElementById('sidebar').classList.toggle('active');
        };

        // Export to CSV function
        function exportToCSV() {
            const data = <?= json_encode($orders); ?>;
            const headers = ["Order ID", "Store ID", "Order Status", "Total Amount", "Order Date"];
            const csvRows = [headers.join(',')];

            data.forEach(row => {
                const values = headers.map(header => {
                    const key = header.toLowerCase().replace(' ', '_');
                    const escaped = (`${row[key]}`).replace(/"/g, '\\"');
                    return `"${escaped}"`;
                });
                csvRows.push(values.join(','));
            });

            const csvData = csvRows.join('\n');
            const blob = new Blob([csvData], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'orders_report.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
    </script>
</body>
</html>