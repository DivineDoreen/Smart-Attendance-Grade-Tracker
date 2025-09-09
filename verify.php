<?php
// Include database connection
include __DIR__ . '/php/db.php';

// Check for verification token
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    try {
        $sql = "SELECT * FROM users WHERE verification_token = :token AND is_verified = 0";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $sql = "UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $user['id']]);
            $message = "Your account has been verified! You can now <a href='login.php'>log in</a>.";
        } else {
            $message = "Invalid or expired verification token.";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
    }
} else {
    $message = "No verification token provided.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .message { color: #333; }
    </style>
</head>
<body>
    <div class="message"><?php echo $message; ?></div>
</body>
</html>