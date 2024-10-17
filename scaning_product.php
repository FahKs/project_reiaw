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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['barcode'])) {
    $barcode = trim($_POST['barcode']); // ทำความสะอาด input เพื่อตรวจสอบค่าที่ว่างเปล่า
    
    if (!empty($barcode)) { // ตรวจสอบว่าค่า barcode ไม่ว่างเปล่า
        $sql = "SELECT p.product_id, p.status, p.quantity, p.expiration_date, p.location, pi.product_name
                FROM product p
                JOIN products_info pi ON p.listproduct_id = pi.listproduct_id
                WHERE p.barcode = ? AND p.store_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $barcode, $store_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<h3>Product Information:</h3>";
            echo "<p><strong>Product Name:</strong> " . htmlspecialchars($row['product_name']) . "</p>";
            echo "<p><strong>Product ID:</strong> " . $row['product_id'] . "</p>";
            echo "<p><strong>Status:</strong> " . $row['status'] . "</p>";
            echo "<p><strong>Quantity:</strong> " . $row['quantity'] . "</p>";
            echo "<p><strong>Expiration Date:</strong> " . $row['expiration_date'] . "</p>";
            echo "<p><strong>Location:</strong> " . $row['location'] . "</p>";
            echo "<p><strong>Price:</strong> $" . number_format($row['price'], 2) . "</p>";
        } else {
            echo "<p>No product found for this barcode in your store.</p>";
        }
    } else {
        echo "<p>Barcode is empty. Please try again.</p>"; // ข้อความแจ้งเมื่อ barcode ว่างเปล่า
    }

    exit(); // ออกจากสคริปต์หลังจากส่งข้อมูลผลิตภัณฑ์
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="./respontive.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/quagga/0.12.1/quagga.min.js"></script>
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
        <a href="reports.php">Reports </a>
    </div>
    <div class="container" id="main-content">
        <h2 class="mt-4 mb-4">Scan Product</h2>
        <!-- ปุ่ม Start Camera และ Stop Camera -->
        <div class="mb-3">
            <button onclick="startScanner()" id="start-camera" class="btn btn-primary">Start Camera</button>
            <button onclick="stopScanner()" id="stop-camera" class="btn btn-danger">Stop Camera</button>
        </div>

        <!-- สแกนเนอร์ -->
        <div id="scanner-container" style="width: 100%; max-width: 640px; height: 480px;"></div>
        
        <!-- ฟอร์มการกรอกรหัส Barcode -->
        <div class="mt-3">
            <label for="barcode-input">Enter Barcode Manually:</label>
            <input type="text" id="barcode-input" class="form-control" placeholder="Enter barcode here">
            <button onclick="submitManualBarcode()" class="btn btn-secondary mt-2">Submit Barcode</button>
        </div>
        
        <input type="text" id="barcode-value" class="form-control mt-3" readonly>
        <div id="product-info" class="mt-4"></div>
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
            .then(response => response.text())
            .then(data => {
                document.getElementById('product-info').innerHTML = data;
                setTimeout(() => {
                    startScanner();
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                startScanner();
            });
        }

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
    </script>
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
