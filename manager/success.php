<?php
session_start();
require_once('../../config/db.php');

if (!isset($_GET['order_id'])) {
    header('Location: order.php');
    exit;
}

$order_id = intval($_GET['order_id']);

// Fetch order details
$query = "SELECT o.*, do.*, p.product_name, py.payment_method, py.payment_pic
          FROM orders o
          JOIN detail_orders do ON o.order_id = do.order_id
          JOIN products_info p ON do.listproduct_id = p.listproduct_id
          JOIN payments py ON o.order_id = py.order_id
          WHERE o.order_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$order_items = [];
$total = 0;
$payment_method = '';
$payment_pic = '';

while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
    $total = $row['total_amount'];
    $payment_method = $row['payment_method'];
    $payment_pic = $row['payment_pic'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">Order Successful!</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-success">
                            Your order has been successfully processed and payment has been received.
                        </div>
                        
                        <h4>Order Details</h4>
                        <p>Order ID: #<?php echo $order_id; ?></p>
                        <p>Payment Method: <?php echo ucfirst($payment_method); ?></p>
                        
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity_set']; ?></td>
                                    <td>฿<?php echo number_format($item['price'], 2); ?></td>
                                    <td>฿<?php echo number_format($item['price'] * $item['quantity_set'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">Total:</th>
                                    <th>฿<?php echo number_format($total, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        
                        <?php if ($payment_method === 'promptpay' && $payment_pic): ?>
                        <div class="mt-4">
                            <h5>Payment Proof</h5>
                            <img src="payment_proofs/<?php echo htmlspecialchars($payment_pic); ?>" 
                                 alt="Payment Proof" 
                                 class="img-fluid" 
                                 style="max-width: 300px;">
                        </div>
                        <?php endif; ?>
                        
                        <div class="text-center mt-4">
                            <a href="order.php" class="btn btn-primary">Back to Order Page</a>
                            <a href="tracking.php" class="btn btn-info">Track Order</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>