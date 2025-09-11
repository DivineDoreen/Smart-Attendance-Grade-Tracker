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

// Persist class_id in session
if (!isset($_SESSION['last_class_id']) && !empty($_POST['class_id'])) {
    $_SESSION['last_class_id'] = $_POST['class_id'];
}
$lastClassId = $_SESSION['last_class_id'] ?? (isset($classes[0]['id']) ? $classes[0]['id'] : null);

// Handle QR code generation
$qrSuccess = $qrError = "";
if (isset($_POST['generate_qr'])) {
    $class_id = $_POST['class_id'] ?? $lastClassId;
    $_SESSION['last_class_id'] = $class_id;
    $lastClassId = $class_id;
    $session_code = bin2hex(random_bytes(16));
    $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));

    try {
        $sql = "INSERT INTO `sessions` (class_id, session_code, expiry_time) VALUES (:class_id, :session_code, :expiry_time)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':class_id', $class_id);
        $stmt->bindParam(':session_code', $session_code);
        $stmt->bindParam(':expiry_time', $expiry_time);
        $stmt->execute();

        include __DIR__ . '/php/phpqrcode/qrlib.php';
        $qrData = "ATTENDANCE_SYSTEM:" . $session_code;
        $qrFile = "qrcodes/" . $session_code . ".png";
        QRcode::png($qrData, $qrFile, 'L', 10, 2);

        $qrSuccess = "QR code generated successfully!";
    } catch (PDOException $e) {
        $qrError = "Error: " . $e->getMessage();
    }
}

// Handle grade management
$gradeSuccess = $gradeError = "";
if (isset($_POST['save_grade'])) {
    $student_id = $_POST['student_id'];
    $class_id = $lastClassId ?? (isset($classes[0]['id']) ? $classes[0]['id'] : null);
    if ($class_id === null) {
        $gradeError = "No class selected or available.";
    } else {
        $test_score = filter_var($_POST['test_score'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $examination_score = filter_var($_POST['examination_score'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $assignment_bonus_mark = filter_var($_POST['assignment_bonus_mark'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $total_grade = $test_score + $examination_score + $assignment_bonus_mark;

        if ($test_score === false || $examination_score === false || $assignment_bonus_mark === false) {
            $gradeError = "Invalid score values. Please enter valid numbers.";
        } else {
            try {
                $checkSql = "SELECT id FROM grades WHERE student_id = :student_id AND class_id = :class_id";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bindParam(':student_id', $student_id);
                $checkStmt->bindParam(':class_id', $class_id);
                $checkStmt->execute();
                if ($checkStmt->rowCount() > 0) {
                    $updateSql = "UPDATE grades SET test_score = :test_score, examination_score = :examination_score, assignment_bonus_mark = :assignment_bonus_mark, total_grade = :total_grade WHERE student_id = :student_id AND class_id = :class_id";
                    $stmt = $conn->prepare($updateSql);
                } else {
                    $updateSql = "INSERT INTO grades (student_id, class_id, test_score, examination_score, assignment_bonus_mark, total_grade) VALUES (:student_id, :class_id, :test_score, :examination_score, :assignment_bonus_mark, :total_grade)";
                    $stmt = $conn->prepare($updateSql);
                }
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':class_id', $class_id);
                $stmt->bindParam(':test_score', $test_score);
                $stmt->bindParam(':examination_score', $examination_score);
                $stmt->bindParam(':assignment_bonus_mark', $assignment_bonus_mark);
                $stmt->bindParam(':total_grade', $total_grade);
                $stmt->execute();

                $gradeSuccess = "Grade saved successfully!";
            } catch (PDOException $e) {
                $gradeError = "Error saving grade: " . $e->getMessage();
            }
        }
    }
}

// Fetch lecturer data
$lecturer_id = $_SESSION['user_id'];
try {
    $lecturerSql = "SELECT name FROM users WHERE id = :lecturer_id";
    $lecturerStmt = $conn->prepare($lecturerSql);
    $lecturerStmt->bindParam(':lecturer_id', $lecturer_id);
    $lecturerStmt->execute();
    $lecturer = $lecturerStmt->fetch(PDO::FETCH_ASSOC);
    $lecturerName = $lecturer ? $lecturer['name'] : 'Lecturer';

    $classesSql = "SELECT * FROM Classes WHERE lecturer_id = :lecturer_id";
    $classesStmt = $conn->prepare($classesSql);
    $classesStmt->bindParam(':lecturer_id', $lecturer_id);
    $classesStmt->execute();
    $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC);

    $attendanceSql = "SELECT a.*, u.name AS student_name 
                      FROM Attendance a 
                      JOIN Users u ON a.student_id = u.id 
                      WHERE a.class_id = :class_id AND u.role = 'student' 
                      ORDER BY a.timestamp DESC";
    $attendanceStmt = $conn->prepare($attendanceSql);
    $defaultClassId = $lastClassId ?? (isset($classes[0]['id']) ? $classes[0]['id'] : null);
    $attendanceStmt->bindParam(':class_id', $defaultClassId);
    $attendanceStmt->execute();
    $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

    $gradesSql = "SELECT g.*, u.name AS student_name 
                  FROM Grades g 
                  JOIN Users u ON g.student_id = u.id 
                  WHERE g.class_id = :class_id";
    $gradesStmt = $conn->prepare($gradesSql);
    $gradesStmt->bindParam(':class_id', $defaultClassId);
    $gradesStmt->execute();
    $grades = $gradesStmt->fetchAll(PDO::FETCH_ASSOC);

    $studentsSql = "SELECT DISTINCT u.id, u.name 
                    FROM Users u 
                    JOIN Attendance a ON u.id = a.student_id 
                    WHERE a.class_id = :class_id AND u.role = 'student'";
    $studentsStmt = $conn->prepare($studentsSql);
    $studentsStmt->bindParam(':class_id', $defaultClassId);
    $studentsStmt->execute();
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);
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
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #FFFFFF;
            margin: 0;
            padding: 0;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .dashboard-container {
            background-color: #FFFFFF;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 1200px;
            margin: 20px 0;
            transition: all 0.3s ease;
        }

        .dashboard-header {
            text-align: center;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: #4CAF50;
            font-weight: 600;
            margin: 0;
        }

        .logout-button-top a {
            padding: 10px 20px;
            background-color: #8B4513;
            color: #FFFFFF;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .logout-button-top a:hover {
            background-color: #6F2F0D;
        }

        .dashboard-section {
            margin-bottom: 25px;
        }

        .dashboard-section h2 {
            font-size: 22px;
            color: #8B4513;
            margin-bottom: 15px;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }

        .dashboard-section h2.minimizable::after {
            content: ' ▼';
        }

        .dashboard-section h2.minimizable.minimized::after {
            content: ' ▲';
        }

        .attendance-content {
            display: block;
        }

        .attendance-content.minimized {
            display: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #FFFFFF;
        }

        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #E0E0E0;
        }

        table th {
            background-color: #F5F5F5;
            color: #8B4513;
            font-weight: 500;
        }

        table tr:hover {
            background-color: #F9F9F9;
        }

        .qr-generator {
            text-align: center;
            margin-bottom: 20px;
        }

        .qr-generator button {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: #FFFFFF;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .qr-generator button:hover {
            background-color: #45A049;
        }

        .qr-image {
            width: 200px;
            height: 200px;
            margin: 20px auto;
            display: block;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
        }

        .grade-form {
            margin: 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .grade-form input {
            padding: 8px;
            border: 1px solid #E0E0E0;
            border-radius: 6px;
            font-size: 14px;
            width: 80px;
        }

        .grade-form button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: #FFFFFF;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .grade-form button:hover {
            background-color: #45A049;
        }

        .success, .error {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }

        .success {
            background-color: #E8F5E9;
            color: #4CAF50;
        }

        .error {
            background-color: #FFEBEE;
            color: #D32F2F;
        }

        @media (max-width: 768px) {
            .dashboard-container { padding: 15px; margin: 10px 0; width: 95%; }
            .dashboard-header h1 { font-size: 22px; }
            .logout-button-top a { padding: 8px 16px; font-size: 14px; }
            .dashboard-section h2 { font-size: 18px; }
            table th, table td { padding: 8px; font-size: 14px; }
            .qr-generator button { padding: 8px 16px; font-size: 14px; }
            .qr-image { width: 150px; height: 150px; }
            .grade-form input { width: 60px; font-size: 12px; }
            .grade-form button { padding: 6px 12px; font-size: 12px; }
        }

        @media (max-width: 480px) {
            .dashboard-container { width: 100%; margin: 5px 0; padding: 10px; }
            .dashboard-header h1 { font-size: 18px; }
            .logout-button-top a { padding: 6px 12px; font-size: 12px; }
            .dashboard-section h2 { font-size: 16px; }
            table th, table td { padding: 6px; font-size: 12px; }
            .qr-image { width: 120px; height: 120px; }
            .grade-form { flex-direction: column; gap: 5px; }
            .grade-form input { width: 100%; max-width: 80px; }
            .grade-form button { width: 100%; max-width: 120px; }
        }
    </style>
</head>
<body>
    <?php include 'nav.php'; ?>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($lecturerName); ?>!</h1>
            <div class="logout-button-top">
                <a href="logout.php">Logout</a>
            </div>
        </div>

        <!-- QR Code Generator -->
        <div class="dashboard-section qr-generator">
            <?php
            $className = "";
            if ($lastClassId) {
                $classNameSql = "SELECT class_name FROM Classes WHERE id = :class_id";
                $classNameStmt = $conn->prepare($classNameSql);
                $classNameStmt->bindParam(':class_id', $lastClassId);
                $classNameStmt->execute();
                $className = $classNameStmt->fetchColumn() ?: "Class $lastClassId";
            } elseif (!empty($classes)) {
                $className = $classes[0]['class_name'] ?: "Class " . $classes[0]['id'];
            }
            ?>
            <h2><?php echo htmlspecialchars($className); ?></h2>
            <?php if (isset($qrSuccess)): ?>
                <p class="success"><?php echo $qrSuccess; ?></p>
                <img src="<?php echo $qrFile; ?>" alt="QR Code" class="qr-image">
                <p>Session Code: <?php echo isset($session_code) ? $session_code : 'Not generated'; ?></p>
                <p>Expires at: <?php echo isset($expiry_time) ? $expiry_time : 'Not generated'; ?></p>
            <?php elseif (isset($qrError)): ?>
                <p class="error"><?php echo $qrError; ?></p>
            <?php else: ?>
                <p style="font-size: 36px; font-weight: 600; color: #8B4513; text-align: center;">No QR Code Generated Yet</p>
            <?php endif; ?>
            <form action="lecturer_dashboard.php" method="POST">
                <input type="hidden" name="class_id" value="<?php echo $lastClassId ?? (isset($classes[0]['id']) ? $classes[0]['id'] : ''); ?>">
                <button type="submit" name="generate_qr">Generate QR Code</button>
            </form>
        </div>

        <!-- Attendance Records -->
        <div class="dashboard-section">
            <h2 class="minimizable" onclick="this.classList.toggle('minimized'); document.querySelector('.attendance-content').classList.toggle('minimized');">Attendance Records</h2>
            <div class="attendance-content">
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
        </div>

        <!-- Grade Management -->
        <div class="dashboard-section">
            <h2>Grade Management</h2>
            <?php if (isset($gradeSuccess)): ?>
                <p class="success"><?php echo $gradeSuccess; ?></p>
            <?php elseif (isset($gradeError)): ?>
                <p class="error"><?php echo $gradeError; ?></p>
            <?php endif; ?>
            <?php if (!empty($students)): ?>
                <?php foreach ($students as $student): ?>
                    <?php
                    $gradeSql = "SELECT * FROM grades WHERE student_id = :student_id AND class_id = :class_id";
                    $gradeStmt = $conn->prepare($gradeSql);
                    $gradeStmt->bindParam(':student_id', $student['id']);
                    $gradeStmt->bindParam(':class_id', $defaultClassId);
                    $gradeStmt->execute();
                    $grade = $gradeStmt->fetch(PDO::FETCH_ASSOC);
                    $test_score = $grade ? $grade['test_score'] : 0;
                    $examination_score = $grade ? $grade['examination_score'] : 0;
                    $assignment_bonus_mark = $grade ? $grade['assignment_bonus_mark'] : 0;
                    $total_grade = $grade ? $grade['total_grade'] : 0;
                    ?>
                    <div class="grade-form" data-student-id="<?php echo $student['id']; ?>">
                        <span><?php echo htmlspecialchars($student['name']); ?>:</span>
                        <input type="number" class="test-score" value="<?php echo $test_score; ?>" min="0" max="30" placeholder="Test" required>
                        <input type="number" class="exam-score" value="<?php echo $examination_score; ?>" min="0" max="70" placeholder="Exam" required>
                        <input type="number" class="bonus-score" value="<?php echo $assignment_bonus_mark; ?>" min="0" placeholder="Bonus" required>
                        <button type="button" class="save-grade" onclick="saveGrade(this)">Save</button>
                        <span class="total-score">Total: <?php echo number_format($total_grade, 2); ?></span>
                        <form id="grade-form-<?php echo $student['id']; ?>" style="display: none;">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            <input type="hidden" name="class_id" value="<?php echo $defaultClassId; ?>">
                            <input type="hidden" name="test_score" class="hidden-test">
                            <input type="hidden" name="examination_score" class="hidden-exam">
                            <input type="hidden" name="assignment_bonus_mark" class="hidden-bonus">
                            <input type="hidden" name="save_grade" value="1">
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No students found for this class.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.grade-form').forEach(form => {
            const testInput = form.querySelector('.test-score');
            const examInput = form.querySelector('.exam-score');
            const bonusInput = form.querySelector('.bonus-score');
            const totalSpan = form.querySelector('.total-score');
            const saveButton = form.querySelector('.save-grade');
            const hiddenForm = form.querySelector('form');
            const hiddenTest = hiddenForm.querySelector('.hidden-test');
            const hiddenExam = hiddenForm.querySelector('.hidden-exam');
            const hiddenBonus = hiddenForm.querySelector('.hidden-bonus');

            function updateTotal() {
                const test = parseFloat(testInput.value) || 0;
                const exam = parseFloat(examInput.value) || 0;
                const bonus = parseFloat(bonusInput.value) || 0;
                const total = test + exam + bonus;
                totalSpan.textContent = `Total: ${number_format(total, 2)}`;
                hiddenTest.value = test;
                hiddenExam.value = exam;
                hiddenBonus.value = bonus;
            }

            [testInput, examInput, bonusInput].forEach(input => {
                input.addEventListener('input', updateTotal);
            });

            saveButton.addEventListener('click', () => {
                if (!testInput.value || !examInput.value || !bonusInput.value) {
                    alert('Please fill in all score fields.');
                    return;
                }
                const formData = new FormData(hiddenForm);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const success = doc.querySelector('.success');
                    const error = doc.querySelector('.error');
                    if (success) {
                        document.querySelector('.dashboard-section:last-child .success')?.remove();
                        document.querySelector('.dashboard-section:last-child').insertAdjacentHTML('afterbegin', success.outerHTML);
                        updateTotal(); // Refresh total after successful save
                    } else if (error) {
                        document.querySelector('.dashboard-section:last-child .error')?.remove();
                        document.querySelector('.dashboard-section:last-child').insertAdjacentHTML('afterbegin', error.outerHTML);
                    }
                    location.reload(); // Reload page to reflect saved data
                })
                .catch(error => console.error('Error:', error));
            });

            // Initial calculation
            updateTotal();
        });

        function number_format(number, decimals) {
            return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
    </script>
</body>
</html>