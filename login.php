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
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'SF Pro Text', sans-serif;
            background-color: #FFFFFF;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            color: #3C2F2F;
        }

        .form-container {
            background-color: #FFFFFF;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 360px;
            text-align: center;
        }

        .form-container h2 {
            font-size: 24px;
            font-weight: 600;
            color: #3C2F2F;
            margin-bottom: 24px;
        }

        .form-container input {
            width: 100%;
            padding: 12px 16px;
            margin-bottom: 16px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 16px;
            color: #3C2F2F;
            background-color: #F5F5F5;
            box-sizing: border-box;
        }

        .form-container input:focus {
            border-color: #34C759;
            outline: none;
            background-color: #FFFFFF;
        }

        .form-container button {
            width: 100%;
            padding: 12px;
            background-color: #34C759;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .form-container button:hover {
            background-color: #2DB84C;
        }

        .error {
            color: #FF3B30;
            font-size: 14px;
            margin-bottom: 16px;
            font-weight: 400;
        }

        .signup-link {
            font-size: 14px;
            color: #3C2F2F;
            margin-top: 16px;
        }

        .signup-link a {
            color: #34C759;
            text-decoration: none;
            font-weight: 500;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 24px;
                max-width: 300px;
            }

            .form-container h2 {
                font-size: 20px;
                margin-bottom: 20px;
            }

            .form-container input {
                font-size: 14px;
                padding: 10px 14px;
            }

            .form-container button {
                font-size: 14px;
                padding: 10px;
            }

            .error,
            .signup-link {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
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