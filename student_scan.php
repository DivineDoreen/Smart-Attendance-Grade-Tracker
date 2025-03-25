<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header("Location: login.php");
    exit();
}

include __DIR__ . '/php/db.php';

$attendanceSuccess = $attendanceError = "";

// Handle scanned QR data (simulated for now - we'll add real scanning next)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['session_code'])) {
    $session_code = $_POST['session_code'];
    $student_id = $_SESSION['user_id'];

    try {
        // Check if session exists and isn't expired
        $sql = "SELECT * FROM Sessions WHERE session_code = :session_code AND expiry_time > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':session_code', $session_code);
        $stmt->execute();
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

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
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Scan QR Code</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 20px; }
        .scanner-container { margin: 30px auto; max-width: 500px; }
        #qr-reader { width: 100%; margin: 20px 0; border: 2px dashed #6a11cb; }
        .btn { 
            background: #6a11cb; color: white; border: none; 
            padding: 12px 24px; border-radius: 8px; cursor: pointer;
        }
        .success { color: green; }
        .error { color: red; }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<style>
    #qr-reader {
        width: 100% !important;
        margin: 0 auto !important;
    }
    #qr-reader__dashboard_section_csr {
        padding: 10px !important;
    }
</style>
</head>
<body>
    <h1>Scan Attendance QR Code</h1>
    
    <div class="scanner-container">
        <!-- QR Scanner Placeholder (we'll add real scanner next) -->
        <div id="qr-reader" style="width: 100%; margin: 20px 0;"></div>
        

        <!-- Simulate scanning for testing -->
        <form method="POST">
            <input type="text" name="session_code" placeholder="Enter session code manually">
            <button type="submit" class="btn">Mark Attendance</button>
        </form>

        <?php if ($attendanceSuccess): ?>
            <p class="success"><?php echo $attendanceSuccess; ?></p>
        <?php elseif ($attendanceError): ?>
            <p class="error"><?php echo $attendanceError; ?></p>
        <?php endif; ?>
    </div>

    <!-- We'll add JavaScript scanner library in the next step -->
     <!-- Add these scripts -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/dist/html5-qrcode.min.js"></script>
<script>
    const qrReader = new Html5Qrcode("qr-reader");
    
    function startScanner() {
        qrReader.start(
            { facingMode: "environment" }, // Use rear camera on mobile
            {
                fps: 10, // Scans per second
                qrbox: 250 // Scanning area size
            },
            (qrCode) => {
                // On successful scan
                console.log("Scanned:", qrCode);
                qrReader.stop();
                document.querySelector('input[name="session_code"]').value = qrCode;
                document.querySelector('form').submit(); // Auto-submit
            },
            (error) => {
                console.error("QR error:", error);
            }
        ).catch(err => alert("Camera error: " + err));
    }

    // Start scanner when page loads
    window.onload = startScanner;
</script>
</body>
</html>