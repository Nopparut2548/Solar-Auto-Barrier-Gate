<?php
require_once __DIR__ . '/bootstrap.php';

$conn = db();
require_method('POST');

$data = request_json();
$studentIdRef = (int)($data['student_id'] ?? 0);
$rfidUid = normalize_uid((string)($data['rfid_uid'] ?? ''));
$unknown = !empty($data['unknown']);

$student = null;

if (!$unknown && $studentIdRef > 0) {
    $stmt = $conn->prepare(
        'SELECT id, student_code, student_name, rfid_uid, active
         FROM students
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งตรวจบัตรไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('i', $studentIdRef);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif (!$unknown && $rfidUid !== '') {
    $stmt = $conn->prepare(
        'SELECT id, student_code, student_name, rfid_uid, active
         FROM students
         WHERE REPLACE(REPLACE(REPLACE(UPPER(rfid_uid), " ", ""), ":", ""), "-", "") = ?
         LIMIT 1'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งค้นหา RFID ไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('s', $rfidUid);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$granted = (bool)($student && (int)$student['active'] === 1);
$type = 'none';

if ($granted) {
    $studentRef = (int)$student['id'];

    $lastStmt = $conn->prepare(
        'SELECT status
         FROM access_logs
         WHERE student_id = ? AND status = "allowed"
         ORDER BY tap_at DESC, id DESC
         LIMIT 1'
    );

    if (!$lastStmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งตรวจประวัติไม่สำเร็จ: ' . $conn->error], 500);
    }

    $lastStmt->bind_param('i', $studentRef);
    $lastStmt->execute();
    $last = $lastStmt->get_result()->fetch_assoc();
    $lastStmt->close();

    // ในฐานข้อมูลเดิมยังไม่มีคอลัมน์บอกเข้า/ออกโดยตรง จึงสลับจากครั้งล่าสุด
    $type = $last ? 'out' : 'in';
}

$now = date('Y-m-d H:i:s');

if ($rfidUid === '') {
    $rfidUid = $student['rfid_uid'] ?? sprintf(
        '%02X%02X%02X%02X',
        random_int(0, 255),
        random_int(0, 255),
        random_int(0, 255),
        random_int(0, 255)
    );
}

$name = $granted ? $student['student_name'] : 'ไม่พบข้อมูลนักศึกษา';
$code = $granted ? $student['student_code'] : '-';
$result = $granted ? 'allowed' : 'denied';
$studentRef = $granted ? (int)$student['id'] : null;

$stmt = $conn->prepare(
    'INSERT INTO access_logs (student_id, rfid_uid, status, tap_at)
     VALUES (?, ?, ?, ?)'
);

if (!$stmt) {
    json_response(['success' => false, 'message' => 'เตรียมคำสั่งบันทึกการแตะบัตรไม่สำเร็จ: ' . $conn->error], 500);
}

$stmt->bind_param('isss', $studentRef, $rfidUid, $result, $now);

if (!$stmt->execute()) {
    $message = $stmt->error;
    $stmt->close();
    json_response(['success' => false, 'message' => 'บันทึกการแตะบัตรไม่ได้: ' . $message], 500);
}

$logId = $stmt->insert_id;
$stmt->close();

json_response([
    'success' => true,
    'granted' => $granted,
    'type' => $type,
    'student' => $granted ? [
        'id' => (int)$student['id'],
        'studentId' => $student['student_code'],
        'name' => $student['student_name']
    ] : null,
    'reason' => $granted ? '' : (
        $student
            ? 'บัตรถูกระงับการใช้งาน'
            : 'ไม่พบบัตรนี้ในระบบ (ไม่ใช่นักศึกษาของวิทยาลัย)'
    ),
    'timestamp' => date('c', strtotime($now)),
    'logId' => $logId
]);
?>
