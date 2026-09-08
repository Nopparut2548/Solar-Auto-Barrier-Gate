<?php
require_once __DIR__ . '/config.php';

function seed_database(): void {
    $conn = db();

    $result = $conn->query('SELECT COUNT(*) AS total FROM students');
    $row = $result->fetch_assoc();
    if ((int)$row['total'] > 0) {
        return;
    }

    $students = [
        ['6631501001', 'ภูมิรพี แสงทอง', 'วิศวกรรมศาสตร์ (ไฟฟ้า)', 'A1 B2 C3 D4', 'active'],
        ['6631501025', 'ณัฐริกา วงศ์สุวรรณ', 'วิทยาศาสตร์ (คอมพิวเตอร์)', 'E5 F6 07 18', 'active'],
        ['6521403010', 'ธนกร ใจงาม', 'บริหารธุรกิจ', '1A 2B 3C 4D', 'active'],
        ['6712300042', 'ศิริชัย พรหมมา', 'เทคโนโลยีอุตสาหกรรม', 'DE AD BE EF', 'active'],
        ['6634100078', 'พิมพ์ชนก ศรีสุข', 'ศึกษาศาสตร์', 'AA BB CC 01', 'suspended'],
    ];

    $stmt = $conn->prepare('INSERT INTO students (student_id, name, faculty, rfid_uid, status) VALUES (?, ?, ?, ?, ?)');
    foreach ($students as $student) {
        [$studentId, $name, $faculty, $uid, $status] = $student;
        $stmt->bind_param('sssss', $studentId, $name, $faculty, $uid, $status);
        $stmt->execute();
    }
    $stmt->close();

    // สร้างข้อมูลตัวอย่างย้อนหลัง 7 วันแบบเดียวกับหน้าเว็บเดิม
    $studentResult = $conn->query("SELECT id, student_id, name, rfid_uid, status FROM students WHERE status = 'active' ORDER BY id");
    $pool = $studentResult->fetch_all(MYSQLI_ASSOC);
    if (!$pool) {
        return;
    }

    $logStmt = $conn->prepare('INSERT INTO access_logs (student_id_ref, student_name, student_code, rfid_uid, access_type, result, event_time) VALUES (?, ?, ?, ?, ?, ?, ?)');

    for ($d = 6; $d >= 0; $d--) {
        $base = new DateTimeImmutable('today', new DateTimeZone('Asia/Bangkok'));
        $base = $base->modify("-{$d} days");
        $hCap = $d === 0 ? (int)(new DateTime('now', new DateTimeZone('Asia/Bangkok')))->format('G') : 23;
        $n = $d === 0 ? 3 : 4 + random_int(0, 3);

        for ($i = 0; $i < $n; $i++) {
            $st = $pool[array_rand($pool)];
            $inH = random_int(7, 9);
            if ($inH <= $hCap) {
                $event = $base->setTime($inH, random_int(0, 59), random_int(0, 59))->format('Y-m-d H:i:s');
                $type = 'in';
                $result = 'granted';
                $studentRef = (int)$st['id'];
                $logStmt->bind_param('issssss', $studentRef, $st['name'], $st['student_id'], $st['rfid_uid'], $type, $result, $event);
                $logStmt->execute();
            }

            $outH = random_int(15, 18);
            if ($outH <= $hCap && random_int(1, 100) <= 85) {
                $event = $base->setTime($outH, random_int(0, 59), random_int(0, 59))->format('Y-m-d H:i:s');
                $type = 'out';
                $result = 'granted';
                $studentRef = (int)$st['id'];
                $logStmt->bind_param('issssss', $studentRef, $st['name'], $st['student_id'], $st['rfid_uid'], $type, $result, $event);
                $logStmt->execute();
            }
        }

        if (random_int(1, 100) <= 40) {
            $unknownUid = sprintf('%02X %02X %02X %02X', random_int(0,255), random_int(0,255), random_int(0,255), random_int(0,255));
            $unknownH = random_int(8, 17);
            if ($unknownH <= $hCap) {
                $event = $base->setTime($unknownH, random_int(0, 59), random_int(0, 59))->format('Y-m-d H:i:s');
                $type = 'none';
                $result = 'denied';
                $studentRef = null;
                $name = 'ไม่พบข้อมูลนักศึกษา';
                $code = '-';
                $logStmt->bind_param('issssss', $studentRef, $name, $code, $unknownUid, $type, $result, $event);
                $logStmt->execute();
            }
        }
    }

    $logStmt->close();
}
?>
