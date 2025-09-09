<?php
include __DIR__ . '/php/db.php';
$error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    try {
        $stmt = $conn->prepare("SELECT * FROM `users` WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $update_stmt = $conn->prepare("UPDATE users SET reset_token = :token, reset_expiry = :expiry WHERE id = :id");
            $update_stmt->execute([':token' => $token, ':expiry' => $expiry, ':id' => $user['id']]);
            $reset_link = "http://localhost/reset_password.php?token=" . $token;
            error_log("Reset link: " . $reset_link);
            $error = "Check error log for reset link.";
        } else {
            $error = "Email not found.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <style> /* Copy styles from login.php */ </style>
</head>
<body>
    <div class="form-container">
        <h2>Forgot Password</h2>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form action="forgot_password.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">Send Reset Link</button>
        </form>
    </div>
</body>
</html>