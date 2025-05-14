<?php
// Start the session
session_start();

// Include the database connection file
include __DIR__ . '/php/db.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lecturer') {
    header("Location: login.php");
    exit();
}

// Handle QR code generation
if (isset($_POST['generate_qr'])) {
    $class_id = $_POST['class_id']; // Assuming you have a dropdown to select a class
    $session_code = bin2hex(random_bytes(16)); // Unique session code
    $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour')); // QR expires in 1 hour

    // Store the session in the database
    try {
        $sql = "INSERT INTO `sessions` (class_id, session_code, expiry_time) VALUES (:class_id, :session_code, :expiry_time)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':session_code', $session_code);
        $stmt->bindParam(':expiry_time', $expiry_time);
        $stmt->execute();

        // Generate QR code
        include __DIR__ . '/php/phpqrcode/qrlib.php';
        $qrData = "ATTENDANCE_SYSTEM:" . $session_code;
        $qrFile = "qrcodes/" . $session_code . ".png";
        QRcode::png($qrData, $qrFile, 'L', 10, 2);

        $qrSuccess = "QR code generated successfully!";
    } catch (PDOException $e) {
        $qrError = "Error: " . $e->getMessage();
    }
}

// Fetch lecturer data
$lecturer_id = $_SESSION['user_id'];
try {
    // Fetch classes taught by the lecturer
    $classesSql = "SELECT * FROM Classes WHERE lecturer_id = :lecturer_id";
    $classesStmt = $conn->prepare($classesSql);
    $classesStmt->bindParam(':lecturer_id', $lecturer_id);
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch attendance records for the lecturer's classes
    $attendanceSql = "SELECT Attendance.*, Users.name AS student_name 
                      FROM Attendance 
                      JOIN Users ON Attendance.student_id = Users.id 
                      WHERE Attendance.class_id IN (
                          SELECT id FROM Classes WHERE lecturer_id = :lecturer_id
                      ) 
                      ORDER BY Attendance.timestamp DESC";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $attendanceStmt->bindParam(':lecturer_id', $lecturer_id);
    $attendanceStmt->execute();
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch grades for the lecturer's classes
    $gradesSql = "SELECT Grades.*, Users.name AS student_name 
                  FROM Grades 
                  JOIN Users ON Grades.student_id = Users.id 
                  WHERE Grades.class_id IN (
                      SELECT id FROM Classes WHERE lecturer_id = :lecturer_id
                  )";
    $gradesStmt = $conn->prepare($gradesSql);
    $gradesStmt->bindParam(':lecturer_id', $lecturer_id);
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
    <title>Lecturer Dashboard</title>
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

        /* QR Code Generator */
        .qr-generator {
            text-align: center;
            margin-bottom: 30px;
        }

        .qr-generator button {
            padding: 12px 24px;
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .qr-generator button:hover {
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

        <!-- QR Code Generator -->
<div class="dashboard-section qr-generator">
    <h2>Generate QR Code for Attendance</h2>
    <?php if (isset($qrSuccess)): ?>
        <p class="success"><?php echo $qrSuccess; ?></p>
        <img src="<?php echo $qrFile; ?>" alt="QR Code" style="width: 200px; height: 200px; margin: 20px auto; display: block;">
        <p>Session Code: <?php echo $session_code; ?></p>
        <p>Expires at: <?php echo $expiry_time; ?></p>
    <?php elseif (isset($qrError)): ?>
        <p class="error"><?php echo $qrError; ?></p>
    <?php endif; ?>
    <form action="lecturer_dashboard.php" method="POST">
        <select name="class_id" required style="width: 100%; padding: 10px; margin-bottom: 15px;">
            <?php foreach ($classes as $class): ?>
                <option value="<?php echo $class['id']; ?>"><?php echo $class['class_name']; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="generate_qr" class="action-button">Generate QR Code</button>
    </form>
</div>

        <!-- Attendance Records -->
        <div class="dashboard-section">
            <h2>Attendance Records</h2>
            <?php if (!empty($attendanceRecords)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($record['timestamp'])); ?></td>
                                <td><?php echo $record['student_name']; ?></td>
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
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Test Score</th>
                            <th>Participation Score</th>
                            <th>Total Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td><?php echo $grade['student_name']; ?></td>
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

        <!-- Logout Button -->
        <div class="logout-button">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <script>
        // Function to generate QR code (placeholder for now)
        function generateQRCode() {
            alert("QR Code generation functionality will be added soon!");
        }
    </script>
</body>
</html>