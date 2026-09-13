<?php
session_start();

if (empty($_SESSION['user'])) {
    json_response(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน'], 401);
}

require_once __DIR__ . '/config.php';
?>
