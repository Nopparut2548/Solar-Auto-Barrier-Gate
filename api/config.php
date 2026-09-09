<?php

const DB_HOST = '127.0.0.1';
const DB_NAME = 'solar_auto_barrier_gate';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

date_default_timezone_set('Asia/Bangkok');

function db(): mysqli {
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_errno) {
        json_response([
            'success' => false,
            'message' => 'เชื่อมต่อฐานข้อมูลไม่ได้: ' . $conn->connect_error
        ], 500);
    }

    if (!$conn->set_charset(DB_CHARSET)) {
        json_response([
            'success' => false,
            'message' => 'ตั้งค่า UTF-8 ของฐานข้อมูลไม่ได้: ' . $conn->error
        ], 500);
    }

    return $conn;
}

function json_response(array $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_json(): array {
    $raw = file_get_contents('php://input');

    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        json_response([
            'success' => false,
            'message' => 'ข้อมูล JSON ไม่ถูกต้อง'
        ], 400);
    }

    return $data;
}

function require_method(string ...$methods): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_response([
            'success' => false,
            'message' => 'ไม่รองรับ HTTP Method นี้'
        ], 405);
    }
}

function normalize_uid(string $uid): string {
    return strtoupper((string)preg_replace('/[\s:.-]+/', '', $uid));
}

function display_uid(string $uid): string {
    $uid = normalize_uid($uid);
    return trim(chunk_split($uid, 2, ' '));
}

function mysql_datetime(string $value): string {
    $timestamp = strtotime($value);

    if ($timestamp === false) {
        $timestamp = time();
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function iso_timestamp(string $value): string {
    return date('c', strtotime($value) ?: time());
}
?>
