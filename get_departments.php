<?php
header('Content-Type: application/json');
include __DIR__ . '/php/db.php';

try {
    $faculty_id = $_GET['faculty_id'] ?? null;
    if (!$faculty_id) {
        echo json_encode([]);
        exit;
    }

    $sql = "SELECT id, name FROM departments WHERE faculty_id = :faculty_id";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':faculty_id' => $faculty_id]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($departments);
} catch (PDOException $e) {
    echo json_encode([]);
    error_log("Error in get_departments.php: " . $e->getMessage());
}
?>