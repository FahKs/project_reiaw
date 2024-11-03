<?php
session_start();
include('../../config/db.php');
  // เปลี่ยนเส้นทางการเชื่อมต่อฐานข้อมูล
  if ($_SESSION['role'] !== 'manager' || $_SESSION['store_id'] === null) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id'];


// ดึงข้อมูลผู้ใช้ปัจจุบัน
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
    $store_name = $user['store_name'];
} else {
    header("Location: ../../auth/login.php");
    exit();
}

// ค้นหาผู้ใช้
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_query = "SELECT u.user_id, u.name, u.surname, u.email, u.tel_user, u.role
                 FROM users u
                 WHERE u.store_id = ? AND (u.user_id LIKE ? OR u.name LIKE ?)";
$stmt = $conn->prepare($search_query);
$search_param = "%$search%";
$stmt->bind_param("iss", $store_id, $search_param, $search_param);
$stmt->execute();
$search_result = $stmt->get_result();

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="./respontive.css">
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
<div id="sidebar" class="bg-success vh-100 p-3 text-white" style="position: fixed; top: 0; left: 0; z-index: 1000;">
    <h4 class="text-center mb-4">Menu</h4>
    <a href="dashboard.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-tachometer-alt me-2"></i> Dashboard
    </a>
    <a href="show_user.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-user me-2"></i> Show User
    </a>
    <a href="order.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-box me-2"></i> Order
    </a>
    <a href="tracking.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-map-marker-alt me-2"></i> Tracking
    </a>
    <a href="scaning_product.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-barcode me-2"></i> Scanning Products
    </a>
    <a href="inventory.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-warehouse me-2"></i> Inventory
    </a>
    <a href="reports_ploblem.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-exclamation-triangle me-2"></i> Reports Problem
    </a>
    <a href="reports.php" class="d-flex align-items-center text-white mb-2">
        <i class="fas fa-file-alt me-2"></i> Reports
    </a>
</div>
<!-- Font Awesome and Bootstrap CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <div class="container" id="main-content">
        <h2>Users in <?php echo $store_name; ?></h2>
        <form action="" method="GET" class="mb-3">
            <div class="input-group">
                <input type="text" class="form-control" placeholder="Search by User ID or Name" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <div class="input-group-append">
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </div>
        </form>
        <?php if ($search_result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Name</th>
                        <th>Surname</th>
                        <th>Email</th>
                        <th>Telephone</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $search_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['user_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td><?php echo htmlspecialchars($row['surname']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['tel_user']); ?></td>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="alert alert-warning mt-3">
                ไม่พบผู้ใช้งานที่ตรงกับคำค้นหา "<?php echo htmlspecialchars($search); ?>"
            </div>
             <?php endif; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('main-content').classList.toggle('sidebar-active');
        });
    </script>
</body>
</html>