<?php
session_start();
include('../config/db.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            if ($user['reset_password'] == 1) {
                $_SESSION['user_id'] = $user['user_id'];
                header('Location: reset_password.php');
                exit();
            } else {
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['store_id'] = $user['store_id'];
                 
                switch ($user['role']) {
                    case 'admin':
                        header('Location: ../page/admin/dashboard.php');
                        break;
                    case 'manager':
                        header('Location: ../page/manager/dashboard.php');
                        break;
                    case 'staff':
                        header('Location: ../page/staff/dashboard.php');
                        break;
                }
                exit();
            }
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบผู้ใช้งานนี้";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #2e8b57;
            font-family: 'Arial', sans-serif;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
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
    <div class="login-container">
        <div class="login-box">
            <div class="illustration">
                <img src="../auth/logo/Name.webp" alt="Coffee Logo">
                <h2 class="text-center mt-3">Welcome To Coffee</h2>
            </div>
            <div class="form-container">
                <h2 class="text-center mb-4">Sign In</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>

