<?php
// Start the session
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include the database connection file
include __DIR__ . '/php/db.php';

// Fetch student data
$student_id = $_SESSION['user_id'];
try {
    // Fetch attendance records
    $attendanceSql = "SELECT * FROM Attendance WHERE student_id = :student_id ORDER BY timestamp DESC";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $attendanceStmt->bindParam(':student_id', $student_id);
    $attendanceStmt->execute();
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch grades
    $gradesSql = "SELECT * FROM Grades WHERE student_id = :student_id";
    $gradesStmt = $conn->prepare($gradesSql);
    $gradesStmt->bindParam(':student_id', $student_id);
    $gradesStmt->execute();
    $grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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

        /* Performance Alerts */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert.warning {
            background-color: #fff3cd;
            color: #856404;
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

        <!-- Attendance History -->
        <div class="dashboard-section">
            <h2>Attendance History</h2>
            <?php if (!empty($attendanceRecords)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Class</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($record['timestamp'])); ?></td>
                                <td><?php echo "Class ID: " . $record['class_id']; ?></td>
                                <td>Present</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No attendance records found.</p>
            <?php endif; ?>
        </div>

        <!-- Grade Reports -->
        <div class="dashboard-section">
            <h2>Grade Reports</h2>
            <?php if (!empty($grades)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Class</th>
                            <th>Test Score</th>
                            <th>Participation Score</th>
                            <th>Total Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td><?php echo "Class ID: " . $grade['class_id']; ?></td>
                                <td><?php echo $grade['test_score']; ?></td>
                                <td><?php echo $grade['participation_score']; ?></td>
                                <td><?php echo $grade['total_grade']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No grade records found.</p>
            <?php endif; ?>
        </div>

        <!-- Performance Alerts -->
        <div class="dashboard-section">
            <h2>Performance Alerts</h2>
            <div class="alert warning">
                <strong>Warning:</strong> Your attendance is below 75%. Please attend more classes.
            </div>
        </div>

        <!-- Logout Button -->
        <div class="logout-button">
            <a href="logout.php">Logout</a>
        </div>
    </div>
</body>
</html>