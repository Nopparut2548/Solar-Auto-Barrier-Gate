<?php
require_once __DIR__ . '/bootstrap.php';

$conn = db();

$studentsResult = $conn->query('SELECT COUNT(*) AS total FROM students');
$logsResult = $conn->query('SELECT COUNT(*) AS total FROM access_logs');

if (!$studentsResult || !$logsResult) {
    json_response([
        'success' => false,
        'message' => 'เชื่อมต่อฐานข้อมูลสำเร็จ: ' . $conn->error
    ], 500);
}

json_response([
    'success' => true,
    'database' => DB_NAME,
    'students' => (int)$studentsResult->fetch_assoc()['total'],
    'logs' => (int)$logsResult->fetch_assoc()['total'],
    'serverTime' => date('c')
]);
?>
