<?php 
session_start();
include('../../config/db.php');

// ตรวจสอบสิทธิ์และข้อมูลผู้จัดการ
if ($_SESSION['role'] !== 'manager' || $_SESSION['store_id'] === null) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$store_id = $_SESSION['store_id']; // กำหนด store_id จาก session โดยตรง

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
    $store_name = $user['store_name'];
} else {
    header("Location: login.php");
    exit();
}

$stmt->close();

// การแบ่งหน้า (Pagination)
$recordPerPages = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordPerPages;

// ฟังก์ชันดึงข้อมูลคำสั่งซื้อและรายละเอียดสินค้า
function fetchOrderDetails($conn, $store_id, $offset, $recordPerPages) {
    $query = "SELECT o.order_id, o.store_id, o.order_status, o.total_amount, o.order_date, 
                     o.shipping_date, o.delivered_date, o.cancel_info, 
                     d.detail_order_id, d.listproduct_id, d.quantity_set, d.price
              FROM orders o
              LEFT JOIN detail_orders d ON o.order_id = d.order_id
              WHERE o.store_id = ?
              LIMIT ?, ?";
    
    // เตรียมและผูกพารามิเตอร์กับ query
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $store_id, $offset, $recordPerPages);
    $stmt->execute();
    $orderResult = $stmt->get_result();

    $orders = [];
    while ($row = $orderResult->fetch_assoc()) {
        if (!isset($orders[$row['order_id']])) {
            $orders[$row['order_id']] = [
                'order_id' => $row['order_id'],
                'store_id' => $row['store_id'],
                'order_status' => $row['order_status'],
                'total_amount' => $row['total_amount'],
                'order_date' => $row['order_date'],
                'shipping_date' => $row['shipping_date'],
                'delivered_date' => $row['delivered_date'],
                'cancel_info' => $row['cancel_info'],
                'details' => []
            ];
        }

        $orders[$row['order_id']]['details'][] = [
            'detail_order_id' => $row['detail_order_id'],
            'listproduct_id' => $row['listproduct_id'],
            'quantity_set' => $row['quantity_set'],
            'price' => $row['price']
        ];
    }

    $stmt->close();
    return $orders;
}

// ฟังก์ชันสำหรับส่งออกข้อมูล
function fetchExports($conn, $store_id) {
    $query = "SELECT o.order_id, o.store_id, o.order_status, o.total_amount, o.order_date, 
                     o.shipping_date, o.delivered_date, o.cancel_info, 
                     d.detail_order_id, d.listproduct_id, d.quantity_set, d.price
              FROM orders o
              LEFT JOIN detail_orders d ON o.order_id = d.order_id
              WHERE o.store_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $orderResult = $stmt->get_result();

    $orders = [];
    while ($row = $orderResult->fetch_assoc()) {
        if (!isset($orders[$row['order_id']])) {
            $orders[$row['order_id']] = [
                'order_id' => $row['order_id'],
                'store_id' => $row['store_id'],
                'order_status' => $row['order_status'],
                'total_amount' => $row['total_amount'],
                'order_date' => $row['order_date'],
                'shipping_date' => $row['shipping_date'],
                'delivered_date' => $row['delivered_date'],
                'cancel_info' => $row['cancel_info'],
                'details' => []
            ];
        }

        $orders[$row['order_id']]['details'][] = [
            'detail_order_id' => $row['detail_order_id'],
            'listproduct_id' => $row['listproduct_id'],
            'quantity_set' => $row['quantity_set'],
            'price' => $row['price']
        ];
    }

    $stmt->close();
    return $orders;
}

// ฟังก์ชันนับจำนวนหน้าทั้งหมด
function fetchCountOrderDetails($conn, $store_id, $recordPerPages) {
    $query = "SELECT COUNT(DISTINCT o.order_id) AS total
              FROM orders o
              WHERE o.store_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()["total"];
    $totalPages = ceil($total / $recordPerPages);

    $stmt->close();
    return $totalPages;
}

// เรียกใช้ฟังก์ชันดึงข้อมูลและนับจำนวนหน้า
$totalPages = fetchCountOrderDetails($conn, $store_id, $recordPerPages);
$orderDetails = fetchOrderDetails($conn, $store_id, $offset, $recordPerPages);
$orderExports = fetchExports($conn, $store_id);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
       /* สไตล์สำหรับ Sidebar */
body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    background-color: #f5f5f5;
    color: #2c3e50;
    font-family: Arial, sans-serif;
}

#banner {
    background-color: #ffffff;
    border-bottom: 2px solid #2c3e50;
    padding: 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 1000;
    height: 60px;
}

#user-info {
    margin-left: auto;
    color: black;
    font-size: 14px;
}

#sidebar {
    width: 250px;
    background-color: #4caf50;
    color: #ffffff;
    padding-top: 20px;
    position: fixed;
    height: 100%;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease-in-out;
    top: 0;
    left: -250px;
    z-index: 1001;
}

#sidebar.active {
    transform: translateX(250px);
}

#sidebar a {
    color: #ffffff;
    text-decoration: none;
    padding: 15px;
    display: block;
    transition: background-color 0.3s;
}

#sidebar a:hover {
    background-color: #66bb6a;
}

#menu-toggle {
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1002;
    cursor: pointer;
    font-size: 20px;
    color: #4caf50;
}

#main-content {
    margin: 80px auto 0;
    padding: 30px;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 1400px;
    width: calc(100% - 40px);
}

.table-container {
    margin: 80px auto;
    padding: 30px;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    max-width: 1000px;
}

.table th {
    background-color: #4caf50;
    color: #ffffff;
}

.btn-primary {
    background-color: #4caf50;
    border-color: #4caf50;
}

.btn-primary:hover {
    background-color: #66bb6a;
    border-color: #66bb6a;
}

.btn-danger {
    background-color: #e53935;
    border-color: #e53935;
}
    </style>
</head>
<body>
<header id="banner">
        <a id="user-info">Name: <?php echo $name . ' ' . $surname; ?> | Role: <?php echo $role; ?></a>
        <button class="btn btn-danger" onclick="window.location.href='../../auth/logout.php'">Log Out</button>
    </header>

    <!-- Sidebar toggle button -->
    <div id="menu-toggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar Menu -->
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
<!-- Main content -->
<div class="container table-container">
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-success">
                <tr>
                    <th>Order ID</th>
                    <th>Store ID</th>
                    <th>Order status</th>
                    <th>Total amount</th>
                    <th>Order date</th>
                    <th>Shipping date</th>
                    <th>Delivered date</th>
                    <th>Cancel info</th>
                    <th>Product Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderDetails as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['order_id']) ?></td>
                        <td><?= htmlspecialchars($order['store_id']) ?></td>
                        <td><?= htmlspecialchars($order['order_status']) ?></td>
                        <td><?= htmlspecialchars($order['total_amount']) ?></td>
                        <td><?= htmlspecialchars($order['order_date']) ?></td>
                        <td><?= htmlspecialchars($order['shipping_date']) ?></td>
                        <td><?= htmlspecialchars($order['delivered_date']) ?></td>
                        <td><?= htmlspecialchars($order['cancel_info']) ?></td>
                        <td>
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Detail order ID</th>
                                        <th>Listproduct ID</th>
                                        <th>Quantity set</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['details'] as $detail): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($detail['detail_order_id']) ?></td>
                                            <td><?= htmlspecialchars($detail['listproduct_id']) ?></td>
                                            <td><?= htmlspecialchars($detail['quantity_set']) ?></td>
                                            <td><?= htmlspecialchars($detail['price']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <nav aria-label="Page navigation example">
  <ul class="pagination">
    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
      <a class="page-link" href="<?php if($page > 1) { echo "?page=" . ($page - 1); } else { echo '#'; } ?>">Previous</a>
    </li>
 <?php for($i = 1; $i <= $totalPages; $i++) { ?>
      <li class="page-item <?php if($i == $page) echo 'active'; ?>">
        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
      </li>
    <?php } ?>
    <li class="page-item <?php if($page >= $totalPages) echo 'disabled'; ?>">
      <a class="page-link" href="<?php if($page < $totalPages) { echo "?page=" . ($page + 1); } else { echo '#'; } ?>">Next</a>
    </li>
  </ul>
</nav>

    <div class="d-flex justify-content-end">
        <button class="btn btn-success me-2" onclick="exportToCSV()">Export</button>
        <a href="reports.php" class="btn btn-secondary">Back to Report Menu</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle sidebar
    document.getElementById('menu-toggle').onclick = function() {
        document.getElementById('sidebar').classList.toggle('active');
    };

    // Export to CSV function
    function exportToCSV() {
        const data = <?= json_encode($orderExports); ?>;
        const headers = [
            "Order ID", "Store ID", "Order Status", "Total Amount", "Order Date", 
            "Shipping Date", "Delivered Date", "Cancel Info", 
        ];
        const headers_detail = [
            "Detail Order ID", "List Product ID", "Quantity Set", "Price"
        ];
        console.log(data);
        dataArray = Object.values(data)
        console.log(dataArray);
        const csvRows = [[...headers,...headers_detail].join(',')];
        dataArray.forEach(order => {
            const row = headers.map(header => {
                    const key = header.toLowerCase().replace(/ /g, '_');
                    const value = order[key];
                    const escaped = (`${value || ''}`).replace(/"/g, '\\"');
                    return `"${escaped}"`;
                });
                order.details.forEach(detail => {
                const row_detail = [
                detail.detail_order_id,
                detail.listproduct_id,
                detail.quantity_set,
                detail.price
            ].map(value => `"${value || ''}"`);
            csvRows.push([...row, ...row_detail].join(','));
            });
        });

        const csvData = csvRows.join('\n');
        const blob = new Blob([csvData], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.setAttribute('download', 'orderlist_report.csv');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);

        URL.revokeObjectURL(url);
        console.log(data); 

    }
</script>
</body>
</html>
