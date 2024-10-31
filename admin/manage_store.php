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
    header("Location: login.php");
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
        <a href="notification-settings.php">Notification Settings</a>
        <a href="reports.php">Reports</a>
    </div>
    <div class="container-fluid" id="main-content">
        <h2>Store Management System</h2>
        <form action="" method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by Store ID or Name" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
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