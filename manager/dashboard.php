<?php
session_start();
include('../../config/db.php');

if ($_SESSION['role'] !== 'manager' || $_SESSION['store_id'] === null) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

// Fetch user and store information
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
    header("Location: ../../auth/login.php");
    exit();
}
// ดึงข้อมูลผู้ใช้ใหม่ล่าสุด
function fetchRecentUsers($conn) {
    $query = "SELECT name, surname, role FROM users ORDER BY update_at DESC LIMIT 3";
    $result = $conn->query($query);
    $recentUsers = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $recentUsers[] = $row;
        }
    }
    return $recentUsers;
}

$recentUsers = fetchRecentUsers($conn);


$stmt->close();

function fetchNotifications($conn) {
    // ปรับการเชื่อมต่อข้อมูลจากสองตารางเพื่อดึงข้อมูลสินค้า
    $query = "SELECT p.product_name, p.quantity_set, a.low_stock_threshold, a.expiry_alert_days
              FROM products_info p
              LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id
              WHERE p.quantity_set <= a.low_stock_threshold OR a.expiry_alert_days <= 10
              ORDER BY a.expiry_alert_days DESC
              LIMIT 3"; // จำกัดแค่ 3 การแจ้งเตือน
    $result = $conn->query($query);

    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

// ดึงข้อมูลการแจ้งเตือน
$notifications = fetchNotifications($conn);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./responsive.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<style>
     body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        #menu-toggle {
            position: fixed;
            top: 10px;
            left: 10px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 8%;
            padding: 10px;
            font-size: 20px;
            cursor: pointer;
            z-index: 1002;
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
    left: -250px; /* Start with sidebar hidden */
    z-index: 1001;
}

#sidebar.active {
    transform: translateX(-250px);
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
        #banner {
            background-color: #ffffff;
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
            height: 60px; /* Fixed height for the banner */
        }
        #user-info {
            margin-left: auto;
            color: black;
            font-size: 14px;
        }

    .container-dashboard {
    padding-top: 100px; 
    max-width: 1000px; 
    margin: 0 auto; 
}

.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px; 
    justify-content: space-between; 
}

.card-dashboard {
    background: #ffffff; 
    border-radius: 8px; 
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); 
    padding: 30px; 
    text-align: left; 
    width: calc(50% - 10px); 
    font-size: 1rem; 
    color: #333; 
}
</style>
<body>
    <button id="menu-toggle">☰</button>
    <header id="banner">
        <a id="user-info">Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?>
        <?php if (!is_null($store_id)) { ?> 
            | Store: <?php echo $store_name; ?> 
        <?php } ?>
        </a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>
   <!-- Sidebar Menu -->
<div id="sidebar" class="bg-success vh-100 p-3 text-white" style="position: fixed; top: 0; left: 0; z-index: 1000;">
    <h4 class="text-center mb-4">Menu</h4>
    <a href="dashboard.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
    </a>
    <a href="show_user.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-user me-2"></i> Show User
    </a>
    <a href="order.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-box me-2"></i> Order
    </a>
    <a href="tracking.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-map-marker-alt me-2"></i> Tracking
    </a>
    <a href="scaning_product.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-barcode me-2"></i> Scanning Products
    </a>
    <a href="inventory.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-warehouse me-2"></i> Inventory
    </a>
    <a href="reports_ploblem.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-exclamation-triangle me-2"></i> Reports Problem
    </a>
    <a href="reports.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-file-alt me-2"></i> Reports
    </a>
</div>
<!-- Font Awesome and Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <div class="container-dashboard">
        <div class="card-container">
            <div class="card-dashboard">
                <h5>Product Notifications</h5>
                <div id="notifications">
                    <?php if (!empty($notifications)): ?>
                        <?php foreach ($notifications as $notification): ?>
                            <p><strong><?php echo htmlspecialchars($notification['product_name']); ?></strong><br>
                            <?php if ($notification['expiry_alert_days'] <= 10): ?>
                                <span class="badge badge-warning">Expiry Warning</span> Expires in <?php echo htmlspecialchars($notification['expiry_alert_days']); ?> days.
                            <?php endif; ?>
                            <?php if ($notification['quantity_set'] <= $notification['low_stock_threshold']): ?>
                                <span class="badge badge-danger">Low Stock Warning</span> Only <?php echo htmlspecialchars($notification['quantity_set']); ?> left.
                            <?php endif; ?></p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-dashboard">
                <h5>New Users</h5>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Surname</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['surname']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main-content').classList.toggle('sidebar-active');
    });
</script>
</body>
</html>
