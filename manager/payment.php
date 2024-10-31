<?php
session_start();
include('../../config/db.php');
require_once('../../vendor/autoload.php');

// Set Stripe API key
\Stripe\Stripe::setApiKey('sk_test_51Q8Gj8CLFIieIhW4C3c2ufG5TQxuNERogYLnKYylBEnjg1QXZUQpZVAmyqzKO9SvbC84KV0u6YMYX1SIeiC8CEDC00r1ap5dOd');

// Initialize response array
$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is an AJAX request for payment intent
    if (isset($_POST['create_payment_intent'])) {
        try {
            $amount = floatval($_POST['amount']) * 100; // Convert to cents
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => (int)$amount,
                'currency' => 'thb',
            ]);
            
            header('Content-Type: application/json');
            echo json_encode([
                'clientSecret' => $payment_intent->client_secret
            ]);
            exit;
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Handle final payment submission
    if (isset($_POST['payment_method'])) {
        try {
            $payment_method = $_POST['payment_method'];
            $orderItems = json_decode($_POST['order_items'], true);
            $total = floatval($_POST['total']);
            $store_id = intval($_POST['store_id']);
            
            // Start transaction
            $conn->begin_transaction();
            
            // Insert into orders table
            $stmt = $conn->prepare("INSERT INTO orders (store_id, order_status, total_amount) VALUES (?, 'paid', ?)");
            $stmt->bind_param("id", $store_id, $total);
            $stmt->execute();
            $order_id = $conn->insert_id;
            
            // Insert order details
            $detail_stmt = $conn->prepare("INSERT INTO detail_orders (order_id, listproduct_id, quantity_set, price) VALUES (?, ?, ?, ?)");
            
            foreach ($orderItems as $productId => $item) {
                $detail_stmt->bind_param("iiid", $order_id, $productId, $item['quantity'], $item['price']);
                $detail_stmt->execute();
            }
            
            if ($payment_method === 'promptpay' && isset($_FILES['payment_proof'])) {
                $target_dir = "../manager/payment_proofs/"; // ปรับเส้นทางให้อยู่ระดับเดียวกับ picture_product
                
                // เก็บชื่อไฟล์เดิมเหมือน product_menu
                $payment_pic = basename($_FILES["payment_proof"]["name"]);
                
                // ตรวจสอบนามสกุลไฟล์ให้รองรับทั้ง jpg และ jpeg เหมือน product_menu
                $file_extension = strtolower(pathinfo($payment_pic, PATHINFO_EXTENSION));
                if($file_extension != "jpg" && $file_extension != "jpeg") {
                    throw new Exception("Only JPG & JPEG files are allowed");
                }
            
                // ย้ายไฟล์
                if (!move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_dir . $payment_pic)) {
                    throw new Exception("Failed to upload payment proof");
                }
            }
            
            // Insert payment record
            $payment_stmt = $conn->prepare("INSERT INTO payments (order_id, payment_method, amount, payment_pic) VALUES (?, ?, ?, ?)");
            $payment_stmt->bind_param("isds", $order_id, $payment_method, $total, $payment_pic);
            $payment_stmt->execute();
            
            $conn->commit();
            
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'order_id' => $order_id,
                'message' => 'Payment processed successfully'
            ]);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
}

// If no POST request, display the payment form
$orderItems = isset($_POST['order_items']) ? json_decode($_POST['order_items'], true) : [];
$total = isset($_POST['total']) ? floatval($_POST['total']) : 0;
$store_id = isset($_POST['store_id']) ? intval($_POST['store_id']) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Processing</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        #card-element {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background: white;
        }
        
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header">
                        <h3>Payment Details</h3>
                    </div>
                    <div class="card-body">
                        <h4>Order Summary</h4>
                        <div id="order-summary">
                            <!-- Order items will be displayed here -->
                        </div>
                        <hr>
                        <h5>Total Amount: ฿<span id="total-amount"></span></h5>
                        
                        <form id="payment-form" class="mt-4">
                            <div class="form-group">
                                <label>Select Payment Method:</label>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="credit_card" name="payment_method" value="credit_card" class="custom-control-input">
                                    <label class="custom-control-label" for="credit_card">Credit Card</label>
                                </div>
                                <div class="custom-control custom-radio">
                                    <input type="radio" id="promptpay" name="payment_method" value="promptpay" class="custom-control-input">
                                    <label class="custom-control-label" for="promptpay">PromptPay</label>
                                </div>
                            </div>
                            
                            <div id="credit-card-form" style="display: none;">
                                <div id="card-element" class="mb-3"></div>
                                <div id="card-errors" class="text-danger mb-3"></div>
                            </div>
                            
                            <div id="promptpay-form" style="display: none;">
                                <div class="text-center mb-3">
                                    <img id="promptpay-qr" src="" alt="PromptPay QR Code" style="max-width: 200px;">
                                </div>
                                <div class="form-group">
                                    <label>Upload Payment Proof:</label>
                                    <input type="file" class="form-control-file" id="payment_proof" name="payment_proof" accept="image/*">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block mt-4">Process Payment</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            const stripe = Stripe('pk_test_51Q8Gj8CLFIieIhW44yBjAb8BkOWg6rpXaLt3qjVHRvaj9onmLE4Df66nER0wYzthOWSPuu8Bqwp99Ja6QjTJeXnj00vIqRsdV7');
            const elements = stripe.elements();
            const card = elements.create('card');
            let clientSecret = null;
            
            // Get order items and total from PHP
            const orderItems = <?php echo json_encode($orderItems); ?>;
            const total = <?php echo $total; ?>;
            
            // Display order summary
            let summaryHtml = '<ul class="list-group">';
            for (const [productId, item] of Object.entries(orderItems)) {
                summaryHtml += `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${item.name} x ${item.quantity}
                        <span>฿${(item.price * item.quantity).toFixed(2)}</span>
                    </li>`;
            }
            summaryHtml += '</ul>';
            
            $('#order-summary').html(summaryHtml);
            $('#total-amount').text(total.toFixed(2));
            
            // Handle payment method selection
            $('input[name="payment_method"]').change(function() {
                const method = $(this).val();
                if (method === 'credit_card') {
                    $('#credit-card-form').show();
                    $('#promptpay-form').hide();
                    card.mount('#card-element');
                    
                    // Create payment intent
                    $.post('payment.php', {
                        create_payment_intent: true,
                        amount: total
                    }, function(response) {
                        clientSecret = response.clientSecret;
                    });
                    
                } else if (method === 'promptpay') {
                    $('#credit-card-form').hide();
                    $('#promptpay-form').show();
                    card.unmount();
                    
                    // Generate PromptPay QR
                    $('#promptpay-qr').attr('src', `https://promptpay.io/0982795528/${total}`);
                }
            });
            
            // Handle form submission
            $('#payment-form').submit(async function(e) {
                e.preventDefault();
                $('.loading').css('display', 'flex');
                
                const paymentMethod = $('input[name="payment_method"]:checked').val();
                const formData = new FormData();
                
                formData.append('payment_method', paymentMethod);
                formData.append('order_items', JSON.stringify(orderItems));
                formData.append('total', total);
                formData.append('store_id', <?php echo $store_id; ?>);
                
                try {
                    if (paymentMethod === 'credit_card') {
                        const result = await stripe.confirmCardPayment(clientSecret, {
                            payment_method: {
                                card: card
                            }
                        });
                        
                        if (result.error) {
                            throw new Error(result.error.message);
                        }
                    } else if (paymentMethod === 'promptpay') {
                        const paymentProof = $('#payment_proof')[0].files[0];
                        if (!paymentProof) {
                            throw new Error('Please upload payment proof');
                        }
                        // ตรวจสอบประเภทไฟล์ว่าเป็น jpg หรือไม่
                        const fileType = paymentProof.type;
                        if (fileType !== 'image/jpeg') {
                            throw new Error('Please upload only JPG files');
                        }
                        formData.append('payment_proof', paymentProof);
                    }
                    
                    // Process payment
                    const response = await $.ajax({
                        url: 'payment.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false
                    });
                    
                    if (response.status === 'success') {
                        window.location.href = `success.php?order_id=${response.order_id}`;
                    } else {
                        throw new Error(response.message);
                    }
                    
                } catch (error) {
                    $('.loading').hide();
                    alert(error.message);
                }
            });
        });
    </script>
</body>
</html>