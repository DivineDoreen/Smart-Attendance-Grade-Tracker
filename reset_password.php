<?php
include __DIR__ . '/php/db.php';

error_log("Accessing reset_password.php with GET: " . print_r($_GET, true)); // Debug log

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email'], $_POST['t'], $_POST['h'], $_POST['password'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $timestamp = $_POST['t'];
    $hash = $_POST['h'];
    $password = $_POST['password'];
    $secret_key = 'your_secret_key_123'; // Must match the key in forgot_password.php

    error_log("POST data: email=$email, timestamp=$timestamp, hash=$hash"); // Debug log

    // Validate hash and timestamp
    $expected_hash = hash('sha256', $email . $timestamp . $secret_key);
    if ($hash !== $expected_hash) {
        $error = "Invalid reset link.";
    } elseif (time() - $timestamp > 3600) { // 1-hour expiry
        $error = "Reset link has expired.";
    } else {
        try {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (strlen($password) < 8) {
                    $error = "Password must be at least 8 characters long.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update_stmt = $conn->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
                    $update_stmt->execute([':password_hash' => $password_hash, ':id' => $user['id']]);
                    $success = "Password reset successfully. <a href='login.php'>Log in</a>.";
                    error_log("Password reset for user ID: {$user['id']}"); // Debug log
                }
            } else {
                $error = "Email not found.";
                error_log("Email not found: $email"); // Debug log
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Database error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'SF Pro Text', sans-serif;
            background-color: #FFFFFF;
            margin: 0;
            color: #3C2F2F;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .form-container {
            background-color: #FFFFFF;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            margin: 24px auto;
            box-sizing: border-box;
            text-align: center;
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            color: #3C2F2F;
            margin-bottom: 24px;
        }

        .error,
        .success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            text-align: center;
        }

        .error {
            background-color: #FFEBEE;
            color: #FF3B30;
        }

        .success {
            background-color: #E8F5E9;
            color: #34C759;
        }

        .success a {
            color: #34C759;
            text-decoration: underline;
        }

        .success a:hover {
            color: #2DB84C;
        }

        input[type="password"],
        input[type="hidden"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            color: #3C2F2F;
            background-color: #F5F5F5;
            box-sizing: border-box;
            margin-bottom: 16px;
        }

        input[type="password"]:focus {
            border-color: #34C759;
            outline: none;
            background-color: #FFFFFF;
        }

        button {
            padding: 12px;
            background-color: #34C759;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
            width: 100%;
        }

        button:hover {
            background-color: #2DB84C;
        }

        @media (max-width: 768px) {
            .form-container {
                padding: 24px;
                max-width: 90%;
            }

            h2 {
                font-size: 22px;
            }

            input[type="password"],
            button {
                padding: 10px;
                font-size: 14px;
            }

            .error,
            .success {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 16px;
                max-width: 95%;
            }

            h2 {
                font-size: 20px;
            }

            input[type="password"],
            button {
                padding: 8px;
                font-size: 13px;
            }

            .error,
            .success {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php elseif (isset($_GET['email'], $_GET['t'], $_GET['h']) || (isset($_POST['email'], $_POST['t'], $_POST['h']) && $error === "Password must be at least 8 characters long.")): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $_GET['email'] ?? ''); ?>">
                <input type="hidden" name="t" value="<?php echo htmlspecialchars($_POST['t'] ?? $_GET['t'] ?? ''); ?>">
                <input type="hidden" name="h" value="<?php echo htmlspecialchars($_POST['h'] ?? $_GET['h'] ?? ''); ?>">
                <input type="password" name="password" placeholder="Enter new password (8+ characters)" required>
                <button type="submit">Reset Password</button>
            </form>
        <?php else: ?>
            <p class="error">Invalid reset link.</p>
        <?php endif; ?>
    </div>
</body>
</html>