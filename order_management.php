<?php
session_start();
include('../../config/db.php');
require_once('../../vendor/autoload.php');
use Picqer\Barcode\BarcodeGeneratorPNG;

// ตรวจสอบสิทธิ์การเข้าถึงเฉพาะผู้ดูแลระบบ
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ฟังก์ชันดึงข้อมูลผู้ใช้จากฐานข้อมูล
function fetchUserDetails($conn, $user_id) {
    $query = "SELECT u.name, u.surname, u.role, u.store_id, s.store_name 
              FROM users u
              LEFT JOIN stores s ON u.store_id = s.store_id 
              WHERE u.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}

// เรียกใช้งานฟังก์ชันดึงข้อมูลผู้ใช้
$result = fetchUserDetails($conn, $user_id);

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $name = $user['name'];
    $surname = $user['surname'];
    $role = $user['role'];
} else {
    header("Location: login.php");
    exit();
}

// ฟังก์ชันสร้างบาร์โค้ด PNG และบันทึกเป็น BLOB
function createBarcodePNG($barcode) {
    $generator = new BarcodeGeneratorPNG();
    $barcode_png = $generator->getBarcode($barcode, $generator::TYPE_CODE_128);

    // บันทึกบาร์โค้ดเป็นไฟล์ PNG
    return $barcode_png; // ส่งกลับรูปภาพ PNG ในรูปแบบ binary
}

// ฟังก์ชันจัดการคำสั่งซื้อ
function processOrder($conn, $order_id, $expiration_dates) {
    // สร้างบาร์โค้ดเฉพาะสำหรับออเดอร์นี้
    $order_barcode = uniqid() . rand(1000, 9999);

    // สร้างบาร์โค้ดในรูปแบบ PNG และบันทึกเป็น base64
    $barcode_png = createBarcodePNG($order_barcode);

    // เริ่มต้นการทำธุรกรรม (Transaction)
    $conn->begin_transaction();
    
    try {
        // บันทึกบาร์โค้ด PNG ลงในฐานข้อมูล (ฟิลด์ barcode_pic ที่เป็น BLOB)
        $update_order = $conn->prepare("UPDATE orders SET order_status = 'shipped', shipping_date = CURRENT_TIMESTAMP, barcode = ?, barcode_pic = ? WHERE order_id = ?");
        $null = NULL; // ใช้ในการบันทึก BLOB
        $update_order->bind_param("sbi", $order_barcode, $null, $order_id); // 'sbi' คือ string, blob, int
        $update_order->send_long_data(1, $barcode_png); // ส่งข้อมูล BLOB
        $update_order->execute();

        // ดึงข้อมูล store_id
        $get_store = $conn->prepare("SELECT store_id FROM orders WHERE order_id = ?");
        $get_store->bind_param("i", $order_id);
        $get_store->execute();
        $store_result = $get_store->get_result();
        $store_data = $store_result->fetch_assoc();
        $store_id = $store_data['store_id'];

        // ดึงข้อมูลรายละเอียดคำสั่งซื้อ
        $get_details = $conn->prepare("SELECT do.detail_order_id, do.listproduct_id, do.quantity_set, 
                                      pi.product_name, pi.quantity_set as product_quantity_set
                                      FROM detail_orders do 
                                      JOIN products_info pi ON do.listproduct_id = pi.listproduct_id
                                      WHERE do.order_id = ?");
        $get_details->bind_param("i", $order_id);
        $get_details->execute();
        $details_result = $get_details->get_result();

        // วนลูปเพิ่มสินค้าในตาราง product
        while ($detail = $details_result->fetch_assoc()) {
            $detail_order_id = $detail['detail_order_id'];
            $listproduct_id = $detail['listproduct_id'];
            $order_quantity = $detail['quantity_set'];
            $product_quantity = $detail['product_quantity_set'];
            $expiration_date = $expiration_dates[$detail_order_id];

            for ($i = 0; $i < $order_quantity; $i++) {
                $insert_product = $conn->prepare("INSERT INTO product (listproduct_id, store_id, status, quantity, expiration_date, detail_order_id) 
                                                  VALUES (?, ?, 'available', ?, ?, ?)");
                $insert_product->bind_param("iiisi", $listproduct_id, $store_id, $product_quantity, $expiration_date, $detail_order_id);
                $insert_product->execute();
            }
        }

        $conn->commit(); // ยืนยันการทำธุรกรรม
    } catch (Exception $e) {
        $conn->rollback(); // ยกเลิกการทำธุรกรรมในกรณีที่เกิดข้อผิดพลาด
        error_log("Transaction failed: " . $e->getMessage());
    }
}

// ตรวจสอบว่ามีการส่งคำสั่งซื้อ
if (isset($_POST['process_order'])) {
    $order_id = $_POST['order_id'];
    $expiration_dates = $_POST['expiration_date']; // เก็บวันหมดอายุสำหรับแต่ละ detail_order_id
    processOrder($conn, $order_id, $expiration_dates);
}

// ดึงข้อมูลคำสั่งซื้อทั้งหมด
$orders_query = "SELECT o.*, s.store_name FROM orders o 
                 JOIN stores s ON o.store_id = s.store_id 
                 ORDER BY o.order_date DESC";
$orders_result = $conn->query($orders_query);
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
    <div class="container-fluid" id="main-content">
        <h2>Order Management</h2>
        <table class="table table-striped">
            <thead>
                <tr>
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
                    <td><?php echo $order['order_id']; ?></td>
                    <td><?php echo $order['store_name']; ?></td>
                    <td><?php echo $order['total_amount']; ?></td>
                    <td><?php echo $order['order_date']; ?></td>
                    <td><?php echo $order['order_status']; ?></td>
                    <td>
                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="btn btn-info btn-sm">View Details</a>
                        <?php if ($order['order_status'] == 'confirm'): ?>
                            <button type="button" class="btn btn-success btn-sm" 
                                    data-toggle="modal" 
                                    data-target="#processModal<?php echo $order['order_id']; ?>">
                                Process Order
                            </button>
                            
                            <!-- Process Order Modal -->
                            <div class="modal fade" id="processModal<?php echo $order['order_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Set Expiration Dates</h5>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <form method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                <?php 
                                                $detail_query = "SELECT do.*, pi.product_name 
                                                               FROM detail_orders do 
                                                               JOIN products_info pi ON do.listproduct_id = pi.listproduct_id 
                                                               WHERE do.order_id = ?";
                                                $detail_stmt = $conn->prepare($detail_query);
                                                $detail_stmt->bind_param("i", $order['order_id']);
                                                $detail_stmt->execute();
                                                $detail_result = $detail_stmt->get_result();
                                                
                                                while ($detail = $detail_result->fetch_assoc()):
                                                ?>
                                                    <div class="form-group">
                                                        <label><?php echo $detail['product_name']; ?> (Quantity: <?php echo $detail['quantity_set']; ?>)</label>
                                                        <input type="date" 
                                                               name="expiration_date[<?php echo $detail['detail_order_id']; ?>]" 
                                                               class="form-control" 
                                                               required>
                                                    </div>
                                                <?php endwhile; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                <button type="submit" name="process_order" class="btn btn-primary">Process Order</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
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