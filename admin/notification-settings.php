<?php
session_start();
include('../../config/db.php');

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user information
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

// Pagination settings
$recordPerPage = 5; // จำนวนข้อมูลต่อหน้า
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordPerPage;

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM products_info p
                LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id";
$count_result = $conn->query($count_query);
$totalRecords = $count_result->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordPerPage);

// Fetch products with pagination
$query = "SELECT p.*, a.low_stock_threshold, a.expiry_alert_days 
          FROM products_info p
          LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $recordPerPage);
$stmt->execute();
$result = $stmt->get_result();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'];
    $low_stock_threshold = $_POST['low_stock_threshold'];
    $expiry_alert_days = $_POST['expiry_alert_days'];

    $update_query = "INSERT INTO product_alert_settings (listproduct_id, low_stock_threshold, expiry_alert_days) 
                     VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE 
                     low_stock_threshold = VALUES(low_stock_threshold), 
                     expiry_alert_days = VALUES(expiry_alert_days)";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("iii", $product_id, $low_stock_threshold, $expiry_alert_days);
    $update_stmt->execute();
    $update_stmt->close();

    header("Location: notification-settings.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
    .product-row:hover {
            background-color: #f8f9fa;
            cursor: pointer;
    }
        
    #sidebar {
        transform: translateX(-250px); /* ซ่อน Sidebar ออกไปทางซ้าย */
        transition: all 0.3s ease;
        width: 250px;
    }

    #sidebar.active {
        transform: translateX(0); /* แสดง Sidebar */
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

    <div class="container" id="main-content">
    <h2>Notification Settings</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Current Stock</th>
                    <th>Low Stock Threshold</th>
                    <th>Expiry Alert Days</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr class="product-row" data-product-id="<?php echo $row['listproduct_id']; ?>">
                    <td><?php echo $row['listproduct_id']; ?></td>
                    <td><?php echo $row['product_name']; ?></td>
                    <td><?php echo $row['category']; ?></td>
                    <td><?php echo $row['quantity_set']; ?></td>
                    <td><?php echo $row['low_stock_threshold'] ?? 'Not set'; ?></td>
                    <td><?php echo $row['expiry_alert_days'] ?? 'Not set'; ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm edit-settings" data-product-id="<?php echo $row['listproduct_id']; ?>">
                            Edit Settings
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav aria-label="Page navigation example">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php if($page > 1) { echo "?page=" . ($page - 1); } else { echo '#'; } ?>">Previous</a>
                </li>
                <?php for($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php if($page >= $totalPages) echo 'disabled'; ?>">
                    <a class="page-link" href="<?php if($page < $totalPages) { echo "?page=" . ($page + 1); } else { echo '#'; } ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
    

    <!-- Modal for editing settings -->
    <div class="modal fade" id="settingsModal" tabindex="-1" role="dialog" aria-labelledby="settingsModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="settingsModalLabel">Edit Notification Settings</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="settingsForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" id="product_id" name="product_id">
                        <div class="form-group">
                            <label for="low_stock_threshold">Low Stock Threshold:</label>
                            <input type="number" class="form-control" id="low_stock_threshold" name="low_stock_threshold" required>
                        </div>
                        <div class="form-group">
                            <label for="expiry_alert_days">Expiry Alert Days:</label>
                            <input type="number" class="form-control" id="expiry_alert_days" name="expiry_alert_days" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.edit-settings').click(function() {
                var productId = $(this).data('product-id');
                var lowStockThreshold = $(this).closest('tr').find('td:eq(4)').text();
                var expiryAlertDays = $(this).closest('tr').find('td:eq(5)').text();

                $('#product_id').val(productId);
                $('#low_stock_threshold').val(lowStockThreshold === 'Not set' ? '' : lowStockThreshold);
                $('#expiry_alert_days').val(expiryAlertDays === 'Not set' ? '' : expiryAlertDays);

                $('#settingsModal').modal('show');
            });
        });
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    </script>
 </body>
</html>