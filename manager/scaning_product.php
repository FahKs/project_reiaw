<?php
session_start();
include('../../config/db.php');

if ($_SESSION['role'] !== 'manager' || $_SESSION['store_id'] === null) {
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
    $store_id = $user['store_id'];
    $store_name = $user['store_name'];
} else {
    header("Location: ../../auth/login.php");
    exit();
}
// Handle barcode scanning
if(isset($_POST['barcode'])) {
    $barcode = trim($_POST['barcode']);
    
    if (empty($barcode)) {
        echo json_encode([
            'success' => false,
            'message' => 'Barcode is empty. Please try again.'
        ]);
        exit();
    }

    // Get order and product details
    $sql = "SELECT o.order_id, o.order_status, o.total_amount, o.order_date,
                do.detail_order_id, do.price, do.listproduct_id,
                pi.product_name, p.status as product_status, 
                p.quantity as product_quantity, p.location
            FROM orders o
            JOIN detail_orders do ON o.order_id = do.order_id
            JOIN products_info pi ON do.listproduct_id = pi.listproduct_id
            LEFT JOIN product p ON do.listproduct_id = p.listproduct_id AND p.store_id = ?
            WHERE o.barcode = ? AND o.store_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $store_id, $barcode, $store_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $orderDetails = null;
        $products = [];
        
        while ($row = $result->fetch_assoc()) {
            if (!$orderDetails) {
                // Update order status if needed
                if ($row['order_status'] === 'shipped') {
                    $updateStatusSql = "UPDATE orders SET order_status = 'delivered' WHERE order_id = ?";
                    $updateStmt = $conn->prepare($updateStatusSql);
                    $updateStmt->bind_param("i", $row['order_id']);
                    $updateStmt->execute();
                    $row['order_status'] = 'delivered';
                }
                
                $orderDetails = [
                    'order_id' => $row['order_id'],
                    'order_status' => $row['order_status'],
                    'total_amount' => $row['total_amount'],
                    'order_date' => $row['order_date']
                ];
            }
            
            $products[] = [
                'product_name' => $row['product_name'],
                'price' => $row['price'],
                'product_status' => $row['product_status'],
                'product_quantity' => $row['product_quantity'],
                'location' => $row['location'],
                'listproduct_id' => $row['listproduct_id']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'order' => $orderDetails,
            'products' => $products
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No order found for this barcode in your store.'
        ]);
    }
    exit();
}
     // Handle product location updates
     $data = json_decode(file_get_contents('php://input'), true);
     if (isset($data['order_id']) && isset($data['products'])) {
         $conn->begin_transaction();
         try {
             // Validate order status
             $checkOrderSql = "SELECT order_status FROM orders WHERE order_id = ? AND store_id = ?";
             $stmt = $conn->prepare($checkOrderSql);
             $stmt->bind_param("ii", $data['order_id'], $store_id);
             $stmt->execute();
             $orderResult = $stmt->get_result();
             $orderData = $orderResult->fetch_assoc();
 
             if (!$orderData || $orderData['order_status'] !== 'delivered') {
                 throw new Exception('Invalid order status or order not found.');
             }
 
             // Update order status
             $updateOrderSql = "UPDATE orders SET order_status = 'completed' WHERE order_id = ?";
             $stmt = $conn->prepare($updateOrderSql);
             $stmt->bind_param("i", $data['order_id']);
             $stmt->execute();
 
             // Update product locations and status
             $updateProductSql = "UPDATE product 
                                 SET location = ?, 
                                     status = CASE 
                                         WHEN status = 'prepare' THEN 'in_stock'
                                         ELSE status 
                                     END
                                 WHERE listproduct_id = ? AND store_id = ?";
             $stmt = $conn->prepare($updateProductSql);
 
             foreach ($data['products'] as $product) {
                 if (empty($product['location'])) {
                     throw new Exception('Location cannot be empty');
                 }
                 $stmt->bind_param("sii", 
                     $product['location'],
                     $product['listproduct_id'],
                     $store_id
                 );
                 $stmt->execute();
                 
                 if ($stmt->affected_rows === 0) {
                     throw new Exception('Product not found or update failed');
                 }
             }
             $conn->commit();
             echo json_encode(['success' => true, 'message' => 'Updates completed successfully']);
         } catch (Exception $e) {
             $conn->rollback();
             echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
         }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="./respontive.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
    /* กำหนดความกว้างสูงสุดให้ modal */
    .modal-lg {
        max-width: 80%;
    }
    /* ปรับการแสดงผลของปุ่มปิด Modal */
    .close {
        font-size: 1.5rem;
    }
    .modal-body h4 {
        font-weight: bold;
    }
    .location-input {
        width: 100%;
        padding: 0.25rem;
        font-size: 0.9rem;
    }
    .modal-footer {
        justify-content: space-between;
    }
    </style>
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
    <div id="sidebar">
        <h4 class="text-center">Menu</h4>
        <a href="dashboard.php">Dashboard</a>
        <a href="show_user.php">Show User</a>
        <a href="order.php">Order</a>
        <a href="tracking.php">Tracking</a>
        <a href="scaning_product.php">Scaning Product</a>
        <a href="inventory.php">Inventory</a>
        <a href="reports_ploblem.php">Reports Ploblem</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="container" id="main-content">
    <h2 class="mt-4 mb-4 text-center">Scan Product</h2>
    
    <div class="row">
        <!-- คอลัมน์ทางซ้าย สำหรับสแกนเนอร์ -->
        <div class="col-md-6">
            <!-- ปุ่ม Start Camera และ Stop Camera -->
            <div class="mb-3">
                <button onclick="startScanner()" id="start-camera" class="btn btn-primary">Start Camera</button>
                <button onclick="stopScanner()" id="stop-camera" class="btn btn-danger ">Stop Camera</button>
            </div>

            <!-- สแกนเนอร์ -->
            <div id="scanner-container" style="width: 100%; max-width: 640px; height: 500px; border: 1px solid #ccc;"></div>
        </div>
        <!-- คอลัมน์ทางขวา สำหรับฟอร์มการกรอกรหัส Barcode -->
        <div class="col-md-6">
            <div class="mt-3">
                <label for="barcode-input">Enter Barcode Manually:</label>
                <input type="text" id="barcode-input" class="form-control" placeholder="Enter barcode here">
                <button onclick="submitManualBarcode()" class="btn btn-secondary btn-block mt-2">Submit Barcode</button>
            </div>
            
            <input type="text" id="barcode-value" class="form-control mt-3" readonly>
            <div id="product-info" class="mt-4"></div>
        </div>
        <!-- Modal ปรับปรุง -->
        <div class="modal fade" id="orderModal" tabindex="-1" role="dialog" aria-labelledby="orderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderModalLabel">Order Details</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="order-info">
                            <h4 class="mb-3">Order Information</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Order ID:</strong> <span id="order-id"></span></p>
                                    <p><strong>Status:</strong> <span id="order-status" class="status-badge"></span></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Total Amount:</strong> ฿<span id="order-amount"></span></p>
                                    <p><strong>Order Date:</strong> <span id="order-date"></span></p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="products-info" class="mt-4">
                            <h4 class="mb-3">Products</h4>
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Stock</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody id="products-table-body">
                                    <!-- รายการสินค้าจะแสดงที่นี่ -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="submit">Submit</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <script>
        let isScanning = false;

        function startScanner() {
            if (isScanning) {
                Quagga.stop();
            }
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: document.querySelector('#scanner-container'),
                    constraints: {
                        width: 640,
                        height: 480,
                        facingMode: "environment"
                    },
                },
                decoder: {
                    readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader"]
                }
            }, function (err) {
                if (err) {
                    console.log(err);
                    return;
                }
                console.log("Initialization finished. Ready to start");
                Quagga.start();
                isScanning = true;
            });

            Quagga.onDetected(function (result) {
                let code = result.codeResult.code;
                document.getElementById('barcode-value').value = code;
                Quagga.stop();
                isScanning = false;
                submitBarcode(code);
            });
        }

        function stopScanner() {
            if (isScanning) {
                Quagga.stop();
                isScanning = false;
                console.log("Scanner stopped");
            }
        }

        function submitBarcode(barcode) {
            fetch('scaning_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'barcode=' + barcode
            })
            .then(response => response.json())  // Parse response as JSON
            .then(data => {
                if (data.success) {
                    // Populate modal with order details
                    document.getElementById('order-id').textContent = data.order.order_id;
                    document.getElementById('order-status').textContent = data.order.order_status;
                    document.getElementById('order-amount').textContent = parseFloat(data.order.total_amount).toLocaleString();
                    
                    // Format date
                    const orderDate = new Date(data.order.order_date);
                    document.getElementById('order-date').textContent = orderDate.toLocaleDateString('th-TH', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });

                    // Clear existing product rows
                    const tableBody = document.getElementById('products-table-body');
                    tableBody.innerHTML = '';

                    // Add product rows
                    data.products.forEach(product => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${product.product_name}</td>
                            <td>฿${parseFloat(product.price).toLocaleString()}</td>
                            <td><span class="badge ${getStatusBadgeClass(product.product_status)}">${product.product_status}</span></td>
                            <td>${product.product_quantity}</td>
                            <td>
                                <input type="text" class="form-control location-input" 
                                    data-listproduct-id="${product.listproduct_id}"
                                    value="${product.location || ''}" 
                                    placeholder="Enter location">
                            </td>`;
                        tableBody.appendChild(row);
                    });

                    // Show the modal
                    $('#orderModal').modal('show');
                } else {
                    // Show error message
                    document.getElementById('product-info').innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            ${data.message}
                        </div>`;
                }

                // Restart scanner after delay if needed
                if (isScanning) {
                    setTimeout(() => {
                        startScanner();
                    }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('product-info').innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        An error occurred while processing the request.
                    </div>`;
                if (isScanning) {
                    startScanner();
                }
            });
        }

        // Helper function to get appropriate badge class based on status
        function getStatusBadgeClass(status) {
            switch (status.toLowerCase()) {
                case 'in_stock':
                    return 'badge-success';
                default:
                    return 'badge-secondary';
            }
        }
        // Add event listener for modal close
        $('#orderModal').on('hidden.bs.modal', function () {
            if (isScanning) {
                startScanner();
            }
        });
        // ฟังก์ชันสำหรับการส่งรหัสบาร์โค้ดด้วยตนเอง
        function submitManualBarcode() {
            const barcode = document.getElementById('barcode-input').value;
            if (barcode.trim() !== "") {
                submitBarcode(barcode);
            } else {
                alert("Please enter a barcode.");
            }
        }
        // เริ่มการสแกนเมื่อเปิดหน้า
        startScanner();
        // Add this after your existing JavaScript code
        document.getElementById('submit').addEventListener('click', function() {
            const orderIdElement = document.getElementById('order-id');
            const orderId = orderIdElement.textContent;
            
            // Collect all location inputs
            const locationInputs = document.querySelectorAll('.location-input');
            const products = [];
            
            locationInputs.forEach(input => {
                products.push({
                    listproduct_id: input.dataset.listproductId,
                    location: input.value.trim()
                });
            });

            // Validate locations
            const emptyLocations = products.some(p => !p.location);
            if (emptyLocations) {
                alert('Please fill in all location fields');
                return;
            }

            // Submit the data
            fetch('scaning_product.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    products: products
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Locations updated successfully');
                    $('#orderModal').modal('hide');
                    // Optionally refresh the page or update the display
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating locations');
            });
        });
    </script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    </script>
</body>
</html>