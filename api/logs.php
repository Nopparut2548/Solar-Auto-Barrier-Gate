<?php
require_once __DIR__ . '/bootstrap.php';

$conn = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function log_type_from_history(array &$lastTypeByStudent, ?int $studentId, string $status): string {
    if ($status !== 'allowed' || !$studentId) {
        return 'none';
    }

    $last = $lastTypeByStudent[$studentId] ?? null;
    $type = ($last === 'in') ? 'out' : 'in';
    $lastTypeByStudent[$studentId] = $type;
    return $type;
}

if ($method === 'GET') {
    $result = $conn->query(
        'SELECT
            al.id,
            al.student_id,
            al.rfid_uid,
            al.status,
            al.tap_at,
            s.student_code,
            s.student_name
         FROM access_logs al
         LEFT JOIN students s ON s.id = al.student_id
         ORDER BY al.tap_at ASC, al.id ASC'
    );

    if (!$result) {
        json_response([
            'success' => false,
            'message' => 'อ่านประวัติการเข้า-ออกไม่ได้: ' . $conn->error
        ], 500);
    }

    $rows = [];
    $lastTypeByStudent = [];

    while ($row = $result->fetch_assoc()) {
        $studentId = $row['student_id'] !== null ? (int)$row['student_id'] : null;
        $status = strtolower((string)$row['status']);
        $resultName = in_array($status, ['allowed', 'granted'], true) ? 'granted' : 'denied';
        $type = log_type_from_history($lastTypeByStudent, $studentId, $status);

        $rows[] = [
            'id' => (int)$row['id'],
            'studentName' => $row['student_name'] ?: 'ไม่พบข้อมูลนักศึกษา',
            'studentId' => $row['student_code'] ?: '-',
            'rfidUid' => '',
            'type' => $type,
            'result' => $resultName,
            'timestamp' => iso_timestamp($row['tap_at'])
        ];
    }

    $rows = array_reverse($rows);
    json_response(['success' => true, 'logs' => $rows]);
}

if ($method === 'DELETE' && isset($_GET['all'])) {
    if (!$conn->query('DELETE FROM access_logs')) {
        json_response([
            'success' => false,
            'message' => 'ล้างบันทึกไม่ได้: ' . $conn->error
        ], 500);
    }

    json_response(['success' => true, 'message' => 'ล้างบันทึกทั้งหมดแล้ว']);
}

$id = (int)($_GET['id'] ?? 0);
$data = request_json();

if ($method === 'POST') {
    $studentIdCode = trim((string)($data['studentId'] ?? ''));
    $status = ($data['result'] ?? 'granted') === 'denied' ? 'denied' : 'allowed';
    $timestamp = mysql_datetime((string)($data['timestamp'] ?? date('Y-m-d H:i:s')));

    if ($studentIdCode === '') {
        json_response(['success' => false, 'message' => 'กรุณาระบุรหัสนักศึกษา'], 422);
    }

    $studentRef = null;
    $rfidUid = normalize_uid((string)($data['rfidUid'] ?? ''));

    if ($studentIdCode !== '-') {
        $find = $conn->prepare('SELECT id, rfid_uid FROM students WHERE student_code = ? LIMIT 1');
        if (!$find) {
            json_response(['success' => false, 'message' => 'เตรียมคำสั่งค้นหานักศึกษาไม่สำเร็จ: ' . $conn->error], 500);
        }

        $find->bind_param('s', $studentIdCode);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();

        if ($found) {
            $studentRef = (int)$found['id'];
            if ($rfidUid === '') {
                $rfidUid = normalize_uid((string)$found['rfid_uid']);
            }
        }
    }

    if ($rfidUid === '') {
        $rfidUid = 'SIMULATED';
    }

    $stmt = $conn->prepare(
        'INSERT INTO access_logs (student_id, rfid_uid, status, tap_at)
         VALUES (?, ?, ?, ?)'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งเพิ่มประวัติไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('isss', $studentRef, $rfidUid, $status, $timestamp);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'เพิ่มประวัติไม่ได้: ' . $message], 500);
    }

    $inserted = $stmt->insert_id;
    $stmt->close();

    json_response(['success' => true, 'message' => 'เพิ่มบันทึกเรียบร้อยแล้ว', 'id' => $inserted], 201);
}

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'ไม่พบรหัสบันทึก'], 400);
}

if ($method === 'PUT') {
    $studentIdCode = trim((string)($data['studentId'] ?? ''));
    $status = ($data['result'] ?? 'granted') === 'denied' ? 'denied' : 'allowed';
    $timestamp = mysql_datetime((string)($data['timestamp'] ?? date('Y-m-d H:i:s')));
    $rfidUid = normalize_uid((string)($data['rfidUid'] ?? ''));

    $studentRef = null;
    if ($studentIdCode !== '' && $studentIdCode !== '-') {
        $find = $conn->prepare('SELECT id, rfid_uid FROM students WHERE student_code = ? LIMIT 1');
        if (!$find) {
            json_response(['success' => false, 'message' => 'เตรียมคำสั่งค้นหานักศึกษาไม่สำเร็จ: ' . $conn->error], 500);
        }
        $find->bind_param('s', $studentIdCode);
        $find->execute();
        $found = $find->get_result()->fetch_assoc();
        $find->close();

        if ($found) {
            $studentRef = (int)$found['id'];
            if ($rfidUid === '') {
                $rfidUid = normalize_uid((string)$found['rfid_uid']);
            }
        }
    }

    if ($rfidUid === '') {
        $existing = $conn->prepare('SELECT rfid_uid FROM access_logs WHERE id = ? LIMIT 1');
        if ($existing) {
            $existing->bind_param('i', $id);
            $existing->execute();
            $old = $existing->get_result()->fetch_assoc();
            $existing->close();
            $rfidUid = normalize_uid((string)($old['rfid_uid'] ?? 'SIMULATED'));
        }
    }

    $stmt = $conn->prepare(
        'UPDATE access_logs
         SET student_id = ?, rfid_uid = ?, status = ?, tap_at = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งแก้ไขประวัติไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('isssi', $studentRef, $rfidUid, $status, $timestamp, $id);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'แก้ไขประวัติไม่ได้: ' . $message], 500);
    }

    $stmt->close();
    json_response(['success' => true, 'message' => 'แก้ไขบันทึกเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare('DELETE FROM access_logs WHERE id = ?');
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งลบประวัติไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'ลบประวัติไม่ได้: ' . $message], 500);
    }

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 0) {
        json_response(['success' => false, 'message' => 'ไม่พบบันทึกนี้'], 404);
    }

    json_response(['success' => true, 'message' => 'ลบบันทึกแล้ว']);
}

json_response(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
?>
