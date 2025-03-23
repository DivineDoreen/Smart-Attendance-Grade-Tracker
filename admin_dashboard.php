<?php
// Start the session
session_start();

// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Include the database connection file
include __DIR__ . '/php/db.php';

// Fetch admin data
try {
    // Fetch all users
    $usersSql = "SELECT * FROM Users";
    $usersStmt = $conn->prepare($usersSql);
    $usersStmt->execute();
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all classes
    $classesSql = "SELECT * FROM Classes";
    $classesStmt = $conn->prepare($classesSql);
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        /* General Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            margin: 0;
            color: #333;
        }

        /* Dashboard Container */
        .dashboard-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 1200px;
            margin: 50px auto;
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .dashboard-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: #444;
        }

        /* Sections */
        .dashboard-section {
            margin-bottom: 30px;
        }

        .dashboard-section h2 {
            font-size: 22px;
            color: #6a11cb;
            margin-bottom: 15px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        table th {
            background-color: #f4f4f4;
            color: #444;
        }

        table tr:hover {
            background-color: #f9f9f9;
        }

        /* Buttons */
        .action-button {
            padding: 8px 16px;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .action-button:hover {
            background: linear-gradient(135deg, #2575fc, #6a11cb);
        }

        /* Logout Button */
        .logout-button {
            text-align: center;
            margin-top: 30px;
        }

        .logout-button a {
            padding: 10px 20px;
            background-color: #ff4d4d;
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .logout-button a:hover {
            background-color: #cc0000;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Welcome to Your Dashboard</h1>
        </div>

        <!-- User Management -->
        <div class="dashboard-section">
            <h2>User Management</h2>
            <?php if (!empty($users)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo $user['name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><?php echo $user['role']; ?></td>
                                <td>
                                    <button class="action-button">Edit</button>
                                    <button class="action-button">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No users found.</p>
            <?php endif; ?>
        </div>

        <!-- Class Management -->
        <div class="dashboard-section">
            <h2>Class Management</h2>
            <?php if (!empty($classes)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Class Name</th>
                            <th>Lecturer</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?php echo $class['id']; ?></td>
                                <td><?php echo $class['class_name']; ?></td>
                                <td><?php echo "Lecturer ID: " . $class['lecturer_id']; ?></td>
                                <td>
                                    <button class="action-button">Edit</button>
                                    <button class="action-button">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No classes found.</p>
            <?php endif; ?>
        </div>

        <!-- Logout Button -->
        <div class="logout-button">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>