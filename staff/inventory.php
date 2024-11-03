<?php  
session_start();
include('../../config/db.php');

// ตรวจสอบการเข้าสู่ระบบ
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้
$query = "SELECT u.name, u.surname, u.role, s.store_name, s.store_id 
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
    $store_id = $user['store_id'] ?? null;
    $store_name = $user['store_name'] ?? null;
} else {
    header("Location: login.php");
    exit();
}

// ฟังก์ชันสำหรับดึงสินค้าทั้งหมดพร้อมเช็คเงื่อนไขการแสดงสถานะ
function getProducts($conn) {
    $sql = "SELECT p.product_id, p.listproduct_id, p.store_id, p.expiration_date, p.status, p.quantity, p.location, p.receipt_date, p.updated_at, 
                   pi.product_name, pi.category
            FROM product p
            LEFT JOIN products_info pi ON p.listproduct_id = pi.listproduct_id
            WHERE p.status IN ('in_stock', 'expired', 'low_stock')";  // เงื่อนไขให้แสดงเฉพาะสถานะที่ต้องการ
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// ฟังก์ชันสำหรับการบันทึกการเบิกสินค้า
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'requisition') {
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // ตรวจสอบจำนวนสินค้าคงเหลือก่อนทำการเบิก
    $check_stock = "SELECT quantity FROM product WHERE product_id = ?";
    $stmt = $conn->prepare($check_stock);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();

    if ($product && $product['quantity'] >= $quantity) {
        // อัปเดตจำนวนสินค้า
        $new_quantity = $product['quantity'] - $quantity;
        $update_stock = "UPDATE product SET quantity = ? WHERE product_id = ?";
        $stmt = $conn->prepare($update_stock);
        $stmt->bind_param("ii", $new_quantity, $product_id);
        $stmt->execute();
        echo "เบิกสินค้าเรียบร้อยแล้ว";
    } else {
        echo "จำนวนสินค้าไม่เพียงพอ";
    }
    exit();
}

$products = getProducts($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Inventory</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="respontive.css">
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

    <!-- Sidebar Menu -->
    <div id="sidebar" class="p-3 text-white">
        <h4 class="text-center mb-4">Menu</h4>
        <a href="dashboard.php" class="d-flex align-items-center text-white mb-2">
            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
        </a>
        <a href="inventory.php" class="d-flex align-items-center text-white mb-2">
            <i class="fas fa-warehouse me-2"></i> Inventory
        </a>
    </div>

    <div class="container" id="main-content">
        <h1 class="mb-4">Inventory Requisition System</h1>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Stock Quantity</th>
                        <th>Status</th>
                        <th>EXP Date</th>
                        <th>Location</th>
                        <th>Receipt Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                        <td><?php echo htmlspecialchars($product['quantity']); ?></td>
                        <td><?php echo ucfirst($product['status']); ?></td> <!-- แสดงสถานะที่ดึงมา -->
                        <td><?php echo htmlspecialchars($product['expiration_date']); ?></td>
                        <td><?php echo htmlspecialchars($product['location']); ?></td>
                        <td><?php echo htmlspecialchars($product['receipt_date']);?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info requisition-product" data-toggle="modal" data-target="#requisitionModal" 
                            data-product='<?php echo json_encode($product); ?>'>เบิก</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal สำหรับเบิกสินค้า -->
    <div class="modal fade" id="requisitionModal" tabindex="-1" role="dialog" aria-labelledby="requisitionModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requisitionModalLabel">ระบุจำนวนสินค้าที่ต้องการเบิก</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="requisitionForm">
                        <input type="hidden" name="product_id" id="product_id">
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" required min="1">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="submitRequisition">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // เปิด Modal และตั้งค่า product_id
        $(document).on('click', '.requisition-product', function() {
            const product = $(this).data('product');
            $('#product_id').val(product.product_id);
        });

        // ส่งข้อมูลการเบิกสินค้า
        $('#submitRequisition').on('click', function() {
            const product_id = $('#product_id').val();
            const quantity = $('#quantity').val();

            if (quantity && product_id) {
                $.post('', { action: 'requisition', product_id: product_id, quantity: quantity }, function(response) {
                    alert(response);
                    $('#requisitionModal').modal('hide');
                    location.reload();
                });
            }
        }); 
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
