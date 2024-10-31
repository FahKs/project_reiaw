<?php
// Database connection
include ('../../config/db.php');
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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
} else {
    header("Location: login.php");
    exit();
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                addUser($conn);
                break;
            case 'edit':
                editUser($conn);
                break;
            case 'delete':
                deleteUser($conn);
                break;
        }
    }
}

// Function to get all users
function getUsers($conn) {
    $sql = "SELECT u.*, s.store_name 
            FROM users u 
            LEFT JOIN stores s ON u.store_id = s.store_id";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all stores
function getStores($conn) {
    $sql = "SELECT * FROM stores";
    $result = $conn->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

/// Function to add a new user
function addUser($conn) {
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $tel_user = $_POST['tel_user'];
    $role = $_POST['role'];
    $store_id = ($_POST['store_id'] === "null") ? NULL : $_POST['store_id'];
    $reset_password = 1;

    // ตรวจสอบเงื่อนไข role และ store_id
    if ($role == 'admin' && $store_id !== NULL) {
        echo "สำหรับ ตำแหน่ง 'admin' ไม่สามารถเลือกสาขาที่อยู่ได้";
        exit();
    }
    if (($role == 'manager' || $role == 'staff') && $store_id === NULL) {
        echo "สำหรับ ตำแหน่ง 'manager' หรือ 'staff' ต้องเลือกสาขาที่อยู่";
        exit();
    }
    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($check_email);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo ('อีเมลนี้มีอยู่แล้ว');
        exit();
    }

     // proceed with user creation
     $sql = "INSERT INTO users (name, surname, email, password, tel_user, role, store_id, reset_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
     $stmt = $conn->prepare($sql);
     $stmt->bind_param("ssssssis", $name, $surname, $email, $password, $tel_user, $role, $store_id, $reset_password);
    
    if ($stmt->execute()) {
        echo "เพิ่มผู้ใช้สำเร็จ";
        exit();
    } else {
        echo "ผิดพลาด: " . $stmt->error;
        exit();
    }

}

// Function to edit a user
function editUser($conn) {
    $user_id = $_POST['user_id'];
    $name = $_POST['name'];
    $surname = $_POST['surname'];
    $tel_user = $_POST['tel_user'];
    $role = $_POST['role'];
    $store_id = ($_POST['store_id'] === "null") ? NULL : $_POST['store_id'];

     // ตรวจสอบเงื่อนไข role และ store_id
     if ($role == 'admin' && $store_id !== NULL) {
        echo "สำหรับ ตำแหน่ง 'admin' ไม่สามารถเลือกสาขาที่อยู่ได้";
        exit();
    }
    if (($role == 'manager' || $role == 'staff') && $store_id === NULL) {
        echo "สำหรับ ตำแหน่ง 'manager' หรือ 'staff' ต้องเลือกสาขาที่อยู่";
        exit();
    }
    
    $sql = "UPDATE users SET name=?, surname=?, tel_user=?, role=?, store_id=? WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssii", $name, $surname, $tel_user, $role, $store_id, $user_id);

    if ($stmt->execute()) {
        echo ('ข้อมูลผู้ใช้แก้ไขสำเร็จ');
        exit();
    } else {
        echo "แก้ไขผิดพลาด: " . $stmt->error;
        exit();
    }
    exit();
}

// Function to delete a user
function deleteUser($conn) {
    $user_id = $_POST['user_id'];
   
    $sql = "DELETE FROM users WHERE user_id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        echo "ลบผู้ใช้สำเร็จ";
        exit();
    } else {
        echo "ลบผู้ใช้ไม่สำเร็จ: " . $stmt->error;
        exit();
    }
    exit();
}

$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_param = "%$search%";
$search_query = "SELECT u.user_id, u.name, u.surname, u.email, u.tel_user, u.role, s.store_name, u.update_at
                 FROM users u
                 LEFT JOIN stores s ON u.store_id = s.store_id
                 WHERE (u.user_id LIKE ? OR u.name LIKE ?)";
$stmt = $conn->prepare($search_query);
$stmt->bind_param("ss", $search_param, $search_param);
$stmt->execute();
$search_result = $stmt->get_result();
$users = $search_result->fetch_all(MYSQLI_ASSOC);

$users = getUsers($conn);
$stores = getStores($conn);
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
        <h2>User Management System</h2>
        <form action="" method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by User ID or Name" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <button class="btn btn-danger" type="button" onclick="window.location.href='manage_user.php';">Back</button>
                </div>
            </div>
        </form>
        <button type="button" class="btn btn-primary mb-3" data-toggle="modal" data-target="#addUserModal">
            Add User
        </button>
        <?php if ($search_result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Store</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            <tbody>
                <?php foreach ($search_result as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                    <td><?php echo htmlspecialchars($user['surname']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['tel_user']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td><?php echo htmlspecialchars($user['store_name'] ?? 'No store'); ?></td>
                    <td><?php echo htmlspecialchars($user['update_at']); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-info edit-user" data-toggle="modal" data-target="#editUserModal" 
                        data-user='<?php echo json_encode($user); ?>'>Edit</button>
                        <button type="button" class="btn btn-sm btn-danger delete-user" data-user-id="<?php echo $user['user_id']; ?>">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <div class="alert alert-warning mt-3">
                ไม่พบผู้ใช้งานที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($search); ?>"
            </div>
        <?php endif; ?>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <input type="hidden" name="action" value="add">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="surname">Surname</label>
                            <input type="text" class="form-control" id="surname" name="surname" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="tel_user">Phone</label>
                            <input type="tel" class="form-control" id="tel_user" name="tel_user" pattern="[0-9]{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select class="form-control" id="role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="store_id">Store</label>
                            <select class="form-control" id="store_id" name="store_id">
                                <option value="null">No store</option>
                                <?php foreach ($stores as $store): ?>
                                <option value="<?php echo htmlspecialchars($store['store_id']); ?>"><?php echo htmlspecialchars($store['store_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addUserBtn">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="form-group">
                            <label for="edit_name">Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_surname">Surname</label>
                            <input type="text" class="form-control" id="edit_surname" name="surname" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_tel_user">Phone</label>
                            <input type="tel" class="form-control" id="edit_tel_user" name="tel_user" pattern="[0-9]{10}" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_role">Role</label>
                            <select class="form-control" id="edit_role" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="staff">Staff</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_store_id">Store</label>
                            <select class="form-control" id="edit_store_id" name="store_id">
                                <option value="null">No store</option>
                                <?php foreach ($stores as $store): ?>
                                <option value="<?php echo htmlspecialchars($store['store_id']); ?>">
                                    <?php echo htmlspecialchars($store['store_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="editUserBtn">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        // Add User
        $('#addUserForm').submit(function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), function(response) {
                    alert(response);
                    location.reload();
                });
            });

        // Edit User
        $('.edit-user').click(function() {
            var user = $(this).data('user');
            $('#edit_user_id').val(user.user_id);
            $('#edit_name').val(user.name);
            $('#edit_surname').val(user.surname);
            $('#edit_tel_user').val(user.tel_user);
            $('#edit_role').val(user.role);
            $('#edit_store_id').val(user.store_id);
        });
        
        $('#editUserForm').submit(function(e) {
                e.preventDefault();
                $.post('', $(this).serialize(), function(response) {
                    alert(response);
                    location.reload();
                });
            });

        // Delete User
        $('.delete-user').click(function() {
            if (confirm('Are you sure you want to delete this user?')) {
                var userId = $(this).data('user-id');
                $.post('', { action: 'delete', user_id: userId }, function(response) {
                        alert(response);
                        location.reload();
                    });
                }
            });
        });
        
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    
    </script>
</body>
</html>
