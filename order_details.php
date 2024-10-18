<?php
session_start();
include('../../config/db.php');
require_once('../../vendor/autoload.php'); // นำเข้าไฟล์ autoload ของ Composer

use Picqer\Barcode\BarcodeGeneratorHTML; // ใช้ BarcodeGeneratorHTML สำหรับสร้างบาร์โค้ด

// ตรวจสอบว่ามีการส่งค่า order_id มาหรือไม่
if (!isset($_GET['id'])) {
    header('Location: order_management.php');
    exit;
}

$order_id = $_GET['id'];

// ดึงข้อมูลคำสั่งซื้อ
$order_query = "SELECT o.*, s.store_name FROM orders o JOIN stores s ON o.store_id = s.store_id WHERE o.order_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();

// ดึงข้อมูลสินค้าในคำสั่งซื้อพร้อมกับบาร์โค้ด
$items_query = "SELECT do.*, pi.product_name, p.slip_img, pr.barcode 
                FROM detail_orders do 
                JOIN products_info pi ON do.listproduct_id = pi.listproduct_id
                LEFT JOIN payments p ON do.order_id = p.order_id
                LEFT JOIN product pr ON do.listproduct_id = pr.listproduct_id
                WHERE do.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./responsive.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Order Details - Order #<?php echo $order['order_id']; ?></h2>
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Order Information</h5>
                <p><strong>Store:</strong> <?php echo $order['store_name']; ?></p>
                <p><strong>Total Amount:</strong> <?php echo $order['total_amount']; ?></p>
                <p><strong>Order Date:</strong> <?php echo $order['order_date']; ?></p>
                <p><strong>Status:</strong> <?php echo $order['order_status']; ?></p>
            </div>
        </div>

        <h3>Order Items</h3>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                    <th>Barcode</th> <!-- คอลัมน์เลขบาร์โค้ด -->
                    <th>Barcode Image</th> <!-- คอลัมน์รูปภาพบาร์โค้ด -->
                    <th>Slip Image</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['quantity_set']; ?></td>
                    <td><?php echo $item['price']; ?></td>
                    <td><?php echo $item['quantity_set'] * $item['price']; ?></td>
                    <td><?php echo $item['barcode']; ?></td> <!-- แสดงเลขบาร์โค้ด -->
                    <td>
                    <?php
if (!empty($item['barcode'])) {
    // สร้างบาร์โค้ดโดยใช้รหัสที่เก็บไว้ใน SQL
    $barcode = $item['barcode'];  // ใช้รหัสเต็มตามที่เก็บในฐานข้อมูล
    $generator = new BarcodeGeneratorHTML();
    echo $generator->getBarcode($barcode, $generator::TYPE_CODE_128);
} else {
    echo 'No barcode available';
}
?>

              </td>
                    <td>
                        <?php 
                        if (!empty($item['slip_img'])) {
                            echo '<img src="slip_img/' . $item['slip_img'] . '" alt="Slip Image" width="100">';
                        } else {
                            echo 'No slip available';
                        }
                        ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <a href="order_management.php" class="btn btn-primary">Back to Order Management</a>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
