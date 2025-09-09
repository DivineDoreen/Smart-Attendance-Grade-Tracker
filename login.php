<?php
session_start();

// Include database connection
include __DIR__ . '/php/db.php';

// Initialize variables
$error = "";

// Log the start of the script
error_log("Login script started at " . date('Y-m-d H:i:s'));

// Handle logout request
if (isset($_GET['logout'])) {
    error_log("Logout requested for user ID: " . ($_SESSION['user_id'] ?? 'none'));
    session_destroy();
    header("Location: login.php");
    exit();
}

// Check if user is already logged in and redirect based on role
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['role'];
    error_log("User already logged in with role: " . $user_role);
    if ($user_role == 'student' && file_exists('student_dashboard.php')) {
        header("Location: student_dashboard.php");
    } elseif ($user_role == 'lecturer' && file_exists('lecturer_dashboard.php')) {
        header("Location: lecturer_dashboard.php");
    } elseif ($user_role == 'admin' && file_exists('admin_dashboard.php')) {
        header("Location: admin_dashboard.php");
    } else {
        $error = "Dashboard not available for role: $user_role. Please contact support.";
    }
    exit();
}

// Handle login form submission
error_log("Checking POST request: " . ($_SERVER['REQUEST_METHOD'] == 'POST' ? 'POST' : 'Not POST') . 
          ", login button: " . (isset($_POST['login']) ? 'Set' : 'Not set'));
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    error_log("Received email: " . $email . ", password length: " . strlen($password));

    try {
        $sql = "SELECT * FROM `users` WHERE email = :email AND is_verified = 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Query executed, user found: " . ($user ? "Yes" : "No") . 
                  ", is_verified: " . ($user['is_verified'] ?? 'N/A'));

        if ($user && password_verify($password, $user['password_hash'])) {
            error_log("Password verified for user ID: " . $user['id']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'student' && file_exists('student_dashboard.php')) {
                header("Location: student_dashboard.php");
                error_log("Redirecting to student_dashboard.php");
            } elseif ($user['role'] == 'lecturer' && file_exists('lecturer_dashboard.php')) {
                header("Location: lecturer_dashboard.php");
                error_log("Redirecting to lecturer_dashboard.php");
            } elseif ($user['role'] == 'admin' && file_exists('admin_dashboard.php')) {
                header("Location: admin_dashboard.php");
                error_log("Redirecting to admin_dashboard.php");
            } else {
                $error = "Dashboard not available for role: " . $user['role'] . ". Please contact support.";
                session_destroy();
            }
            exit();
        } else {
            error_log("Password verification failed or user not found/verified. User data: " . print_r($user, true));
            $error = "Invalid email, password, or account not verified.";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "Database error: " . $e->getMessage();
    }
} else {
    error_log("No valid POST request received");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #333;
        }
        .form-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 350px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .form-container h2 { margin-bottom: 20px; font-size: 24px; color: #444; }
        .form-container input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; transition: border-color 0.3s ease; }
        .form-container input:focus { border-color: #6a11cb; outline: none; }
        .form-container button { width: 100%; padding: 12px; background: linear-gradient(135deg, #6a11cb, #2575fc); color: #fff; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.3s ease; }
        .form-container button:hover { background: linear-gradient(135deg, #2575fc, #6a11cb); }
        .error { color: #ff4d4d; margin-bottom: 15px; font-size: 14px; }
        .signup-link { margin-top: 15px; font-size: 14px; }
        .signup-link a { color: #6a11cb; text-decoration: none; font-weight: bold; }
        .signup-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="form-container">
        <h2>Login</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form action="login.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Login</button>
        </form>
        <p class="signup-link">
            Forgot Password? <a href="forgot_password.php">Reset here</a>
            <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] == 'admin'): ?>
                | <a href="admin_dashboard.php">Admin Dashboard</a>
            <?php endif; ?>
        </p>
    </div>
</body>
</html>