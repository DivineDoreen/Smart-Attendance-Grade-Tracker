<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/php/db.php';

$attendanceSuccess = $attendanceError = "";

// Handle form submission when QR code is scanned
// Handle form submission when QR code is scanned
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['session_code'])) {
    $scanned_code = $_POST['session_code'];
    
    // Remove the "ATTENDANCE_SYSTEM:" prefix from the scanned code
    $session_code = str_replace("ATTENDANCE_SYSTEM:", "", $scanned_code);
    
    $student_id = $_SESSION['user_id'];

    // Log the session code for debugging
    error_log("Scanned session_code (raw): " . $scanned_code);
    error_log("Processed session_code: " . $session_code);

    try {
        // Get the current server time for debugging
        $stmt = $conn->prepare("SELECT NOW() AS server_time");
        $stmt->execute();
        $current_time = $stmt->fetch(PDO::FETCH_ASSOC)['server_time'];
        error_log("Server time: " . $current_time);

        // Check if QR session is valid and not expired
        $sql = "SELECT * FROM Sessions WHERE session_code = :session_code AND expiry_time > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':session_code', $session_code);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log the session details for debugging
        if ($session) {
            error_log("Session found: " . print_r($session, true));
        } else {
            error_log("No session found for session_code: " . $session_code);
        }

        if ($session) {
            // Check if student already marked attendance
            $checkSql = "SELECT * FROM Attendance WHERE student_id = :student_id AND session_code = :session_code";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bindParam(':student_id', $student_id);
            $checkStmt->bindParam(':session_code', $session_code);
            $checkStmt->execute();

            if ($checkStmt->rowCount() == 0) {
                // Mark attendance
                $insertSql = "INSERT INTO Attendance (student_id, class_id, session_code) VALUES (:student_id, :class_id, :session_code)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bindParam(':student_id', $student_id);
                $insertStmt->bindParam(':class_id', $session['class_id']);
                $insertStmt->bindParam(':session_code', $session_code);
                $insertStmt->execute();

                $attendanceSuccess = "Attendance marked successfully!";
            } else {
                $attendanceError = "You've already marked attendance for this session.";
            }
        } else {
            $attendanceError = "Invalid or expired QR code.";
        }
    } catch (PDOException $e) {
        $attendanceError = "Error: " . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Scan QR Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
            background-color: #f5f5f5;
            margin: 0;
        }
        .scanner-container {
            max-width: 500px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        #qr-reader {
            width: 100%;
            max-width: 400px;
            margin: 20px auto;
            border: 2px solid #ddd;
            border-radius: 5px;
        }
        .btn {
            background: #6a11cb;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 0;
            width: 100%;
            max-width: 300px;
        }
        .btn:hover {
            background: #5a0fb0;
        }
        .success {
            color: green;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            color: red;
            padding: 10px;
            background: #ffebee;
            border-radius: 5px;
            margin: 10px 0;
        }
        #manual-scan {
            display: none;
            margin-top: 20px;
        }
        input[type="text"] {
            width: 100%;
            max-width: 300px;
            padding: 12px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .permission-message {
            color: #333;
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Scan Attendance QR Code</h1>

    <div class="scanner-container">
        <!-- QR scanner will appear here -->
        <div id="qr-reader"></div>

        <!-- Manual entry fallback (hidden by default) -->
        <div id="manual-scan">
            <p>Camera not available. Please enter the session code:</p>
            <form method="POST">
                <input type="text" name="session_code" placeholder="Paste session code here" required>
                <button type="submit" class="btn">Submit Attendance</button>
            </form>
        </div>

        <!-- Scanner control button -->
        <button id="start-scanner" class="btn">Start Scanner</button>

        <!-- Display success/error messages -->
        <?php if ($attendanceSuccess): ?>
            <p class="success"><?php echo htmlspecialchars($attendanceSuccess); ?></p>
        <?php elseif ($attendanceError): ?>
            <p class="error"><?php echo htmlspecialchars($attendanceError); ?></p>
        <?php endif; ?>
    </div>

    <!-- Load QR scanner library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <!-- Scanner implementation -->
    <script>
    // Ensure the page is fully loaded before running JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Html5Qrcode is available
        if (typeof Html5Qrcode === 'undefined') {
            const errorDiv = document.createElement('p');
            errorDiv.className = 'error';
            errorDiv.textContent = 'QR scanner library failed to load. Please try refreshing the page or use manual entry.';
            document.querySelector('.scanner-container').prepend(errorDiv);
            document.getElementById('manual-scan').style.display = 'block';
            document.getElementById('start-scanner').style.display = 'none';
            return;
        }

        const qrReader = new Html5Qrcode("qr-reader");
        const startButton = document.getElementById('start-scanner');
        const manualScan = document.getElementById('manual-scan');
        let isScanning = false;

        // Check if the page is served over HTTPS or localhost
        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
            const warningDiv = document.createElement('p');
            warningDiv.className = 'error';
            warningDiv.textContent = 'Camera access requires a secure connection (HTTPS). Please use a secure server or manual entry.';
            document.querySelector('.scanner-container').prepend(warningDiv);
            manualScan.style.display = 'block';
            startButton.style.display = 'none';
            return;
        }

        // Start/stop scanner when button clicked
        startButton.addEventListener('click', function() {
            if (isScanning) {
                stopScanner();
            } else {
                startScanner();
            }
        });

        // Function to start scanning
        function startScanner() {
            // Request camera permissions and start scanner
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    qrReader.start(
                        { facingMode: "environment" }, // Prefer rear camera
                        {
                            fps: 10, // Scan 10 times per second
                            qrbox: { width: 250, height: 250 } // Scanning area
                        },
                        (decodedText) => {
                            // QR code detected, stop scanner and submit
                            stopScanner();
                            submitAttendance(decodedText);
                        },
                        (error) => {
                            console.log("Scan error:", error);
                        }
                    ).then(() => {
                        isScanning = true;
                        startButton.textContent = "Stop Scanner";
                    }).catch(err => {
                        handleCameraError(err);
                    });
                } else {
                    handleCameraError("No cameras found on this device.");
                }
            }).catch(err => {
                handleCameraError(err);
            });
        }

        // Function to stop scanning
        function stopScanner() {
            qrReader.stop().then(() => {
                isScanning = false;
                startButton.textContent = "Start Scanner";
                qrReader.clear(); // Clear the scanner area
            }).catch(err => {
                console.error("Stop scanner error:", err);
            });
        }

        // Function to handle camera errors
        function handleCameraError(error) {
            console.error("Camera error:", error);
            const errorDiv = document.createElement('p');
            errorDiv.className = 'permission-message';
            errorDiv.textContent = 'Unable to access camera. Please grant camera permissions or use manual entry.';
            document.querySelector('.scanner-container').prepend(errorDiv);
            manualScan.style.display = 'block';
            startButton.textContent = "Start Scanner";
            isScanning = false;
        }

        // Function to submit attendance
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

        // Check camera permissions on page load
        navigator.permissions.query({ name: 'camera' }).then(permissionStatus => {
            if (permissionStatus.state === 'denied') {
                handleCameraError('Camera permission denied.');
            } else if (permissionStatus.state === 'prompt') {
                const promptDiv = document.createElement('p');
                promptDiv.className = 'permission-message';
                promptDiv.textContent = 'Please allow camera access when prompted to scan QR codes.';
                document.querySelector('.scanner-container').prepend(promptDiv);
            }
            // Start scanner automatically if permissions are granted
            if (permissionStatus.state === 'granted') {
                startScanner();
            }
        }).catch(err => {
            console.error("Permission check error:", err);
        });
    });
    </script>
</body>
</html>