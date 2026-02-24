<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$course = isset($_GET['course']) ? trim($_GET['course']) : '';
if ($course === '') {
    echo json_encode(['error' => 'Missing course']);
    exit;
}

try {
    if (strtolower($course) === 'all') {
        $sql = "SELECT id, name FROM users WHERE role = 'Student' ORDER BY name";
        $stmt = $conn->prepare($sql);
    } else {
        $sql = "SELECT id, name FROM users WHERE role = 'Student' AND course = ? ORDER BY name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $course);
    }

    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $students = [];
    while ($row = $res->fetch_assoc()) {
        $students[] = ['id' => (int)$row['id'], 'name' => $row['name']];
    }

    echo json_encode($students);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

?>
