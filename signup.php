<?php
// Start the session
session_start();

// Include database connection
include __DIR__ . '/php/db.php';

// Initialize variables
$error = "";
$success = "";

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Validate inputs
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        try {
            // Check if email already exists
            $sql = "SELECT id FROM users WHERE email = :email";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = "Email already registered.";
            } else {
                // Generate verification token
                $verification_token = bin2hex(random_bytes(32));
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $is_verified = 0;

                // Insert new user
                $sql = "INSERT INTO users (name, email, password_hash, role, verification_token, is_verified) 
                        VALUES (:name, :email, :password_hash, :role, :verification_token, :is_verified)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':role' => $role,
                    ':verification_token' => $verification_token,
                    ':is_verified' => $is_verified
                ]);

                $success = "User registered! Please ask the user to check their email for verification.";
                // TODO: Implement email sending with verification link (e.g., http://localhost/verify.php?token=$verification_token)

                $success = "User registered! Verification link: http://localhost/verify.php?token=" . $verification_token . 
                    " (Copy this link and open it in a browser to verify the account manually for now.)";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .form-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 350px;
            text-align: center;
        }
        .form-container h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #444;
        }
        .form-container input, .form-container select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-container button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        .form-container button:hover {
            background: linear-gradient(135deg, #2575fc, #6a11cb);
        }
        .error { color: #ff4d4d; margin-bottom: 15px; }
        .success { color: #4CAF50; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Register New User</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="name" placeholder="Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="student">Student</option>
                <option value="lecturer">Lecturer</option>
            </select>
            <button type="submit" name="register">Register</button>
        </form>
        <a href="admin_dashboard.php">Back to Admin Dashboard</a>
    </div>
</body>
</html>