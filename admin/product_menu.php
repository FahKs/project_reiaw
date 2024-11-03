<?php
session_start();
include('../../config/db.php');

// เปลี่ยนเส้นทางการเชื่อมต่อฐานข้อมูล
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');  // เปลี่ยนเส้นทางการเช็ค role
    exit;
}

$user_id = $_SESSION['user_id'];

// ดึงข้อมูลผู้ใช้งาน
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

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["action"])) {
        switch ($_POST["action"]) {
            case "add":
                addProduct($conn);
                break;
            case "edit":
                editProduct($conn);
                break;
            case "delete":
                deleteProduct($conn);
                break;
            case "toggle":
                toggleVisibility($conn);
                break;
        }
    }
}

// ฟังก์ชันสำหรับดึงข้อมูลสินค้าทั้งหมด
// ฟังก์ชันสำหรับดึงข้อมูลสินค้าทั้งหมด (ค้นหาและตัวกรอง)
function getProducts($conn, $search = '', $category = '') {
    $sql = "SELECT * FROM products_info WHERE 1";  // เริ่มต้น SQL

    // ตรวจสอบเงื่อนไขการค้นหา
    if (!empty($search)) {
        $sql .= " AND (listproduct_id LIKE ? OR product_name LIKE ?)";
    }

    // ตรวจสอบตัวกรองหมวดหมู่
    if (!empty($category)) {
        $sql .= " AND category = ?";
    }

    $sql .= " ORDER BY updated_at DESC";
    
    $stmt = $conn->prepare($sql);

    // ผูกค่าเงื่อนไขตามการค้นหาและตัวกรอง
    if (!empty($search) && !empty($category)) {
        $search_param = '%' . $search . '%';
        $stmt->bind_param("sss", $search_param, $search_param, $category);
    } elseif (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bind_param("ss", $search_param, $search_param);
    } elseif (!empty($category)) {
        $stmt->bind_param("s", $category);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


// ฟังก์ชันสำหรับเพิ่มสินค้าใหม่
function addProduct($conn) {
    $product_name = $_POST['product_name'];
    $category = $_POST['category'];
    $price_set = $_POST['price_set'];
    $product_info = $_POST['product_info'];
    $quantity_set = $_POST['quantity_set'];
    $product_pic = '';

    if (isset($_FILES['product_pic']) && $_FILES['product_pic']['error'] == 0) {
        $target_dir = "../picture_product/"; // กำหนดโฟลเดอร์ที่เก็บรูปภาพ
        $target_file = $target_dir . basename($_FILES['product_pic']['name']);
        move_uploaded_file($_FILES['product_pic']['tmp_name'], $target_file); // บันทึกไฟล์ลงโฟลเดอร์
        $product_pic = basename($_FILES['product_pic']['name']); // เก็บชื่อไฟล์ในฐานข้อมูล
    }

    // ตรวจสอบชื่อสินค้าซ้ำ
    $check_sql = "SELECT * FROM products_info WHERE product_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $product_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo ('ชื่อสินค้าซ้ำ');
        exit();
    }
    
    $sql = "INSERT INTO products_info (product_name, category, price_set, product_info, quantity_set, product_pic) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsis", $product_name, $category, $price_set, $product_info, $quantity_set, $product_pic);
    
    if ($stmt->execute()) {
        echo ('เพิ่มสินค้าสำเร็จ');
        exit();
    } else {
        echo ('เกิดข้อผิดพลาดในการเพิ่มสินค้า');
        exit();
    }
}

// ฟังก์ชันสำหรับแก้ไขสินค้า
function editProduct($conn) {
    $listproduct_id = $_POST['listproduct_id'];
    $price_set = $_POST['price_set'];
    $product_info = $_POST['product_info'];
    $quantity_set = $_POST['quantity_set'];
    $product_pic = '';

    // ดึงข้อมูลสินค้าเดิมเพื่อเอาชื่อรูปภาพเก่ามาใช้ลบ
    $sql = "SELECT product_pic FROM products_info WHERE listproduct_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $listproduct_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $old_product = $result->fetch_assoc();
    $old_product_pic = $old_product['product_pic'];

    // ตรวจสอบว่ามีการอัพโหลดรูปภาพใหม่หรือไม่
    if (isset($_FILES['product_pic']) && $_FILES['product_pic']['error'] == 0) {
        // ตรวจสอบประเภทไฟล์และขนาดไฟล์
        $allowed_extensions = ['jpg', 'jpeg'];
        $file_extension = pathinfo($_FILES['product_pic']['name'], PATHINFO_EXTENSION);
        
        if (in_array($file_extension, $allowed_extensions) && $_FILES['product_pic']['size'] <= 500000) {
            $target_dir = "../picture_product/";
            $target_file = $target_dir . basename($_FILES['product_pic']['name']);
            
            // ลบรูปภาพเก่าออกก่อน
            if (!empty($old_product_pic) && file_exists($target_dir . $old_product_pic)) {
                unlink($target_dir . $old_product_pic);
            }
            
            move_uploaded_file($_FILES['product_pic']['tmp_name'], $target_file);
            $product_pic = basename($_FILES['product_pic']['name']); // เก็บชื่อไฟล์ในฐานข้อมูล
        } else {
            echo ('ไฟล์รูปภาพไม่ถูกต้อง');
            exit();
        }
    }

    // สร้าง SQL สำหรับแก้ไขข้อมูล
    if ($product_pic) {
        // หากมีการอัพโหลดรูปภาพใหม่
        $sql = "UPDATE products_info SET price_set = ?, product_info = ?, quantity_set = ?, product_pic = ? WHERE listproduct_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsisi", $price_set, $product_info, $quantity_set, $product_pic, $listproduct_id);
    } else {
        // หากไม่มีการอัพโหลดรูปภาพใหม่
        $sql = "UPDATE products_info SET price_set = ?, product_info = ?, quantity_set = ? WHERE listproduct_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dsii", $price_set, $product_info, $quantity_set, $listproduct_id);
    }

    if ($stmt->execute()) {
        echo ('แก้ไขสินค้าสำเร็จ');
        exit();
    } else {
        echo ('เกิดข้อผิดพลาดในการแก้ไขสินค้า');
        exit();
    }
}

// ฟังก์ชันสำหรับลบสินค้า
function deleteProduct($conn) {
    $listproduct_id = $_POST['listproduct_id'];
    $sql = "DELETE FROM products_info WHERE listproduct_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $listproduct_id);
    
    if ($stmt->execute()) {
        echo ('ลบสินค้าสำเร็จ');
        exit();
    } else {
        echo ('เกิดข้อผิดพลาดในการลบสินค้า');
        exit();
    }
}

// ฟังก์ชันสำหรับเปลี่ยนการมองเห็นสินค้า
function toggleVisibility($conn) {
    $listproduct_id = $_POST['listproduct_id'];
    $visible = $_POST['visible'];
    $sql = "UPDATE products_info SET visible = ? WHERE listproduct_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $visible, $listproduct_id);
    
    if ($stmt->execute()) {
        echo ('เปลี่ยนการมองเห็นสินค้าสำเร็จ');
        exit();
    } else {
        echo ('เกิดข้อผิดพลาดในการเปลี่ยนการมองเห็นสินค้า');
        exit();
    }
}


$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$products = getProducts($conn, $search, $category);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
    .product-image {
        max-width: 100px;
        max-height: 100px;
    }
        
    #sidebar {
        transform: translateX(-250px); /* ซ่อน Sidebar ออกไปทางซ้าย */
        transition: all 0.3s ease;
        width: 250px;
    }

    #sidebar.active {
        transform: translateX(0); /* แสดง Sidebar */
        }
    </style>
</head>
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

    <div class="container" id="main-content">
        <h1 class="mb-4">ระบบจัดการเมนูสินค้า</h1>
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
        <!-- ปุ่มเพิ่มสินค้า -->
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addProductModal">
            Add Product
        </button>
        <div class="table-responsive">
        <?php if (empty($products)): ?>
            <div class="alert alert-warning mt-3">
                ไม่พบสินค้าที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($search); ?>" และหมวดหมู่ "<?php echo htmlspecialchars($category); ?>"
            </div>
        <?php else: ?>
            <!-- แสดงตารางสินค้า -->
            <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อสินค้า</th>
                    <th>หมวดหมู่</th>
                    <th>ราคา</th>
                    <th>รายละเอียด</th>
                    <th>จำนวน</th>
                    <th>รูปภาพ</th>
                    <th>อัปเดตล่าสุด</th>
                    <th>การดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo $product['listproduct_id']; ?></td>
                    <td><?php echo $product['product_name']; ?></td>
                    <td><?php echo $product['category']; ?></td>
                    <td><?php echo $product['price_set']; ?></td>
                    <td><?php echo $product['product_info']; ?></td>
                    <td><?php echo $product['quantity_set']; ?></td>
                    <td>
                        <?php if ($product['product_pic']): ?>
                            <img src="../picture_product/<?php echo $product['product_pic']; ?>" alt="Product Image" class="product-image">
                        <?php else: ?>
                            No Image
                        <?php endif; ?>
                    </td>
                    <td><?php echo $product['updated_at']; ?></td>
                    <td>
                        <!-- ปุ่มแก้ไข -->
                        <button type="button" class="btn btn-sm btn-info edit-product" data-toggle="modal" data-target="#editProductModal" 
                        data-product='<?php echo json_encode($product); ?>'>Edit</button>
                        <!-- ปุ่มลบ -->
                        <button type="button" class="btn btn-sm btn-danger delete-product" 
                        data-product-id="<?php echo $product['listproduct_id']; ?>">delete</button>
                        <!-- ปุ่มสลับการแสดงผล -->
                        <button type="button" class="btn btn-sm <?php echo $product['visible'] ? 'btn-success' : 'btn-secondary'; ?> toggle-visibility"
                        data-product-id="<?php echo $product['listproduct_id']; ?>"data-visible="<?php echo $product['visible']; ?>"><?php echo $product['visible'] ? 'แสดง' : 'ซ่อน'; ?></button>
                    </td>
                    
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Modal เพิ่มสินค้า -->
    <div class="modal fade" id="addProductModal" tabindex="-1" role="dialog" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addProductModalLabel">เพิ่มสินค้าใหม่</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addProductForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="product_name">ชื่อสินค้า</label>
                            <input type="text" class="form-control" id="product_name" name="product_name" required>
                        </div>
                        <div class="form-group">
                            <label for="category">หมวดหมู่</label>
                            <select class="form-control" id="category" name="category" required>
                                <option value="">เลือกหมวดหมู่</option>
                                <option value="กาแฟ">กาแฟ</option>
                                <option value="นมและครีม">นมและครีม</option>
                                <option value="ไซรัปและน้ำเชื่อม ">ไซรัปและน้ำเชื่อม </option>
                                <option value="ผงเครื่องดื่มและส่วนผสมอื่นๆ">ผงเครื่องดื่มและส่วนผสมอื่นๆ</option>
                                <option value="ขนมและของว่าง ">ขนมและของว่าง </option>
                                <option value="อุปกรณ์การชงกาแฟ">อุปกรณ์การชงกาแฟ</option>
                                <option value="แก้วและภาชนะบรรจุ">แก้วและภาชนะบรรจุ</option>
                                <option value="สารให้ความหวานและสารแต่งกลิ่นรส">สารให้ความหวานและสารแต่งกลิ่นรส</option>
                                <option value="ผลิตภัณฑ์เพิ่มมูลค่า 5">ผลิตภัณฑ์เพิ่มมูลค่า 5</option>
                                <option value="อุปกรณ์เสิร์ฟ">อุปกรณ์เสิร์ฟ</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="price_set">ราคา</label>
                            <input type="number" class="form-control" id="price_set" name="price_set" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="product_info">รายละเอียดสินค้า</label>
                            <textarea class="form-control" id="product_info" name="product_info"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="quantity_set">จำนวน</label>
                            <input type="number" class="form-control" id="quantity_set" name="quantity_set" required>
                        </div>
                        <div class="form-group">
                            <label for="product_pic">รูปภาพสินค้า (เฉพาะ JPG, JPEG)</label>
                            <input type="file" class="form-control-file" id="product_pic" name="product_pic" accept=".jpg,.jpeg">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary" id="addProductBtn">เพิ่มสินค้า</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal แก้ไขสินค้า -->
    <div class="modal fade" id="editProductModal" tabindex="-1" role="dialog" aria-labelledby="editProductModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">แก้ไขสินค้า</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editProductForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_listproduct_id" name="listproduct_id">
                        <div class="form-group">
                            <label for="edit_price_set">ราคา</label>
                            <input type="number" class="form-control" id="edit_price_set" name="price_set" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_info">รายละเอียดสินค้า</label>
                            <textarea class="form-control" id="edit_product_info" name="product_info"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="edit_quantity_set">จำนวน</label>
                            <input type="number" class="form-control" id="edit_quantity_set" name="quantity_set" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_product_pic">รูปภาพสินค้า (เฉพาะ JPG, JPEG)</label>
                            <input type="file" class="form-control-file" id="edit_product_pic" name="product_pic" accept=".jpg,.jpeg">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                            <button type="submit" class="btn btn-primary" id="editProductBtn">>บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
    // เพิ่มสินค้า
        $('#addProductForm').submit(function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                type: 'POST',
                url: '<?php echo $_SERVER['PHP_SELF']; ?>', // POST ไปยังเพจเดียวกัน
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert(response);
                    location.reload();
                }
            });
        });

        // แก้ไขสินค้า
        $('.edit-product').click(function() {
            var product = $(this).data('product');
            $('#edit_listproduct_id').val(product.listproduct_id);
            $('#edit_price_set').val(product.price_set);
            $('#edit_product_info').val(product.product_info);
            $('#edit_quantity_set').val(product.quantity_set);
        });

        $('#editProductForm').submit(function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            $.ajax({
                type: 'POST',
                url: '<?php echo $_SERVER['PHP_SELF']; ?>',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    alert(response);
                    location.reload();
                }
            });
        });

        // ลบสินค้า
        $('.delete-product').click(function() {
            if (confirm('Are you sure you want to delete this product?')) {
                var productId = $(this).data('product-id');
                $.post('<?php echo $_SERVER['PHP_SELF']; ?>', { action: 'delete', listproduct_id: productId }, function(response) {
                    alert(response);
                    location.reload();
                });
            }
        });

        // สลับการแสดงผลสินค้า
        $('.toggle-visibility').click(function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var visible = $(this).data('visible');
            $.post('<?php echo $_SERVER['PHP_SELF']; ?>', { action: 'toggle', listproduct_id: productId, visible: visible ? 0 : 1 }, function(response) {
                alert(response);
                location.reload();
            });
        });

        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    });
    </script>
 </body>
</html>

