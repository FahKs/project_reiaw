<?php
session_start();
include('../config/db.php');

// Initialize variables to avoid undefined variable warnings
$error = "";
$success = "";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password === $confirm_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = '$hashed_password', reset_password = 0 WHERE user_id = $user_id";
        if ($conn->query($sql) === TRUE) {
            $success = "เปลี่ยนรหัสผ่านสำเร็จ!";
            header('Location: login.php');
            exit();
        } else {
            $error = "มีข้อผิดพลาดในการเปลี่ยนรหัสผ่าน: " . $conn->error;
        }
    } else {
        $error = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #2e8b57;
            font-family: 'Arial', sans-serif;
        }

        .reset-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .reset-box {
            background-color:white;
            width: 70%;
            height: 60vh;
            display: flex;
            align-items: center;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .illustration {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .illustration img {
            width: 40%;
            height: auto;
            border-radius: 50%;
            display: block;
            margin: 0 auto;
            box-shadow: 0px 4px 12px rgba(0, 0, 0, 0.1);
        }

        .illustration h2 {
            color: #2e8b57;
            font-weight: bold;
            text-align: center;
            margin-top: 20px;
        }

        .form-container {
            flex: 1;
            padding: 30px;
        }

        h2 {
            color: #2e8b57;
            font-weight: bold;
            text-align: center;
        }

        .form-control {
            border: none;
            border-bottom: 1px solid #90ee90;
            border-radius: 0;
            margin-bottom: 20px;
        }

        .btn-primary {
            background-color: #90ee90;
            border: none;
        }

        .btn-primary:hover {
            background-color: #77dd77;
        }

        .custom-checkbox {
            margin-bottom: 20px;
        }

        .alert {
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-box">
            <div class="illustration">
                <img src="../auth/logo/Name.webp" alt="Coffee Logo">
                <h2 class="text-center mt-3">Welcome To Coffee</h2>
            </div>
            <div class="form-container">
                <h2 class="text-center mb-4">Reset Password</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="text" name="new_password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Reset Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
