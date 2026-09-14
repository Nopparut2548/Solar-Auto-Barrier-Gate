<?php
require_once __DIR__ . '/config.php';
session_start();

function ensure_users_table(mysqli $conn): void {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        role VARCHAR(30) NOT NULL DEFAULT 'admin',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$conn->query($sql)) {
        json_response(['success' => false, 'message' => 'สร้างตารางผู้ใช้งานไม่ได้: ' . $conn->error], 500);
    }

    $check = $conn->query("SELECT COUNT(*) AS total FROM users");
    $total = $check ? (int)$check->fetch_assoc()['total'] : 0;

    if ($total === 0) {
        $username = 'admin';
        $passwordHash = '$2y$12$aXbu9xQkYHkQpjiBDwR41uHy8BkKpnIN0WpApnrL1xp23Z5m20Ige';
        $displayName = 'ผู้ดูแลระบบ';
        $role = 'admin';
        $stmt = $conn->prepare('INSERT INTO users (username, password_hash, display_name, role, active) VALUES (?, ?, ?, ?, 1)');
        if (!$stmt) {
            json_response(['success' => false, 'message' => 'เตรียมข้อมูลผู้ใช้ไม่ได้: ' . $conn->error], 500);
        }
        $stmt->bind_param('ssss', $username, $passwordHash, $displayName, $role);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $stmt->close();
            json_response(['success' => false, 'message' => 'สร้างผู้ดูแลระบบไม่ได้: ' . $message], 500);
        }
        $stmt->close();
    }
}

$conn = db();
ensure_users_table($conn);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    json_response([
        'success' => true,
        'loggedIn' => !empty($_SESSION['user']),
        'user' => $_SESSION['user'] ?? null
    ]);
}

$data = request_json();

if ($method === 'POST') {
    $action = (string)($data['action'] ?? 'login');

    // ==================== เข้าสู่ระบบ ====================
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if ($username === '' || $password === '') {
        json_response(['success' => false, 'message' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'], 422);
    }

    $stmt = $conn->prepare('SELECT id, username, password_hash, display_name, role, active FROM users WHERE username = ? LIMIT 1');
    if (!$stmt) {
        json_response(['success' => false, 'message' => 'เตรียมคำสั่งเข้าสู่ระบบไม่ได้: ' . $conn->error], 500);
    }
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || (int)$user['active'] !== 1 || !password_verify($password, $user['password_hash'])) {
        json_response(['success' => false, 'message' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], 401);
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role']
    ];

    json_response(['success' => true, 'message' => 'เข้าสู่ระบบสำเร็จ', 'user' => $_SESSION['user']]);
}

if ($method === 'DELETE') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json_response(['success' => true, 'message' => 'ออกจากระบบแล้ว']);
}

json_response(['success' => false, 'message' => 'ไม่รองรับคำขอนี้'], 405);
?>
