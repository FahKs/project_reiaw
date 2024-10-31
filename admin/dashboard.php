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

$stmt->close();

// ดึงข้อมูลสถิติเบื้องต้น
$totalUsers = $conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0];
$totalStores = $conn->query("SELECT COUNT(*) FROM stores")->fetch_row()[0];
$totalPendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE order_status = 'shipped'")->fetch_row()[0];
$totalProducts = $conn->query("SELECT COUNT(*) FROM product")->fetch_row()[0];

// ดึงข้อมูลรายการคำสั่งซื้อที่สถานะ pending
$ordersResult = $conn->query("SELECT * FROM orders WHERE order_status = 'shipped' LIMIT 1");

// ปิดการเชื่อมต่อฐานข้อมูลหลังจากดึงข้อมูลทั้งหมดแล้ว
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
</head>
<style>
        /* Motion Effects */
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Sidebar Animation */
        #sidebar {
            transition: all 0.3s ease;
        }

        /* Quick Access Button Styling */
        .quick-access-btn {
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .quick-access-btn:hover {
            background-color: #5a5a5a;
            color: #ffffff;
            transform: scale(1.05);
        }
    </style>
<body>
    <button id="menu-toggle">☰</button>
    <header id="banner">
        <a id="user-info">Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?></a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>
    <div id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="dashboard.php">Dashboard</a>
        <a href="manage_user.php">Manage Users</a>
        <a href="manage_store.php">Manage Stores</a>
        <a href="product_menu.php">Product Menu</a>
        <a href="order_management.php">Order Request</a>
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="container mt-4">
        <h2>Admin Dashboard</h2>
        <div class="row">
            <!-- Quick Stats -->
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text"><?php echo $totalUsers; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Stores</h5>
                        <p class="card-text"><?php echo $totalStores; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Pending Orders</h5>
                        <p class="card-text"><?php echo $totalPendingOrders; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <p class="card-text"><?php echo $totalProducts; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <h4>Pending Orders</h4>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $ordersResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['order_id']; ?></td>
                    <td><?php echo $row['order_date']; ?></td>
                    <td><?php echo $row['order_status']; ?></td>
                    <td>
                        <a href="order_details.php?id=<?php echo $row['order_id']; ?>" class="btn btn-info btn-sm">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Quick Access Links -->
        <div class="mt-4">
            <h4>Quick Access</h4>
            <a href="manage_user.php" class="btn btn-primary">Manage Users</a>
            <a href="manage_store.php" class="btn btn-secondary">Manage Stores</a>
            <a href="product_menu.php" class="btn btn-success">Manage Products</a>
            <a href="order_management.php" class="btn btn-warning">Manage Orders</a>
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
