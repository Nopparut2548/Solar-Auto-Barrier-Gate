<?php
require_once __DIR__ . '/bootstrap.php';

$conn = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function student_row(array $row): array {
    return [
        'id' => (int)$row['id'],
        'studentId' => $row['student_code'],
        'name' => $row['student_name'],
        'faculty' => '',
        'status' => ((int)$row['active'] === 1) ? 'active' : 'suspended',
        'rfidUid' => $row['rfid_uid'] ?? ''
    ];
}

if ($method === 'GET') {
    $result = $conn->query(
        'SELECT id, student_code, student_name, rfid_uid, active
         FROM students
         ORDER BY id ASC'
    );

    if (!$result) {
        json_response([
            'success' => false,
            'message' => 'อ่านข้อมูลนักศึกษาไม่ได้: ' . $conn->error
        ], 500);
    }

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = student_row($row);
    }

    json_response(['success' => true, 'students' => $students]);
}

$data = request_json();
$id = (int)($_GET['id'] ?? 0);

if ($method === 'POST') {
    $name = trim((string)($data['name'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $rfidUid = normalize_uid((string)($data['rfidUid'] ?? ''));
    $active = ($data['status'] ?? 'active') === 'suspended' ? 0 : 1;

    if ($name === '' || $studentId === '' || $rfidUid === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลนักศึกษาให้ครบ'], 422);
    }

    $check = $conn->prepare(
        'SELECT id, student_code, rfid_uid
         FROM students
         WHERE student_code = ? OR rfid_uid = ?
         LIMIT 1'
    );

    if (!$check) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่ง SQL ไม่สำเร็จ: ' . $conn->error], 500);
    }

    $check->bind_param('ss', $studentId, $rfidUid);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    $check->close();

    if ($existing) {
        $field = $existing['student_code'] === $studentId ? 'รหัสนักศึกษา' : 'RFID UID';
        json_response(['success' => false, 'message' => $field . ' นี้มีในระบบแล้ว'], 409);
    }

    $stmt = $conn->prepare(
        'INSERT INTO students (student_code, student_name, rfid_uid, active)
         VALUES (?, ?, ?, ?)'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งเพิ่มนักศึกษาไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('sssi', $studentId, $name, $rfidUid, $active);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'เพิ่มนักศึกษาไม่ได้: ' . $message], 500);
    }

    $newId = $stmt->insert_id;
    $stmt->close();

    json_response([
        'success' => true,
        'message' => 'เพิ่มนักศึกษาเรียบร้อยแล้ว',
        'student' => [
            'id' => $newId,
            'studentId' => $studentId,
            'name' => $name,
            'faculty' => '',
            'status' => $active ? 'active' : 'suspended',
            'rfidUid' => $rfidUid
        ]
    ], 201);
}

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'ไม่พบรหัสข้อมูลนักศึกษา'], 400);
}

if ($method === 'PUT') {
    $name = trim((string)($data['name'] ?? ''));
    $studentId = trim((string)($data['studentId'] ?? ''));
    $active = ($data['status'] ?? 'active') === 'suspended' ? 0 : 1;

    if ($name === '' || $studentId === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกข้อมูลนักศึกษาให้ครบ'], 422);
    }

    $check = $conn->prepare(
        'SELECT id FROM students WHERE student_code = ? AND id <> ? LIMIT 1'
    );

    if (!$check) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งตรวจสอบไม่สำเร็จ: ' . $conn->error], 500);
    }

    $check->bind_param('si', $studentId, $id);
    $check->execute();

    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        json_response(['success' => false, 'message' => 'รหัสนักศึกษานี้มีในระบบแล้ว'], 409);
    }

    $check->close();

    $stmt = $conn->prepare(
        'UPDATE students
         SET student_code = ?, student_name = ?, active = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งแก้ไขไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('ssii', $studentId, $name, $active, $id);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response(['success' => false, 'message' => 'แก้ไขข้อมูลไม่ได้: ' . $message], 500);
    }

    $stmt->close();
    json_response(['success' => true, 'message' => 'แก้ไขข้อมูลนักศึกษาเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    $stmt = $conn->prepare('DELETE FROM students WHERE id = ?');

    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งลบไม่สำเร็จ: ' . $conn->error], 500);
    }

    $stmt->bind_param('i', $id);

    if (!$stmt->execute()) {
        $message = $stmt->error;
        $stmt->close();
        json_response([
            'success' => false,
            'message' => 'ลบนักศึกษาไม่ได้ เพราะอาจมีประวัติการเข้า-ออกอ้างอิงอยู่: ' . $message
        ], 409);
    }

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted === 0) {
        json_response(['success' => false, 'message' => 'ไม่พบข้อมูลนักศึกษา'], 404);
    }

    json_response(['success' => true, 'message' => 'ลบข้อมูลนักศึกษาแล้ว']);
}

json_response(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
?>
