<?php
// Include the database connection file
include __DIR__ . '/php/db.php';

// Initialize variables
$error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch user data from the database
    try {
        $stmt = $conn->prepare("SELECT * FROM `users` WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(); // Fetch a single row
        
        // Verify password and user existence
        if ($user && password_verify($password, $user['password_hash'])) {
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] == 'student') {
                header("Location: student_dashboard.php");
            } elseif ($user['role'] == 'lecturer') {
                header("Location: lecturer_dashboard.php");
            } elseif ($user['role'] == 'admin') {
                header("Location: admin_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }

        // Close the statement and clean up
        $stmt->closeCursor();  // Close the cursor to release the connection
        $stmt = null;  // Explicit cleanup

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
    <title>Login</title>
    <style>
        /* General Styles */
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

        /* Form Container */
        .form-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 350px;
            text-align: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form Header */
        .form-container h2 {
            margin-bottom: 20px;
            font-size: 24px;
            color: #444;
        }

        /* Input Fields */
        .form-container input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-container input:focus {
            border-color: #6a11cb;
            outline: none;
        }

        /* Submit Button */
        .form-container button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .form-container button:hover {
            background: linear-gradient(135deg, #2575fc, #6a11cb);
        }

        /* Error Message */
        .error {
            color: #ff4d4d;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* Signup Link */
        .signup-link {
            margin-top: 15px;
            font-size: 14px;
        }

        .signup-link a {
            color: #6a11cb;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Login</h2>
        <?php
        if (isset($error)) {
            echo "<p class='error'>$error</p>";
        }
        ?>
        <form action="login.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
        <p class="signup-link">Don't have an account? <a href="signup.php">Signup here</a></p>
    </div>
</body>
</html>