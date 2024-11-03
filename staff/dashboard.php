<?php
session_start();
include('../../config/db.php');

// ตรวจสอบ role และ store_id
if ($_SESSION['role'] !== 'staff' || $_SESSION['store_id'] === null) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
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
    $store_id = $user['store_id'];
    $store_name = $user['store_name'];
} else {
    header("Location: login.php");
    exit();
}

function fetchNotifications($conn) {
    $query = "SELECT p.product_name, p.quantity_set, a.low_stock_threshold, a.expiry_alert_days
              FROM products_info p
              LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id
              WHERE p.quantity_set <= a.low_stock_threshold OR a.expiry_alert_days <= 10
              ORDER BY a.expiry_alert_days DESC
              LIMIT 3";
    $result = $conn->query($query);

    $notifications = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    return $notifications;
}

$notifications = fetchNotifications($conn);
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="respontive.css">
</head>
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
    <div id="sidebar" class="p-3 text-white">
        <h4 class="text-center mb-4">Menu</h4>
        <a href="dashboard.php" class="d-flex align-items-center text-white mb-2">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a href="scaning_product.php" class="d-flex align-items-center text-white mb-2">
            <i class="fas fa-barcode me-2"></i> Scanning Products
        </a>
        <a href="inventory.php" class="d-flex align-items-center text-white mb-2">
            <i class="fas fa-warehouse me-2"></i> Inventory
        </a>
    </div>

    <!-- Main Content -->
    <div id="main-content">
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
