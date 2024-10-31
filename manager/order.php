<?php
session_start();
include('../../config/db.php');

if ($_SESSION['role'] !== 'manager' || $_SESSION['store_id'] === null) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];

// Fetch user and store information
$query = "SELECT u.name, u.surname, u.role, s.store_name 
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
    $store_name = $user['store_name'];
} else {
    header("Location: ../../auth/login.php");
    exit();
}

// รับค่าจากฟอร์มค้นหา
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
// สร้าง SQL Query พื้นฐาน
$query = "SELECT listproduct_id, product_name, category, price_set, quantity_set, product_pic, product_info 
          FROM products_info 
          WHERE visible = 1";
// ตรวจสอบว่ามีการกรอกข้อมูลในฟิลด์ค้นหาหรือไม่
$bind_params = [];
$types = '';

if (!empty($search)) {
    if (is_numeric($search)) {
        // ถ้า search เป็นตัวเลข ให้ค้นหาด้วย Product ID
        $query .= " AND listproduct_id = ?";
        $types .= 'i';
        $bind_params[] = $search;
    } else {
        // ถ้า search เป็นข้อความ ให้ค้นหาด้วยชื่อสินค้า
        $query .= " AND product_name LIKE ?";
        $types .= 's';
        $bind_params[] = '%' . $search . '%';
    }
}

// ตรวจสอบว่ามีการเลือกหมวดหมู่หรือไม่
if (!empty($category)) {
    $query .= " AND category = ?";
    $types .= 's';
    $bind_params[] = $category;
}

// เพิ่มการจัดเรียง
$query .= " ORDER BY category, product_name";

// เตรียมการ Query
$stmt = $conn->prepare($query);

// ถ้ามีเงื่อนไขการค้นหา ก็ bind พารามิเตอร์
if (!empty($bind_params)) {
    $stmt->bind_param($types, ...$bind_params);
}

// รัน Query
$stmt->execute();
$result = $stmt->get_result();
$products = $result->fetch_all(MYSQLI_ASSOC);



$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrderPage-Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .product-card {
            height: 100%;
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        #order-summary {
            position: sticky;
            top: 76px;
        }
    </style>
</head>
<>
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
    <div id="main-content">
        <h2 class="mb-4">Order Products</h2>
        <form action="" method="GET" class="mb-3">
            <div class="input-group">
                <input style="width: 500px;" type="text" class="form-control" placeholder="Search by Product ID or Name" name="search"  value="<?php echo htmlspecialchars($search); ?>">
                <select class="form-control" name="category" >
                    <option value="">เลือกหมวดหมู่</option>
                    <option value="กาแฟ  " <?php echo $category == 'กาแฟ' ? 'selected' : ''; ?>>กาแฟ </option>
                    <option value="นมและครีม" <?php echo $category == 'นมและครีม' ? 'selected' : 'นมและครีม'; ?>>นมและครีม</option>
                    <option value="ไซรัปและน้ำเชื่อม" <?php echo $category == 'ไซรัปและน้ำเชื่อม' ? 'selected' : 'ไซรัปและน้ำเชื่อม'; ?>>ไซรัปและน้ำเชื่อม</option>
                    <option value="ผงเครื่องดื่มและส่วนผสมอื่นๆ" <?php echo $category == 'ผงเครื่องดื่มและส่วนผสมอื่นๆ' ? 'selected' : 'ผงเครื่องดื่มและส่วนผสมอื่นๆ'; ?>>ผงเครื่องดื่มและส่วนผสมอื่นๆ</option>
                    <option value="ขนมและของว่าง" <?php echo $category == 'ขนมและของว่าง' ? 'selected' : 'ขนมและของว่าง'; ?>>ขนมและของว่าง</option>
                    <option value="อุปกรณ์การชงกาแฟ" <?php echo $category == 'อุปกรณ์การชงกาแฟ' ? 'selected' : 'อุปกรณ์การชงกาแฟ'; ?>>อุปกรณ์การชงกาแฟ</option>
                    <option value="แก้วและภาชนะบรรจุ" <?php echo $category == 'แก้วและภาชนะบรรจุ' ? 'selected' : 'แก้วและภาชนะบรรจุ'; ?>>แก้วและภาชนะบรรจุ</option>
                    <option value="สารให้ความหวานและสารแต่งกลิ่นรส" <?php echo $category == 'สารให้ความหวานและสารแต่งกลิ่นรส' ? 'selected' : 'สารให้ความหวานและสารแต่งกลิ่นรส'; ?>>สารให้ความหวานและสารแต่งกลิ่นรส</option>
                    <option value="ผลิตภัณฑ์เพิ่มมูลค่า" <?php echo $category == 'ผลิตภัณฑ์เพิ่มมูลค่า' ? 'selected' : 'ผลิตภัณฑ์เพิ่มมูลค่า'; ?>>ผลิตภัณฑ์เพิ่มมูลค่า</option>
                    <option value="อุปกรณ์เสิร์ฟ" <?php echo $category == 'อุปกรณ์เสิร์ฟ' ? 'selected' : 'อุปกรณ์เสิร์ฟ'; ?>>อุปกรณ์เสิร์ฟ</option>
                    
                </select>
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </div>
        </form>
        <div class="row">
            <div class="col-md-9"> 
                <div class="row" id="product-grid">
                    <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card">
                                <img src="../picture_product/<?php echo htmlspecialchars($product['product_pic']); ?>" class="card-img-top product-image" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                    <p class="card-text">
                                        หมวดหมู่: <?php echo htmlspecialchars($product['category']); ?><br>
                                        ราคา: ฿<?php echo number_format($product['price_set'], 2); ?><br>
                                        จำนวน: <?php echo htmlspecialchars($product['quantity_set']); ?>
                                    </p>
                                    <button class="btn btn-primary btn-sm view-details" data-product-id="<?php echo $product['listproduct_id']; ?>">View Details</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <p>ไม่พบสินค้าที่ตรงกับเงื่อนไขการค้นหา</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <div id="order-summary" class="card">
                <div class="card-header">
                    <h3>Order Summary</h3>
                </div>
                <div class="card-body">
                    <ul id="order-items" class="list-group list-group-flush">
                        <!-- Order items will be dynamically added here -->
                    </ul>
                </div>
                <div id="card-errors" role="alert"></div>
                <div class="card-footer">
                    <h4>Total: ฿<span id="order-total">0.00</span></h4>
                    <button id="confirm-order" class="btn btn-success btn-block mt-3">Confirm Order</button>
                </div>
                </div>
            </div>
        </div>
    </div>
                        
    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" role="dialog" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">Product Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <img id="modal-product-image" src="" alt="Product Image" class="img-fluid mb-3">
                    <h4 id="modal-product-name"></h4>
                    <p id="modal-product-category"></p>
                    <p id="modal-product-price"></p>
                    <p id="modal-product-quantity-set"></p>
                    <p id="modal-product-info"></p>
                    <div class="form-group">
                        <label for="modal-product-quantity">จำนวนเชตที่ต้องการ:</label>
                        <input type="number" class="form-control" id="modal-product-quantity" min="1" value="1">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="add-to-order">Add to Order</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Order</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <h6>Order Details:</h6>
                    <ul id="modal-order-items"></ul>
                    <h6>Total: ฿<span id="modal-order-total"></span></h6>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submit-order">Submit Order</button>
                </div>
            </div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            let orderItems = {};
            let total = 0;
            let products = <?php echo json_encode($products); ?>;
             // ดึงข้อมูลจาก localStorage ถ้ามีข้อมูลที่บันทึกไว้
            let savedOrderItems = localStorage.getItem('orderItems');
            if (savedOrderItems) {
                orderItems = JSON.parse(savedOrderItems);
                updateOrderSummary();
            }

            function updateOrderSummary() {
                let $orderItems = $('#order-items');
                $orderItems.empty();
                total = 0;

                // บันทึก orderItems ลงใน localStorage
                localStorage.setItem('orderItems', JSON.stringify(orderItems));

                for (let productId in orderItems) {
                    let item = orderItems[productId];
                    let itemTotal = item.price * item.quantity;
                    total += itemTotal;

                    $orderItems.append(
                        `<li class="list-group-item d-flex justify-content-between align-items-center">
                            ${item.name} (x${item.quantity})
                            <span>
                                ฿${itemTotal.toFixed(2)}
                                <button class="btn btn-danger btn-sm ml-2 remove-item" data-product-id="${productId}">Remove</button>
                            </span>
                        </li>`
                    );
                }

                $('#order-total').text(total.toFixed(2));
            }

            $('.view-details').click(function() {
                let productId = $(this).data('product-id');
                let product = products.find(p => p.listproduct_id == productId);
                
                $('#modal-product-image').attr('src', '../picture_product/' + product.product_pic);
                $('#modal-product-name').text(product.product_name);
                $('#modal-product-category').text('หมวดหมู่: ' + product.category);
                $('#modal-product-price').text('ราคา: ฿' + parseFloat(product.price_set).toFixed(2));
                $('#modal-product-quantity-set').text('จำนวนสินค้า: ' + product.quantity_set);
                $('#modal-product-info').text(product.product_info);
                $('#modal-product-quantity').val(1);
                
                $('#add-to-order').data('product-id', productId);
                
                $('#productModal').modal('show');
            });

            $('#add-to-order').click(function() {
                let productId = $(this).data('product-id');
                let product = products.find(p => p.listproduct_id == productId);
                let quantity = parseInt($('#modal-product-quantity').val());

                if (orderItems[productId]) {
                    orderItems[productId].quantity += quantity;
                } else {
                    orderItems[productId] = {
                        name: product.product_name,
                        price: parseFloat(product.price_set),
                        quantity: quantity
                    };
                }

                updateOrderSummary();
                $('#productModal').modal('hide');
            });

            $(document).on('click', '.remove-item', function() {
                let productId = $(this).data('product-id');
                delete orderItems[productId];
                updateOrderSummary();
            });

            $('#confirm-order').click(function() {
                let $modalOrderItems = $('#modal-order-items');
                $modalOrderItems.empty();

                for (let productId in orderItems) {
                    let item = orderItems[productId];
                    let itemTotal = item.price * item.quantity;
                    $modalOrderItems.append(`<li>${item.name} (x${item.quantity}) - ฿${itemTotal.toFixed(2)}</li>`);
                }

                $('#modal-order-total').text(total.toFixed(2));
                $('#confirmationModal').modal('show');
            });

            $('#submit-order').click(function() {
                // แทนที่จะใช้ AJAX, เราจะส่งฟอร์มไปยัง submit_order.php
                let form = $('<form action="payment.php" method="post"></form>');
                form.append($('<input type="hidden" name="order_items">').val(JSON.stringify(orderItems)));
                form.append($('<input type="hidden" name="total">').val(total));
                form.append($('<input type="hidden" name="store_id">').val(<?php echo $store_id; ?>));
                
                $('body').append(form);
                form.submit();

                // Clear the order summary after submitting the form
                orderItems = {}; // Reset the order items
                localStorage.removeItem('orderItems'); // Remove the order from localStorage
                updateOrderSummary(); // Refresh the order summary on the page
            });
        });
        document.getElementById('menu-toggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
        document.getElementById('main-content').classList.toggle('sidebar-active');
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>