<?php
// Start the session
session_start();

// Include database connection
include __DIR__ . '/php/db.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Initialize variables
$error = "";
$success = "";

// Fetch existing classes for the dropdown
$classes = [];
try {
    $sql = "SELECT id, class_name FROM Classes";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching classes: " . $e->getMessage();
}

// Handle user registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    error_log("POST request received: " . print_r($_POST, true)); // Debug: Log all POST data

    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $department = $_POST['department'] ?? null;
    $faculty = $_POST['faculty'] ?? null;
    $matric_no = $role == 'student' ? trim($_POST['matric_no']) : null;
    $lecturer_id = $role == 'lecturer' ? trim($_POST['lecturer_id']) : null;
    $class_id = $_POST['class_id'] ?? null;
    $new_class_name = $role == 'lecturer' && !empty($_POST['new_class_name']) ? trim($_POST['new_class_name']) : null;

    // Validate required fields
    if (empty($name) || empty($email) || empty($password) || empty($role)) {
        $error = "Name, email, password, and role are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($role == 'student' && empty($matric_no)) {
        $error = "Matric Number is required for students.";
    } elseif ($role == 'lecturer' && empty($lecturer_id)) {
        $error = "Lecturer ID is required for lecturers.";
    } elseif ($role == 'lecturer' && empty($class_id) && empty($new_class_name)) {
        $error = "A class must be assigned or created for a lecturer.";
    } elseif (empty($department) || empty($faculty)) {
        $error = "Department and faculty are required.";
    } else {
        try {
            // Check for existing email
            $sql = "SELECT id FROM users WHERE email = :email";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $error = "Email already registered.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $verification_token = bin2hex(random_bytes(32));
                $is_verified = 0;

                error_log("Attempting to insert user: $name, $email, $role"); // Debug: Log before insert

                // Insert user (store department and faculty IDs)
                $sql = "INSERT INTO users (name, email, password_hash, role, verification_token, is_verified, department, faculty, matric_no, lecturer_id) 
                        VALUES (:name, :email, :password_hash, :role, :verification_token, :is_verified, :department, :faculty, :matric_no, :lecturer_id)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':role' => $role,
                    ':verification_token' => $verification_token,
                    ':is_verified' => $is_verified,
                    ':department' => $department,
                    ':faculty' => $faculty,
                    ':matric_no' => $matric_no,
                    ':lecturer_id' => $lecturer_id
                ]);

                $user_id = $conn->lastInsertId();
                error_log("User inserted with ID: $user_id"); // Debug: Log successful insert

                // Handle class assignment for lecturer
                if ($role == 'lecturer') {
                    if (!empty($new_class_name)) {
                        $sql = "INSERT INTO Classes (class_name, lecturer_id) VALUES (:class_name, :lecturer_id)";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':class_name' => $new_class_name, ':lecturer_id' => $user_id]);
                        error_log("New class created: $new_class_name for lecturer ID: $user_id");
                    } elseif (!empty($class_id)) {
                        $sql = "UPDATE Classes SET lecturer_id = :lecturer_id WHERE id = :class_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([':lecturer_id' => $user_id, ':class_id' => $class_id]);
                        error_log("Class ID $class_id assigned to lecturer ID: $user_id");
                    }
                }

                // Generate clickable verification link with subdirectory
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $base_path = dirname($_SERVER['PHP_SELF']) === '/' ? '' : dirname($_SERVER['PHP_SELF']);
                $verification_link = "$base_url$base_path/verify.php?token=$verification_token";
                $success = "User registered! Verification link: <a href='$verification_link'>Click here to verify your account</a>.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Registration error: " . $e->getMessage()); // Debug: Log database errors
        }
    }
}

// Fetch faculty and department names for display
$faculty_names = [];
$department_names = [];
try {
    // Fetch all faculties
    $sql = "SELECT id, name FROM faculties";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($faculties as $faculty) {
        $faculty_names[$faculty['id']] = $faculty['name'];
    }

    // Fetch all departments
    $sql = "SELECT id, name FROM departments";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($departments as $dept) {
        $department_names[$dept['id']] = $dept['name'];
    }
} catch (PDOException $e) {
    $error = "Error fetching faculty/department names: " . $e->getMessage();
}

// Fetch all users
try {
    $sql = "SELECT id, name, email, role, is_verified, department, faculty, matric_no, lecturer_id FROM users";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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

        h2 {
            font-size: 28px;
            font-weight: 600;
            color: #3C2F2F;
            margin: 0;
            text-align: center;
        }

        h3 {
            font-size: 20px;
            font-weight: 500;
            color: #3C2F2F;
            margin-bottom: 16px;
        }

        .error {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            background-color: #FFEBEE;
            color: #FF3B30;
            text-align: center;
        }

        .success {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
            background-color: #E8F5E9;
            color: #34C759;
            text-align: center;
        }

        .success a {
            color: #34C759;
            text-decoration: underline;
        }

        .success a:hover {
            color: #2DB84C;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #FFFFFF;
            margin: 16px 0;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #E0E0E0;
            text-align: left;
            font-size: 14px;
            color: #3C2F2F;
        }

        th {
            background-color: #F5F5F5;
            font-weight: 500;
        }

        tr:hover {
            background-color: #F9F9F9;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #3C2F2F;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            color: #3C2F2F;
            background-color: #F5F5F5;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #34C759;
            outline: none;
            background-color: #FFFFFF;
        }

        button {
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

        button:hover {
            background-color: #2DB84C;
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

        #filter-select,
        #filter-value {
            width: 100%;
            max-width: 300px;
            padding: 12px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            color: #3C2F2F;
            background-color: #F5F5F5;
            margin-bottom: 16px;
        }

        #filter-select:focus,
        #filter-value:focus {
            border-color: #34C759;
            outline: none;
            background-color: #FFFFFF;
        }

        #filter-value {
            display: none;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 24px;
                margin: 16px auto;
                max-width: 90%;
            }

            .dashboard-header h2 {
                font-size: 24px;
            }

            .logout-button {
                padding: 6px 12px;
                font-size: 13px;
            }

            h3 {
                font-size: 18px;
            }

            th, td {
                padding: 10px;
                font-size: 13px;
            }

            .form-group label {
                font-size: 13px;
            }

            .form-group input,
            .form-group select,
            #filter-select,
            #filter-value {
                padding: 10px;
                font-size: 13px;
            }

            button {
                padding: 10px;
                font-size: 14px;
            }

            .error,
            .success {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 16px;
                max-width: 95%;
            }

            .dashboard-header h2 {
                font-size: 20px;
            }

            .logout-button {
                padding: 6px 10px;
                font-size: 12px;
            }

            h3 {
                font-size: 16px;
            }

            th, td {
                padding: 8px;
                font-size: 12px;
            }

            .form-group label {
                font-size: 12px;
            }

            .form-group input,
            .form-group select,
            #filter-select,
            #filter-value {
                padding: 8px;
                font-size: 12px;
            }

            button {
                padding: 8px;
                font-size: 13px;
            }

            .error,
            .success {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>Admin Dashboard</h2>
            <a href="login.php?logout=1" class="logout-button">Logout</a>
        </div>
        <?php if (!empty($error)): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>

        <!-- User Registration Form -->
        <h3>Register New User</h3>
        <form method="POST" action="" onsubmit="console.log('Form submitting');">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" name="name" id="name" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="role">Role</label>
                <select name="role" id="role" required>
                    <option value="student">Student</option>
                    <option value="lecturer">Lecturer</option>
                </select>
            </div>
            <div class="form-group" id="common-fields" style="display: none;">
                <label for="faculty">Faculty <span style="color: #FF3B30;">*</span></label>
                <select name="faculty" id="faculty" required>
                    <option value="">Select Faculty</option>
                    <?php
                    try {
                        $sql = "SELECT id, name FROM faculties";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute();
                        $faculties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($faculties as $faculty) {
                            echo "<option value='{$faculty['id']}'>{$faculty['name']}</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option value=''>Error loading faculties</option>";
                    }
                    ?>
                </select>
                <label for="department">Department <span style="color: #FF3B30;">*</span></label>
                <select name="department" id="department" required>
                    <option value="">Select Department</option>
                    <?php
                    try {
                        $sql = "SELECT id, name FROM departments WHERE faculty_id = :faculty_id";
                        $stmt = $conn->prepare($sql);
                        $defaultFacultyId = $faculties[0]['id'] ?? null;
                        $stmt->execute([':faculty_id' => $defaultFacultyId]);
                        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($departments as $dept) {
                            echo "<option value='{$dept['id']}'>{$dept['name']}</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option value=''>Error loading departments</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group" id="lecturer-fields" style="display: none;">
                <label for="lecturer_id">Lecturer ID <span style="color: #FF3B30;">*</span></label>
                <input type="text" name="lecturer_id" id="lecturer_id">
                <label for="class_id">Existing Class</label>
                <select name="class_id" id="class_id">
                    <option value="">Select an existing class</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo $class['id']; ?>"><?php echo $class['class_name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="new_class_name">New Class Name (optional)</label>
                <input type="text" name="new_class_name" id="new_class_name" placeholder="e.g., CS101">
            </div>
            <div class="form-group" id="student-field" style="display: none;">
                <label for="matric_no">Matric Number <span style="color: #FF3B30;">*</span></label>
                <input type="text" name="matric_no" id="matric_no">
            </div>
            <button type="submit" name="register">Register</button>
        </form>

        <!-- User List -->
        <h3>Registered Users</h3>
        <select id="filter-select" onchange="filterUsers()">
            <option value="all">All</option>
            <option value="student">Students</option>
            <option value="lecturer">Lecturers</option>
            <option value="dept">By Department</option>
            <option value="faculty">By Faculty</option>
        </select>
        <input type="text" id="filter-value" placeholder="Enter Department/Faculty">
        <?php if (!empty($users)): ?>
            <table id="users-table">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Faculty</th>
                    <th>Matric No/ID</th>
                    <th>Verified</th>
                </tr>
                <?php foreach ($users as $user): ?>
                    <tr class="user-row" data-role="<?php echo $user['role']; ?>" 
                        data-dept="<?php echo isset($department_names[$user['department']]) ? htmlspecialchars($department_names[$user['department']]) : ''; ?>" 
                        data-fac="<?php echo isset($faculty_names[$user['faculty']]) ? htmlspecialchars($faculty_names[$user['faculty']]) : ''; ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><?php echo isset($department_names[$user['department']]) ? htmlspecialchars($department_names[$user['department']]) : 'N/A'; ?></td>
                        <td><?php echo isset($faculty_names[$user['faculty']]) ? htmlspecialchars($faculty_names[$user['faculty']]) : 'N/A'; ?></td>
                        <td><?php echo htmlspecialchars($user['matric_no'] ?? $user['lecturer_id'] ?? 'N/A'); ?></td>
                        <td><?php echo $user['is_verified'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No users found.</p>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const facultySelect = document.getElementById('faculty');
            const deptSelect = document.getElementById('department');
            const commonFields = document.getElementById('common-fields');
            const lecturerFields = document.getElementById('lecturer-fields');
            const studentField = document.getElementById('student-field');
            const roleSelect = document.getElementById('role');
            const lecturerIdInput = document.getElementById('lecturer_id');
            const matricNoInput = document.getElementById('matric_no');

            function updateFields() {
                const role = roleSelect.value;
                commonFields.style.display = role ? 'block' : 'none';
                lecturerFields.style.display = role === 'lecturer' ? 'block' : 'none';
                studentField.style.display = role === 'student' ? 'block' : 'none';
                // Toggle required attributes
                lecturerIdInput.required = role === 'lecturer';
                matricNoInput.required = role === 'student';
            }

            function updateDepartments() {
                const facultyId = facultySelect.value;
                if (!facultyId) {
                    deptSelect.innerHTML = '<option value="">Select Faculty First</option>';
                    return;
                }
                fetch(`get_departments.php?faculty_id=${facultyId}`)
                    .then(response => response.json())
                    .then(depts => {
                        deptSelect.innerHTML = '<option value="">Select Department</option>';
                        depts.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.name;
                            deptSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        deptSelect.innerHTML = '<option value="">Error loading departments</option>';
                        console.error('Error:', error);
                    });
            }

            roleSelect.addEventListener('change', updateFields);
            facultySelect.addEventListener('change', updateDepartments);
            updateFields(); // Trigger on load
        });

        function filterUsers() {
            const filter = document.getElementById('filter-select').value;
            const filterValue = document.getElementById('filter-value').value.toLowerCase();
            const filterInput = document.getElementById('filter-value');
            const rows = document.getElementsByClassName('user-row');

            filterInput.style.display = (filter === 'dept' || filter === 'faculty') ? 'inline-block' : 'none';

            for (let row of rows) {
                const role = row.getAttribute('data-role').toLowerCase();
                const dept = row.getAttribute('data-dept').toLowerCase();
                const fac = row.getAttribute('data-fac').toLowerCase();
                let display = false;

                if (filter === 'all') {
                    display = true;
                } else if (filter === 'student' || filter === 'lecturer') {
                    display = role === filter;
                } else if (filter === 'dept' && dept.includes(filterValue)) {
                    display = true;
                } else if (filter === 'faculty' && fac.includes(filterValue)) {
                    display = true;
                }

                row.style.display = display ? '' : 'none';
            }
        }

        document.getElementById('filter-value').addEventListener('input', filterUsers);
    </script>
</body>
</html>