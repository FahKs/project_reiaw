<?php
session_start();
include('../../config/db.php');
require_once('../../vendor/autoload.php');
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\Exceptions\BarcodeException;

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

// Pagination settings
$recordPerPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordPerPage;

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM orders";
$count_result = $conn->query($count_query);
$totalRecords = $count_result->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $recordPerPage);

// Fetch orders with pagination
$orders_query = "SELECT o.*, s.store_name FROM orders o 
                 JOIN stores s ON o.store_id = s.store_id 
                 ORDER BY o.order_date DESC 
                 LIMIT $offset, $recordPerPage";
$orders_result = $conn->query($orders_query);

// Function to generate a unique barcode
function generateBarcode() {
    return uniqid() . rand(1000, 9999);
}

// Function to generate barcode image and store it in ../barcodes
function generateBarcodeImage($barcode) {
     // สร้างบาร์โค้ด
     $generator = new BarcodeGeneratorPNG();
     $barcode_data = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
     
     // กำหนดพาธที่ต้องการบันทึกไฟล์
     $barcode_img_path = '../barcodes/' . $barcode . '.png';
     
     // สร้างภาพจากข้อมูลบาร์โค้ดโดยใช้ GD
     $image = imagecreatefromstring($barcode_data);
     if ($image === false) {
         throw new BarcodeException('Failed to create image from barcode data');
     }
 
     // สร้างภาพที่มีพื้นหลังสีขาว
     $width = imagesx($image);
     $height = imagesy($image);
     $white_bg_image = imagecreatetruecolor($width, $height);
     
     // กำหนดสีพื้นหลังเป็นสีขาว
     $white = imagecolorallocate($white_bg_image, 255, 255, 255); 
     imagefill($white_bg_image, 0, 0, $white);
     
     // คัดลอกบาร์โค้ดลงในภาพที่มีพื้นหลังสีขาว
     imagecopy($white_bg_image, $image, 0, 0, 0, 0, $width, $height);
     
     // บันทึกภาพไปยังไฟล์
     imagepng($white_bg_image, $barcode_img_path);
     
     // ทำการลบภาพจากหน่วยความจำ
     imagedestroy($image);
     imagedestroy($white_bg_image);
 
     return $barcode_img_path;
}

// Handle order processing
if (isset($_POST['process_order'])) {
    $order_id = $_POST['order_id'];
    $expiration_dates = $_POST['expiration_date']; // Array of expiration dates for each detail_order_id
    
    // สร้างบาร์โค้ดเฉพาะสำหรับทั้งออเดอร์
    $order_barcode = generateBarcode();
     // Generate barcode image and get image path
     $barcode_img_path = generateBarcodeImage($order_barcode);
    // Start transaction
    $conn->begin_transaction();
    try {
        // Update order status to 'processing'
        $update_order = $conn->prepare("UPDATE orders SET order_status = 'shipped', shipping_date = CURRENT_TIMESTAMP, barcode = ?, barcode_pic = ? WHERE order_id = ?");
        $update_order->bind_param("ssi", $order_barcode, $barcode_img_path, $order_id);
        $update_order->execute();

        // Get order details and store_id
        $get_store = $conn->prepare("SELECT store_id FROM orders WHERE order_id = ?");
        $get_store->bind_param("i", $order_id);
        $get_store->execute();
        $store_result = $get_store->get_result();
        $store_data = $store_result->fetch_assoc();
        $store_id = $store_data['store_id'];

        // Get order details with quantity_set from products_info
        $get_details = $conn->prepare("SELECT do.detail_order_id, do.listproduct_id, do.quantity_set, 
                                     pi.product_name, pi.quantity_set as product_quantity_set
                                     FROM detail_orders do 
                                     JOIN products_info pi ON do.listproduct_id = pi.listproduct_id
                                     WHERE do.order_id = ?");
        $get_details->bind_param("i", $order_id);
        $get_details->execute();
        $details_result = $get_details->get_result();

        while ($detail = $details_result->fetch_assoc()) {
            $detail_order_id = $detail['detail_order_id'];
            $listproduct_id = $detail['listproduct_id'];
            $order_quantity = $detail['quantity_set']; // Number of sets ordered
            $product_quantity = $detail['product_quantity_set']; // Quantity per set
            $expiration_date = $expiration_dates[$detail_order_id];
            
            // Generate barcode for each item in the set
            for ($i = 0; $i < $order_quantity; $i++) {
                // Insert one record per item in the set
                $insert_product = $conn->prepare("INSERT INTO product (listproduct_id, store_id, 
                                               status, quantity, expiration_date, detail_order_id) 
                                               VALUES (?, ?, 'available', ?, ?, ?)");
                $insert_product->bind_param("iiisi", $listproduct_id, $store_id, 
                                         $product_quantity, $expiration_date, $detail_order_id);
                $insert_product->execute();
            }
        }
        if (!$update_order->execute()) {
            throw new Exception("Failed to update order with barcode information");
        }

        $conn->commit();
        // Add success message or redirect
    } catch (Exception $e) {
        error_log("Order processing failed: " . $e->getMessage());
        $conn->rollback();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<style>
    #sidebar {
        transform: translateX(-250px); /* ซ่อน Sidebar ออกไปทางซ้าย */
        transition: all 0.3s ease;
        width: 250px;
        }

        #sidebar.active {
        transform: translateX(0); /* แสดง Sidebar */
        }
    
        .table th, .table td {
            vertical-align: middle;
        }
        .status-badge {
            font-size: 0.85em;
            padding: 5px 10px;
            border-radius: 5px;
        }
        .status-failed { background-color: #f8d7da; color: #721c24; }
        .status-in-process { background-color: #fff3cd; color: #856404; }
        .status-shipped { background-color: #d4edda; color: #155724; }
        .btn-view {
            padding: 5px 15px;
            font-size: 0.85em;
        }
</style>
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
    <div class="container-fluid" id="main-content">
        <h2>Order Management</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th scope="col">
                        <input type="checkbox">
                    </th>
                    <th>Order ID</th>
                    <th>Store</th>
                    <th>Total Amount</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($order = $orders_result->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox"></td>
                    <td>#<?php echo $order['order_id']; ?></td>
                    <td><?php echo $order['store_name']; ?></td>
                    <td>฿ <?php echo number_format($order['total_amount'], 2); ?> THB</td>
                    <td><?php echo date("F d, Y", strtotime($order['order_date'])); ?></td>
                    <td>
                        <?php 
                        switch ($order['order_status']) {
                            case 'Failed': echo '<span class="status-badge status-failed">Failed</span>'; break;
                            case 'In process': echo '<span class="status-badge status-in-process">In process</span>'; break;
                            case 'Shipped': echo '<span class="status-badge status-shipped">Shipped</span>'; break;
                            default: echo '<span class="status-badge">' . $order['order_status'] . '</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary btn-sm btn-view">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

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
                    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    </script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
