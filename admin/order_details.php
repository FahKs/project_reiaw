<?php
session_start();
include('../../config/db.php');

if (!isset($_GET['id'])) {
    header('Location: order_management.php');
    exit;
}

$order_id = $_GET['id'];

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'confirm') {
            $new_status = 'confirm';
            $update_query = "UPDATE orders SET order_status = ? WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("si", $new_status, $order_id);
            $update_stmt->execute();
        } elseif ($_POST['action'] === 'cancel') {
            $new_status = 'cancel';
            $cancel_reason = $_POST['cancel_reason'];
            
            // Handle cancel image upload
            $cancel_pic = '';
            if (isset($_FILES['cancel_pic']) && $_FILES['cancel_pic']['error'] === 0) {
                $target_dir = "../cancel_payment/"; // กำหนดโฟลเดอร์ที่เก็บรูปภาพ
                $target_file = $target_dir . basename($_FILES['cancel_pic']['name']);
                move_uploaded_file($_FILES['cancel_pic']['tmp_name'], $target_file); // บันทึกไฟล์ลงโฟลเดอร์
                $cancel_pic = basename($_FILES['cancel_pic']['name']); // เก็บชื่อไฟล์ในฐานข้อมูล
            }
            // บันทึกลงฐานข้อมูล
            $update_query = "UPDATE orders SET order_status = ?, cancel_info = ?, cancel_pic = ? WHERE order_id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("sssi", $new_status, $cancel_reason, $cancel_pic, $order_id);
            $update_stmt->execute();
        }
    }
}

// Fetch order details with store information
$order_query = "SELECT o.*, s.store_name, s.tel_store FROM orders o 
                JOIN stores s ON o.store_id = s.store_id 
                WHERE o.order_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();

// Fetch order items
$items_query = "SELECT do.*, pi.product_name FROM detail_orders do 
                JOIN products_info pi ON do.listproduct_id = pi.listproduct_id 
                WHERE do.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Fetch payment details
$payment_query = "SELECT * FROM payments WHERE order_id = ?";
$payment_stmt = $conn->prepare($payment_query);
$payment_stmt->bind_param("i", $order_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Order Details - Order #<?php echo $order['order_id']; ?></h2>
        <a href="order_management.php" class="btn btn-primary">Back to Order Management</a>
        
        <?php if (!empty($upload_error)): ?>
            <div class="alert alert-danger mt-3">
                <?php echo htmlspecialchars($upload_error); ?>
            </div>
        <?php endif; ?>
        
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title">Order Information</h5>
                <p><strong>Store:</strong> <?php echo $order['store_name']; ?></p>
                <p><strong>Total Amount:</strong> <?php echo $order['total_amount']; ?></p>
                <p><strong>Order Date:</strong> <?php echo $order['order_date']; ?></p>
                <p><strong>Status:</strong> <span class="badge badge-<?php 
                    echo $order['order_status'] === 'confirm' ? 'success' : 
                        ($order['order_status'] === 'cancel' ? 'danger' : 'warning'); 
                    ?>"><?php echo $order['order_status']; ?></span></p>
                
                <?php if ($order['order_status'] === 'cancel' && $order['cancel_info']): ?>
                    <div class="alert alert-danger">
                        <h6>Cancellation Reason:</h6>
                        <p><?php echo htmlspecialchars($order['cancel_info']); ?></p>
                        <?php if ($order['cancel_pic']): ?>
                            <h6>Cancellation Image:</h6>
                            <img src="../cancel_payment/<?php echo htmlspecialchars($order['cancel_pic']); ?>" 
                            alt="Cancellation Image" class="img-fluid" style="max-width: 300px;">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php while ($payment = $payment_result->fetch_assoc()): ?>
                    <p><strong>Payment Date:</strong> <?php echo $payment['payment_date']; ?></p>
                    <?php if ($payment['payment_method'] === 'credit_card'): ?>
                        <p><strong>Payment Method:</strong> Credit Card</p>
                    <?php elseif ($payment['payment_pic']): ?>
                        <p><strong>Payment Method:</strong> PromptPay</p>
                        <div>
                            <strong>Payment Proof:</strong><br>
                            <img src="../manager/payment_proofs/<?php echo htmlspecialchars($payment['payment_pic']); ?>" 
                                alt="Payment Proof" style="max-width: 300px;" class="img-fluid">
                        </div>
                    <?php endif; ?>
                <?php endwhile; ?>
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
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $item['product_name']; ?></td>
                    <td><?php echo $item['quantity_set']; ?></td>
                    <td><?php echo $item['price']; ?></td>
                    <td><?php echo $item['quantity_set'] * $item['price']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Order Status Controls -->
        <?php if ($order['order_status'] === 'paid'): ?>
            <div class="mb-3">
                <form method="POST" class="d-inline mr-2">
                    <input type="hidden" name="action" value="confirm">
                    <button type="submit" class="btn btn-success">Confirm Order</button>
                </form>
                
                <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#cancelModal">
                    Cancel Order
                </button>
            </div>
        <?php endif; ?>

        <!-- Cancel Modal -->
        <div class="modal fade" id="cancelModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Order</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="cancel">
                            <div class="form-group">
                                <label for="cancel_reason">Cancellation Reason:</label>
                                <textarea name="cancel_reason" id="cancel_reason" class="form-control" required></textarea>
                            </div>
                            <div class="mt-3">
                                <h6>PromptPay QR Code</h6>
                                <img src="https://promptpay.io/<?php echo $order['tel_store']; ?>/<?php echo $order['total_amount']; ?>" 
                                    alt="PromptPay QR Code" class="img-fluid" style="max-width: 200px;">
                            </div>
                            <div class="form-group">
                                <label for="cancel_pic">Upload Image (JPG/JPEG only):</label>
                                <input type="file" name="cancel_pic" id="cancel_pic" class="form-control-file" accept="image/jpeg">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>