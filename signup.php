<?php
// Include the database connection file
include __DIR__ . '/php/db.php';

// Initialize variables
$error = "";
$success = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Input validation (basic checks)
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if the email already exists
        try {
            $checkEmailSql = "SELECT email FROM Users WHERE email = :email";
            $checkEmailStmt = $conn->prepare($checkEmailSql);
            $checkEmailStmt->bindParam(':email', $email);
            $checkEmailStmt->execute();

            if ($checkEmailStmt->rowCount() > 0) {
                // Email already exists
                $error = "Email already registered. Please use a different email.";
            } else {
                // Hash the password for security
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert user data into the database
                $insertSql = "INSERT INTO Users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bindParam(':name', $name);
                $insertStmt->bindParam(':email', $email);
                $insertStmt->bindParam(':password_hash', $password_hash);
                $insertStmt->bindParam(':role', $role);
                $insertStmt->execute();

                // Add cleanup after successful insert
                $insertStmt->closeCursor(); // Close the statement after execution
                $insertStmt = null; // Explicit cleanup

                $success = "Registration successful!";
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup</title>
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
        .form-container input, .form-container select {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-container input:focus, .form-container select:focus {
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

        /* Error and Success Messages */
        .error {
            color: #ff4d4d;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .success {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 14px;
        }

        /* Link to Login Page */
        .login-link {
            margin-top: 15px;
            font-size: 14px;
        }

        .login-link a {
            color: #6a11cb;
            text-decoration: none;
            font-weight: bold;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Signup</h2>
        <?php
        if (isset($error)) {
            echo "<p class='error'>$error</p>";
        }
        if (isset($success)) {
            echo "<p class='success'>$success</p>";
        }
        ?>
        <form action="signup.php" method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role" required>
                <option value="student">Student</option>
                <option value="lecturer">Lecturer</option>
                <option value="admin">Admin</option>
            </select>
            <button type="submit">Signup</button>
        </form>
        <p class="login-link">Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>