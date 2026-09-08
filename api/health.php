<?php
require_once __DIR__ . '/bootstrap.php';
seed_database();
$conn = db();

$countStudents = $conn->query('SELECT COUNT(*) total FROM students')->fetch_assoc()['total'];
$countLogs = $conn->query('SELECT COUNT(*) total FROM access_logs')->fetch_assoc()['total'];

json_response([
    'success' => true,
    'database' => DB_NAME,
    'students' => (int)$countStudents,
    'logs' => (int)$countLogs,
    'serverTime' => date('c')
]);
?>
