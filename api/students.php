<?php
require_once __DIR__ . '/bootstrap.php';
seed_database();

$conn = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function student_row(array $row): array {
    return [
        'id' => (int)$row['id'],
        'studentId' => $row['student_id'],
        'name' => $row['name'],
        'faculty' => $row['faculty'] ?? '',
        'status' => $row['status'],
        'rfidUid' => $row['rfid_uid'] ?? '' // เก็บไว้สำหรับฝั่งระบบ/ESP32 แต่ไม่ส่งไปแสดงในหน้าเว็บ
    ];
}

if ($method === 'GET') {
    $result = $conn->query('SELECT id, student_id, name, faculty, status, rfid_uid FROM students ORDER BY id ASC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = student_row($row);
    }
    json_response(['success' => true, 'students' => $rows]);
}

$data = request_json();

if ($method === 'POST') {
    $name = trim((string)($data['name'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $faculty = trim((string)($data['faculty'] ?? ''));
    $status = in_array(($data['status'] ?? 'active'), ['active', 'suspended'], true) ? $data['status'] : 'active';
    $rfidUid = normalize_uid((string)($data['rfidUid'] ?? ''));

    if ($name === '' || $studentId === '' || $rfidUid === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลนักศึกษาให้ครบ'], 422);
    }

    $check = $conn->prepare('SELECT id, student_id, rfid_uid, name FROM students WHERE student_id = ? OR rfid_uid = ? LIMIT 1');
    $check->bind_param('ss', $studentId, $rfidUid);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();
    if ($existing) {
        $field = $existing['student_id'] === $studentId ? 'รหัสนักศึกษา' : 'RFID UID';
        json_response(['success' => false, 'message' => $field . ' นี้มีในระบบแล้ว'], 409);
    }

    $stmt = $conn->prepare('INSERT INTO students (student_id, name, faculty, rfid_uid, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssss', $studentId, $name, $faculty, $rfidUid, $status);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    json_response(['success' => true, 'message' => 'เพิ่มนักศึกษาเรียบร้อยแล้ว', 'student' => [
        'id' => $id, 'studentId' => $studentId, 'name' => $name, 'faculty' => $faculty, 'status' => $status, 'rfidUid' => $rfidUid
    ]], 201);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'message' => 'ไม่พบรหัสข้อมูลนักศึกษา'], 400);
}

if ($method === 'PUT') {
    $name = trim((string)($data['name'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $faculty = trim((string)($data['faculty'] ?? ''));
    $status = in_array(($data['status'] ?? 'active'), ['active', 'suspended'], true) ? $data['status'] : 'active';

    if ($name === '' || $studentId === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลนักศึกษาให้ครบ'], 422);
    }

    $check = $conn->prepare('SELECT id FROM students WHERE student_id = ? AND id <> ? LIMIT 1');
    $check->bind_param('si', $studentId, $id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        json_response(['success' => false, 'message' => 'รหัสนักศึกษานี้มีในระบบแล้ว'], 409);
    }
    $check->close();

    $stmt = $conn->prepare('UPDATE students SET student_id = ?, name = ?, faculty = ?, status = ? WHERE id = ?');
    $stmt->bind_param('ssssi', $studentId, $name, $faculty, $status, $id);
    $stmt->execute();
    $stmt->close();

    json_response(['success' => true, 'message' => 'แก้ไขข้อมูลนักศึกษาเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    if ($deleted === 0) {
        json_response(['success' => false, 'message' => 'ไม่พบข้อมูลนักศึกษา'], 404);
    }
    json_response(['success' => true, 'message' => 'ลบข้อมูลนักศึกษาแล้ว']);
}

json_response(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
?>
