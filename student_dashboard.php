<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/php/db.php';

// Handle QR scan and attendance marking
$attendanceSuccess = $attendanceError = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['session_code'])) {
    $scanned_code = trim($_POST['session_code']);
    $session_code = str_replace("ATTENDANCE_SYSTEM:", "", $scanned_code);
    $student_id = $_SESSION['user_id'];

    error_log("Scanned (raw): $scanned_code, Processed: $session_code, Student ID: $student_id");

    try {
        $current_time = date('Y-m-d H:i:s');
        error_log("Server time: $current_time");

        $sql = "SELECT * FROM sessions WHERE session_code = :session_code AND expiry_time > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':session_code', $session_code);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            error_log("Valid session found: " . print_r($session, true));
        } else {
            $attendanceError = "Invalid or expired QR code.";
            error_log("Invalid/expired session for code: $session_code");
        }

        if ($session) {
            $checkSql = "SELECT * FROM attendance WHERE student_id = :student_id AND session_code = :session_code";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':student_id', $student_id);
            $checkStmt->bindParam(':session_code', $session_code);
            $checkStmt->execute();

            if ($checkStmt->rowCount() == 0) {
                $insertSql = "INSERT INTO attendance (student_id, class_id, session_code, timestamp) 
                             VALUES (:student_id, :class_id, :session_code, NOW())";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bindParam(':student_id', $student_id);
                $insertStmt->bindParam(':class_id', $session['class_id']);
                $insertStmt->bindParam(':session_code', $session_code);

                if ($insertStmt->execute()) {
                    $lastId = $conn->lastInsertId();
                    error_log("Attendance recorded, ID: $lastId, Student: $student_id, Class: " . $session['class_id']);
                    $attendanceSuccess = "Attendance marked successfully!";
                } else {
                    $errorInfo = $insertStmt->errorInfo();
                    error_log("Insert failed: " . print_r($errorInfo, true));
                    $attendanceError = "Failed to record attendance: " . $errorInfo[2];
                }
            } else {
                $attendanceError = "Already marked attendance for this session.";
                error_log("Duplicate for Student: $student_id, Code: $session_code");
            }
        }
    } catch (PDOException $e) {
        $attendanceError = "Database error: " . $e->getMessage();
        error_log("Error: " . $e->getMessage());
    }
}

// Fetch dashboard data and student name
$student_id = $_SESSION['user_id'];
$attendanceRates = [];
try {
    // Fetch student name
    $studentSql = "SELECT name FROM users WHERE id = :student_id";
    $studentStmt = $conn->prepare($studentSql);
    $studentStmt->bindParam(':student_id', $student_id);
    $studentStmt->execute();
    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
    $studentName = $student ? $student['name'] : 'Student';

    $classSql = "SELECT DISTINCT class_id FROM attendance WHERE student_id = :student_id";
    $classStmt = $conn->prepare($classSql);
    $classStmt->bindParam(':student_id', $student_id);
    $classStmt->execute();
    $classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($classes)) {
        foreach ($classes as $class_id) {
            // Total QR codes generated (all sessions for the class)
            $totalSql = "SELECT COUNT(*) FROM sessions WHERE class_id = :class_id";
            $totalStmt = $conn->prepare($totalSql);
            $totalStmt->bindParam(':class_id', $class_id);
            $totalStmt->execute();
            $totalGenerated = $totalStmt->fetchColumn();
            error_log("Class $class_id: Total Generated = $totalGenerated");

            // Scanned QR codes by student
            $scannedSql = "SELECT COUNT(*) FROM attendance a JOIN sessions s ON a.session_code = s.session_code 
                          WHERE a.student_id = :student_id AND a.class_id = :class_id";
            $scannedStmt = $conn->prepare($scannedSql);
            $scannedStmt->bindParam(':student_id', $student_id);
            $scannedStmt->bindParam(':class_id', $class_id);
            $scannedStmt->execute();
            $scannedSessions = $scannedStmt->fetchColumn();
            error_log("Class $class_id: Scanned Sessions = $scannedSessions");

            $attendanceRate = $totalGenerated > 0 ? ($scannedSessions / $totalGenerated) * 100 : 0;
            $attendanceRates[$class_id] = number_format($attendanceRate, 2);
            error_log("Class $class_id: Attendance Rate = $attendanceRate%");
        }
    }

    $attendanceSql = "SELECT * FROM attendance WHERE student_id = :student_id ORDER BY timestamp DESC LIMIT 5";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $attendanceStmt->bindParam(':student_id', $student_id);
    $attendanceStmt->execute();
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    $gradesSql = "SELECT g.*, c.class_name FROM grades g JOIN classes c ON g.class_id = c.id WHERE g.student_id = :student_id";
    $gradesStmt = $conn->prepare($gradesSql);
    $gradesStmt->bindParam(':student_id', $student_id);
    $gradesStmt->execute();
    $grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard data error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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

        .dashboard-container {
            background-color: #FFFFFF;
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 960px;
            margin: 24px auto;
            box-sizing: border-box;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 600;
            color: #3C2F2F;
            margin: 0;
        }

        .logout-button {
            padding: 8px 16px;
            background-color: #34C759;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
            text-decoration: none;
            text-align: center;
        }

        .logout-button:hover {
            background-color: #2DB84C;
        }

        .dashboard-section {
            margin-bottom: 24px;
        }

        .dashboard-section h2 {
            font-size: 20px;
            font-weight: 500;
            color: #3C2F2F;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #FFFFFF;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E0E0E0;
            font-size: 14px;
            color: #3C2F2F;
        }

        table th {
            background-color: #F5F5F5;
            font-weight: 500;
        }

        table tr:hover {
            background-color: #F9F9F9;
        }

        .attendance-rate {
            font-weight: 500;
            color: #34C759;
        }

        .attendance-rate.low {
            color: #FF3B30;
        }

        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            background-color: #FFF8E7;
            color: #3C2F2F;
            font-size: 14px;
        }

        .alert.warning {
            background-color: #FFF8E7;
            color: #3C2F2F;
        }

        .btn {
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
            max-width: 300px;
            text-align: center;
        }

        .btn:hover {
            background-color: #2DB84C;
        }

        .scanner-container {
            background-color: #FFFFFF;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            text-align: center;
        }

        #qr-reader {
            width: 100%;
            max-width: 400px;
            margin: 16px auto;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
        }

        #manual-scan {
            display: none;
            margin-top: 16px;
        }

        input[type="text"] {
            width: 100%;
            max-width: 300px;
            padding: 12px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            color: #3C2F2F;
            background-color: #F5F5F5;
            box-sizing: border-box;
            margin-bottom: 12px;
        }

        input[type="text"]:focus {
            border-color: #34C759;
            outline: none;
            background-color: #FFFFFF;
        }

        .success,
        .error {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            text-align: center;
        }

        .success {
            background-color: #E8F5E9;
            color: #34C759;
        }

        .error {
            background-color: #FFEBEE;
            color: #FF3B30;
        }

        .permission-message {
            background-color: #FFF8E7;
            color: #3C2F2F;
            padding: 12px;
            border-radius: 8px;
            margin: 12px 0;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 24px;
                margin: 16px auto;
                max-width: 90%;
            }

            .dashboard-header h1 {
                font-size: 24px;
            }

            .logout-button {
                padding: 6px 12px;
                font-size: 13px;
            }

            .dashboard-section h2 {
                font-size: 18px;
            }

            table th,
            table td {
                padding: 10px;
                font-size: 13px;
            }

            .scanner-container {
                padding: 12px;
            }

            #qr-reader {
                max-width: 100%;
            }

            .btn {
                padding: 10px;
                font-size: 14px;
            }

            input[type="text"] {
                padding: 10px;
                font-size: 13px;
            }

            .alert,
            .success,
            .error,
            .permission-message {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 16px;
                max-width: 95%;
            }

            .dashboard-header h1 {
                font-size: 20px;
            }

            .logout-button {
                padding: 6px 10px;
                font-size: 12px;
            }

            .dashboard-section h2 {
                font-size: 16px;
            }

            table th,
            table td {
                padding: 8px;
                font-size: 12px;
            }

            .btn {
                padding: 8px;
                font-size: 13px;
            }

            input[type="text"] {
                padding: 8px;
                font-size: 12px;
            }

            .alert,
            .success,
            .error,
            .permission-message {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($studentName); ?>!</h1>
            <a href="login.php?logout=1" class="logout-button">Logout</a>
        </div>

        <!-- QR Scanner Section -->
        <div class="dashboard-section">
            <h2>Mark Attendance</h2>
            <div class="scanner-container">
                <div id="qr-reader"></div>
                <div id="manual-scan">
                    <p>Camera not available. Enter session code:</p>
                    <form method="POST">
                        <input type="text" name="session_code" placeholder="Paste session code" required>
                        <button type="submit" class="btn">Submit</button>
                    </form>
                </div>
                <button id="start-scanner" class="btn">Start Scanner</button>
                <?php if ($attendanceSuccess): ?>
                    <p class="success"><?php echo htmlspecialchars($attendanceSuccess); ?></p>
                <?php elseif ($attendanceError): ?>
                    <p class="error"><?php echo htmlspecialchars($attendanceError); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attendance Rate -->
        <div class="dashboard-section">
            <h2>Attendance Rates</h2>
            <?php echo "<!-- Debug: Rates: " . print_r($attendanceRates, true) . ", Classes: " . print_r($classes, true) . " -->"; ?>
            <?php if (!empty($attendanceRates)): ?>
                <table>
                    <thead><tr><th>Class</th><th>Rate</th></tr></thead>
                    <tbody>
                        <?php foreach ($attendanceRates as $class_id => $rate): 
                            $classNameSql = "SELECT class_name FROM classes WHERE id = :class_id";
                            $classNameStmt = $conn->prepare($classNameSql);
                            $classNameStmt->bindParam(':class_id', $class_id);
                            $classNameStmt->execute();
                            $className = $classNameStmt->fetchColumn() ?: "Class $class_id";
                            $rateClass = $rate < 75 ? 'low' : '';
                        ?>
                            <tr><td><?php echo $className; ?></td><td class="attendance-rate <?php echo $rateClass; ?>"><?php echo $rate; ?>%</td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No attendance data yet. Scan a QR code to start tracking.</p>
            <?php endif; ?>
        </div>

        <!-- Attendance History -->
        <div class="dashboard-section">
            <h2>Attendance History</h2>
            <?php if (!empty($attendanceRecords)): ?>
                <table>
                    <thead><tr><th>Date</th><th>Class</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($attendanceRecords as $record): 
                            $classNameSql = "SELECT class_name FROM classes WHERE id = :class_id";
                            $classNameStmt = $conn->prepare($classNameSql);
                            $classNameStmt->bindParam(':class_id', $record['class_id']);
                            $classNameStmt->execute();
                            $className = $classNameStmt->fetchColumn() ?: "Class " . $record['class_id'];
                        ?>
                            <tr><td><?php echo date('Y-m-d H:i:s', strtotime($record['timestamp'])); ?></td><td><?php echo $className; ?></td><td>Present</td></tr>
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
                    <thead><tr><th>Class</th><th>Test</th><th>Exam</th><th>Bonus</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php foreach ($grades as $grade): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['class_name'] ?: "Class " . $grade['class_id']); ?></td>
                                <td><?php echo $grade['test_score'] !== null ? $grade['test_score'] : 'N/A'; ?></td>
                                <td><?php echo $grade['examination_score'] !== null ? $grade['examination_score'] : 'N/A'; ?></td>
                                <td><?php echo $grade['assignment_bonus_mark'] !== null ? $grade['assignment_bonus_mark'] : 'N/A'; ?></td>
                                <td><?php echo $grade['total_grade'] !== null ? number_format($grade['total_grade'], 2) : 'N/A'; ?></td>
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
            <?php $lowAttendance = false;
            foreach ($attendanceRates as $rate) if ($rate < 75) { $lowAttendance = true; break; }
            if ($lowAttendance): ?>
                <div class="alert warning"><strong>Warning:</strong> Attendance below 75% in at least one class.</div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Html5Qrcode === 'undefined') {
                document.getElementById('manual-scan').style.display = 'block';
                document.getElementById('start-scanner').style.display = 'none';
                return;
            }
            const qrReader = new Html5Qrcode("qr-reader");
            const startButton = document.getElementById('start-scanner');
            const manualScan = document.getElementById('manual-scan');
            let isScanning = false;

            if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                manualScan.style.display = 'block';
                startButton.style.display = 'none';
                return;
            }

            startButton.addEventListener('click', function() {
                if (isScanning) {
                    qrReader.stop().then(() => { isScanning = false; startButton.textContent = "Start Scanner"; qrReader.clear(); });
                } else {
                    Html5Qrcode.getCameras().then(devices => {
                        if (devices.length) {
                            qrReader.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } },
                                decodedText => { qrReader.stop().then(() => submitAttendance(decodedText)); },
                                error => console.log("Scan error:", error)
                            ).then(() => { isScanning = true; startButton.textContent = "Stop Scanner"; });
                        } else {
                            manualScan.style.display = 'block';
                            startButton.textContent = "Start Scanner";
                        }
                    }).catch(err => { manualScan.style.display = 'block'; startButton.textContent = "Start Scanner"; });
                }
            });

            function submitAttendance(sessionCode) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'session_code';
                input.value = sessionCode;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    </script>
</body>
</html>