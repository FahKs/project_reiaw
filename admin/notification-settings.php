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

// Fetch all products
$query = "SELECT p.*, a.low_stock_threshold, a.expiry_alert_days 
          FROM products_info p
          LEFT JOIN product_alert_settings a ON p.listproduct_id = a.listproduct_id";
$result = $conn->query($query);

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
    <style>
        .product-row:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }
    </style>
</head>
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
        <a href="order_management.php">Order reqeuest</a>
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>
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