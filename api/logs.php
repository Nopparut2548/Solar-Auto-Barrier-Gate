<?php
require_once __DIR__ . '/bootstrap.php';
seed_database();
$conn = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function log_row(array $row): array {
    return [
        'id' => (int)$row['id'],
        'studentName' => $row['student_name'],
        'studentId' => $row['student_code'],
        'type' => $row['access_type'],
        'result' => $row['result'],
        'timestamp' => iso_timestamp($row['event_time'])
    ];
}

if ($method === 'GET') {
    $result = $conn->query('SELECT id, student_name, student_code, access_type, result, event_time FROM access_logs ORDER BY event_time DESC, id DESC');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = log_row($row);
    }
    json_response(['success' => true, 'logs' => $rows]);
}

if ($method === 'DELETE' && isset($_GET['all'])) {
    $conn->query('DELETE FROM access_logs');
    json_response(['success' => true, 'message' => 'ล้างบันทึกทั้งหมดแล้ว']);
}

$id = (int)($_GET['id'] ?? 0);
$data = request_json();

if ($method === 'POST') {
    $name = trim((string)($data['studentName'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $type = in_array(($data['type'] ?? 'none'), ['in', 'out', 'none'], true) ? $data['type'] : 'none';
    $result = ($data['result'] ?? 'granted') === 'denied' ? 'denied' : 'granted';
    $rfidUid = normalize_uid((string)($data['rfidUid'] ?? 'SIMULATED'));
    $timestamp = (string)($data['timestamp'] ?? date('Y-m-d H:i:s'));
    $parsed = date('Y-m-d H:i:s', strtotime($timestamp));

    if ($name === '' || $studentId === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลบันทึกให้ครบ'], 422);
    }

    $studentRef = null;
    if ($result === 'granted' && $studentId !== '-') {
        $find = $conn->prepare('SELECT id FROM students WHERE student_id = ? LIMIT 1');
        $find->bind_param('s', $studentId);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();
        $studentRef = $found ? (int)$found['id'] : null;
    }

    $stmt = $conn->prepare('INSERT INTO access_logs (student_id_ref, student_name, student_code, rfid_uid, access_type, result, event_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssss', $studentRef, $name, $studentId, $rfidUid, $type, $result, $parsed);
    $stmt->execute();
    $inserted = $stmt->insert_id;
    $stmt->close();
    json_response(['success' => true, 'message' => 'เพิ่มบันทึกเรียบร้อยแล้ว', 'id' => $inserted], 201);
}

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'ไม่พบรหัสบันทึก'], 400);
}

if ($method === 'PUT') {
    $name = trim((string)($data['studentName'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $type = in_array(($data['type'] ?? 'none'), ['in', 'out', 'none'], true) ? $data['type'] : 'none';
    $result = ($data['result'] ?? 'granted') === 'denied' ? 'denied' : 'granted';
    $timestamp = date('Y-m-d H:i:s', strtotime((string)($data['timestamp'] ?? date('Y-m-d H:i:s'))));

    if ($name === '' || $studentId === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลบันทึกให้ครบ'], 422);
    }

    $stmt = $conn->prepare('UPDATE access_logs SET student_name = ?, student_code = ?, access_type = ?, result = ?, event_time = ? WHERE id = ?');
    $stmt->bind_param('sssssi', $name, $studentId, $type, $result, $timestamp, $id);
    $stmt->execute();
    $stmt->close();
    json_response(['success' => true, 'message' => 'แก้ไขบันทึกเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare('DELETE FROM access_logs WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    if ($deleted === 0) {
        json_response(['success' => false, 'message' => 'ไม่พบบันทึกนี้'], 404);
    }
    json_response(['success' => true, 'message' => 'ลบบันทึกแล้ว']);
}

json_response(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
?>
