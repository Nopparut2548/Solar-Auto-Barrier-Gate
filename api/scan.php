<?php
require_once __DIR__ . '/bootstrap.php';
seed_database();
$conn = db();

require_method('POST');
$data = request_json();

$studentIdRef = (int)($data['student_id'] ?? 0);
$rfidUid = normalize_uid((string)($data['rfid_uid'] ?? ''));
$unknown = !empty($data['unknown']);

$student = null;
if (!$unknown && $studentIdRef > 0) {
    $stmt = $conn->prepare('SELECT id, student_id, name, rfid_uid, status FROM students WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $studentIdRef);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (!$unknown && $rfidUid !== '') {
    $stmt = $conn->prepare('SELECT id, student_id, name, rfid_uid, status FROM students WHERE rfid_uid = ? LIMIT 1');
    $stmt->bind_param('s', $rfidUid);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$granted = (bool)($student && $student['status'] === 'active');
$type = 'none';

if ($granted) {
    $lastStmt = $conn->prepare("SELECT access_type FROM access_logs WHERE result = 'granted' AND student_id_ref = ? AND access_type IN ('in','out') ORDER BY event_time DESC, id DESC LIMIT 1");
    $studentRef = (int)$student['id'];
    $lastStmt->bind_param('i', $studentRef);
    $lastStmt->execute();
    $last = $lastStmt->get_result()->fetch_assoc();
    $lastStmt->close();
    $type = ($last && $last['access_type'] === 'in') ? 'out' : 'in';
}

$now = date('Y-m-d H:i:s');
if ($rfidUid === '') {
    $rfidUid = $student['rfid_uid'] ?? sprintf('%02X%02X%02X%02X', random_int(0,255), random_int(0,255), random_int(0,255), random_int(0,255));
}
$name = $granted ? $student['name'] : 'ไม่พบข้อมูลนักศึกษา';
$code = $granted ? $student['student_id'] : '-';
$result = $granted ? 'granted' : 'denied';
$studentRef = $granted ? (int)$student['id'] : null;

$stmt = $conn->prepare('INSERT INTO access_logs (student_id_ref, student_name, student_code, rfid_uid, access_type, result, event_time) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('issssss', $studentRef, $name, $code, $rfidUid, $type, $result, $now);
$stmt->execute();
$logId = $stmt->insert_id;
$stmt->close();

json_response([
    'success' => true,
    'granted' => $granted,
    'type' => $type,
    'student' => $granted ? ['id' => (int)$student['id'], 'studentId' => $student['student_id'], 'name' => $student['name']] : null,
    'reason' => $granted ? '' : ($student ? 'บัตรถูกระงับการใช้งาน' : 'ไม่พบบัตรนี้ในระบบ (ไม่ใช่นักศึกษาของวิทยาลัย)'),
    'timestamp' => date('c', strtotime($now)),
    'logId' => $logId
]);
?>
