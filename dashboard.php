<?php
session_start();
include('../../config/db.php');

// ตรวจสอบว่า role เป็น manager หรือไม่
if ($_SESSION['role'] !== 'manager') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$query = "SELECT u.name, u.surname, u.role, s.store_name 
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
    $store_name = $user['store_name'];
} else {
    header("Location: login.php");
    exit();
}

$stmt->close();

function fetchNotifications($conn) {
    // ปรับการเชื่อมต่อข้อมูลจากสองตารางเพื่อดึงข้อมูลสินค้า
    $query = "SELECT p.product_name, p.quantity_set, a.low_stock_threshold, a.expiry_alert_days
          FROM products_info p
          LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id
          WHERE p.quantity_set <= a.low_stock_threshold OR a.expiry_alert_days <= 10
          ORDER BY a.expiry_alert_days DESC";

    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '
            <div class="col-md-6">
                <div class="card notification-card">
                    <div class="card-header bg-success text-white">
                        Product: ' . htmlspecialchars($row['product_name']) . '
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">';

            // แสดงข้อมูลการแจ้งเตือนวันหมดอายุ ถ้ามี
            if ($row['expiry_alert_days'] != null) {
                echo '
                    <li class="list-group-item">
                        <span class="badge bg-warning text-dark">Expiry Warning</span>
                        Expires in ' . htmlspecialchars($row['expiry_alert_days']) . ' days.
                    </li>';
            }

            // แสดงข้อมูลการแจ้งเตือนสินค้าใกล้หมดคลัง ถ้ามี
            if ($row['quantity_set'] <= $row['low_stock_threshold']) {
                echo '
                    <li class="list-group-item">
                        <span class="badge bg-danger">Low Stock Warning</span>
                        Only ' . htmlspecialchars($row['quantity_set']) . ' left in stock.
                    </li>';
            }

            echo '
                        </ul>
                        
                    </div>
                </div>
            </div>';
        }
    } else {
        echo "<p>No notifications at this time.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
</head>
<body>
    <button id="menu-toggle">☰</button>
    <header id="banner">
        <a id="user-info">Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?>
        <?php if (!is_null($store_name)) { ?> 
            | Store: <?php echo $store_name; ?> 
        <?php } ?>
        </a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>
    <div id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="dashboard.php">Dashboard</a>
        <a href="show_user.php">Show User</a>
        <a href="order.php">Order</a>
        <a href="tracking.php">Tracking</a>
        <a href="scaning_product.php">Scan Product</a>
        <a href="inventory.php">Inventory</a>
        <a href="reports_ploblem.php">Reports Problem</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="container" id="main-content">
        <h2 class="mt-4 mb-4">Manager Dashboard</h2>
        <h4>Notifications</h4>
        <div id="notifications" class="row">
            <?php fetchNotifications($conn); ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    </script>
</body>
</html>
