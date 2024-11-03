<?php
session_start();
include('../../config/db.php');
  // เปลี่ยนเส้นทางการเชื่อมต่อฐานข้อมูล
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../../auth/login.php');  // เปลี่ยนเส้นทางการเช็ค role
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
    header("Location: ../../auth/login.php");
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                echo addStore($conn, $_POST['storeName'], $_POST['telStore'], $_POST['street'], $_POST['district'], $_POST['province'], $_POST['postalCode']);
                break;
            case 'edit':
                echo updateStore($conn, $_POST['storeId'], $_POST['storeName'], $_POST['telStore'], $_POST['street'], $_POST['district'], $_POST['province'], $_POST['postalCode']);
                break;
            case 'delete':
                echo deleteStore($conn, $_POST['storeId']);
                break;
        }
    }
}

// Function to get all stores with their addresses
function getStores($conn) {
    $sql = "SELECT s.store_id, s.store_name, s.tel_store, s.update_at, 
                   a.street, a.district, a.province, a.postal_code
            FROM stores s
            JOIN address a ON s.location_id = a.location_id";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to add a new store
function addStore($conn, $storeName, $telStore, $street, $district, $province, $postalCode) {
    // Check if store name already exists
    $stmt = $conn->prepare("SELECT store_id FROM stores WHERE store_name = ?");
    $stmt->bind_param("s", $storeName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "มีสาขานี้อยู่แล้ว";
        exit();
    }

    // Insert new address
    $stmt = $conn->prepare("INSERT INTO address (street, district, province, postal_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $street, $district, $province, $postalCode);
    $stmt->execute();
    $locationId = $conn->insert_id;

    // Insert new store
    $stmt = $conn->prepare("INSERT INTO stores (store_name, location_id, tel_store) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $storeName, $locationId, $telStore);
    if ($stmt->execute()){
        echo ('เพื่มสาขาสำเร็จ');
        exit();
    } else {
        echo ('ไม่สามารถเพิ่มสาขาได้'). $stmt->error;
        exit();
    }
    exit();

}

// Function to update a store
function updateStore($conn, $storeId, $storeName, $telStore, $street, $district, $province, $postalCode) {
    // Check if new store name already exists (excluding current store)
    $stmt = $conn->prepare("SELECT store_id FROM stores WHERE store_name = ? AND store_id != ?");
    $stmt->bind_param("si", $storeName, $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "มีสาขานี้ในระบบแล้ว";
        exit();
    }

    // Update address
    $stmt = $conn->prepare("UPDATE address a JOIN stores s ON a.location_id = s.location_id 
                            SET a.street = ?, a.district = ?, a.province = ?, a.postal_code = ? 
                            WHERE s.store_id = ?");
    $stmt->bind_param("ssssi", $street, $district, $province, $postalCode, $storeId);
    $stmt->execute();

    // Update store
    $stmt = $conn->prepare("UPDATE stores SET store_name = ?, tel_store = ? WHERE store_id = ?");
    $stmt->bind_param("ssi", $storeName, $telStore, $storeId);
    if ($stmt->execute()){
        echo ('ข้อมูลสาขาแก้ไขสำเร็จ');
        exit();
    } else {
        echo ('ไม่สามารถแก้ข้อมูลสาขาได้'). $stmt->error;
        exit();
    }
    exit();
}

// Function to delete a store
function deleteStore($conn, $storeId) {
    // Check if store is associated with any users
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE store_id = ?");
    $stmt->bind_param("i", $storeId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "ไม่สามารถลบสาขานี้: มีผู้ใช้งานอยู่ในสาขา";
        exit();
    }

    // Delete store (this will also delete the associated address due to ON DELETE CASCADE)
    $stmt = $conn->prepare("DELETE FROM stores WHERE store_id = ?");
    $stmt->bind_param("i", $storeId);
    if ($stmt->execute()){
        echo ('สาขานี้ถูกลบสำเร็จ');
        exit();
    } else {
        echo ('ไม่สามารถลบสาขานีี้ได้'). $stmt->error;
        exit();
    }
    exit();

   
    
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_param = "%$search%";
$search_query = "SELECT s.store_id, s.store_name, s.tel_store, s.update_at, 
                        a.street, a.district, a.province, a.postal_code
                 FROM stores s
                 JOIN address a ON s.location_id = a.location_id
                 WHERE (s.store_id LIKE ? OR s.store_name LIKE ?)";
$stmt = $conn->prepare($search_query);
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$search_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// การแบ่งหน้า Pages
$recordPerPage = 1;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordPerPage;

// ฟังก์ชันดึงข้อมูลสาขาและรายละเอียด
function fetchStoreDetails($conn, $offset, $recordPerPage) {
    $query = "SELECT s.store_id, s.store_name, s.location_id, s.tel_store 
              FROM stores s
              LIMIT ?, ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $offset, $recordPerPage);
    $stmt->execute();
    $result = $stmt->get_result();

    $stores = [];
    while ($row = $result->fetch_assoc()) {
        $stores[] = $row;
    }
    return $stores;
}

// ฟังก์ชันนับจำนวนสาขาทั้งหมด
function fetchCountStores($conn, $recordPerPage) {
    $query = "SELECT COUNT(*) AS total FROM stores";
    $result = $conn->query($query);

    if (!$result) {
        die('Query Failed: ' . mysqli_error($conn));
    }
    $total = $result->fetch_assoc()["total"];
    $totalPages = ceil($total / $recordPerPage);
    return $totalPages;
}

// เรียกใช้งานฟังก์ชันการแบ่งหน้า
$totalPages = fetchCountStores($conn, $recordPerPage);
$storeDetails = fetchStoreDetails($conn, $offset, $recordPerPage);
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
        <h2>Store Management System</h2>
        <form action="" method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by Store ID or Name" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <button class="btn btn-danger" type="button" onclick="window.location.href='manage_store.php';">Back</button>
                </div>
            </div>
        </form>
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addStoreModal">
            Add Store
        </button>
        <div class="table-responsive">
        <?php if (count($search_result) > 0): ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Store ID</th>
                        <th>Store Name</th>
                        <th>Telephone</th>
                        <th>Address</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_result as $store): ?>
                    <tr>
                        <td><?php echo $store['store_id']; ?></td>
                        <td><?php echo $store['store_name']; ?></td>
                        <td><?php echo $store['tel_store']; ?></td>
                        <td>
                            <?php 
                            echo $store['street'] . ', ' . $store['district'] . ', ' . 
                                 $store['province'] . ' ' . $store['postal_code']; 
                            ?>
                        </td>
                        <td><?php echo $store['update_at']; ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-info edit-store" data-toggle="modal" data-target="#editStoreModal" 
                                    data-store='<?php echo json_encode($store); ?>'>Edit</button>
                            <button type="button" class="btn btn-sm btn-danger delete-store" data-store-id="<?php echo $store['store_id']; ?>">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
        <div class="alert alert-warning mt-3">
            ไม่พบสาขาที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($search); ?>"
        </div>
        <?php endif; ?>
        </div>
    </div>
    <nav aria-label="Page navigation example">
    <ul class="pagination justify-content-center">
        <!-- ปุ่ม Previous -->
        <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if($page > 1) { echo "?page=" . ($page - 1); } else { echo '#'; } ?>">Previous</a>
        </li>
        
        <!-- แสดงหมายเลขหน้า -->
        <?php for($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?php if($i == $page) echo 'active'; ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
        
        <!-- ปุ่ม Next -->
        <li class="page-item <?php if($page >= $totalPages) echo 'disabled'; ?>">
            <a class="page-link" href="<?php if($page < $totalPages) { echo "?page=" . ($page + 1); } else { echo '#'; } ?>">Next</a>
        </li>
    </ul>
</nav>

    <!-- Add Store Modal -->
    <div class="modal fade" id="addStoreModal" tabindex="-1" role="dialog" aria-labelledby="addStoreModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStoreModalLabel">Add New Store</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="addStoreForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="storeName">Store Name</label>
                            <input type="text" class="form-control" id="storeName" name="storeName" required>
                        </div>
                        <div class="form-group">
                            <label for="telStore">Telephone</label>
                            <input type="tel" class="form-control" id="telStore" name="telStore" pattern="[0-9]{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="street">Street</label>
                            <input type="text" class="form-control" id="street" name="street" required>
                        </div>
                        <div class="form-group">
                            <label for="district">District</label>
                            <input type="text" class="form-control" id="district" name="district" required>
                        </div>
                        <div class="form-group">
                            <label for="province">Province</label>
                            <input type="text" class="form-control" id="province" name="province" required>
                        </div>
                        <div class="form-group">
                            <label for="postalCode">Postal Code</label>
                            <input type="text" class="form-control" id="postalCode" name="postalCode" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Store</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Store Modal -->
    <div class="modal fade" id="editStoreModal" tabindex="-1" role="dialog" aria-labelledby="editStoreModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStoreModalLabel">Edit Store</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editStoreForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="editStoreId" name="storeId">
                        <div class="form-group">
                            <label for="editStoreName">Store Name</label>
                            <input type="text" class="form-control" id="editStoreName" name="storeName" required>
                        </div>
                        <div class="form-group">
                            <label for="editTelStore">Telephone</label>
                            <input type="tel" class="form-control" id="editTelStore" name="telStore" pattern="[0-9]{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="editStreet">Street</label>
                            <input type="text" class="form-control" id="editStreet" name="street" required>
                        </div>
                        <div class="form-group">
                            <label for="editDistrict">District</label>
                            <input type="text" class="form-control" id="editDistrict" name="district" required>
                        </div>
                        <div class="form-group">
                            <label for="editProvince">Province</label>
                            <input type="text" class="form-control" id="editProvince" name="province" required>
                        </div>
                        <div class="form-group">
                            <label for="editPostalCode">Postal Code</label>
                            <input type="text" class="form-control" id="editPostalCode" name="postalCode" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Add Store Form Submission
            $('#addStoreForm').submit(function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), function(response) {
                    alert(response);
                    location.reload();
                });
            });

            // Edit Store
            $('.edit-store').click(function() {
                var store = $(this).data('store');
                $('#editStoreId').val(store.store_id);
                $('#editStoreName').val(store.store_name);
                $('#editTelStore').val(store.tel_store);
                $('#editStreet').val(store.street);
                $('#editDistrict').val(store.district);
                $('#editProvince').val(store.province);
                $('#editPostalCode').val(store.postal_code);
            });

            // Edit Store Form Submission
            $('#editStoreForm').submit(function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), function(response) {
                    alert(response);
                    location.reload();
                });
            });

            // Delete Store
            $('.delete-store').click(function() {
                if (confirm('Are you sure you want to delete this store?')) {
                    var storeId = $(this).data('store-id');
                    $.post('', { action: 'delete', storeId: storeId }, function(response) {
                        alert(response);
                        location.reload();
                    });
                }
            }); 
            document.getElementById('menu-toggle').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('active');
                document.getElementById('main-content').classList.toggle('sidebar-active');
            });
   
        });
    </script>
</body>
</html>