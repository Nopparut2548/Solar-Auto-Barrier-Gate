<?php
session_start();

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>สมัครสมาชิก | Solar Gate System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body class="login-page">
  <div class="login-bg-shape shape-one"></div>
  <div class="login-bg-shape shape-two"></div>

  <main class="login-shell">
    <section class="login-card">
      <div class="login-visual">
        <div class="login-orbit orbit-one"></div>
        <div class="login-orbit orbit-two"></div>
        <div class="login-visual-icon">👤</div>
        <div class="login-visual-title">Create Account</div>
        <div class="login-visual-sub">สร้างบัญชีเพื่อเข้าใช้งาน
          <br>ระบบ Solar Gate System</div>
        <div class="login-feature-list">
          <div><span>✓</span> บันทึกข้อมูลผู้ใช้งาน</div>
          <div><span>✓</span> เข้าถึงแดชบอร์ดระบบ</div>
          <div><span>✓</span> จัดการข้อมูลระบบไม้กั้น</div>
        </div>
        <div class="login-tech">ระบบควบคุมการเข้า-ออกรถจักรยานยนต์ด้วยไม้กั้นอัตโนมัติ โดยใช้พลังงานแสงอาทิตย์</div>
      </div>

      <div class="login-content">
        <div class="login-brand">
          <div class="login-logo">☀️</div>
          <div>
            <div class="login-brand-title">Solar Gate System</div>
            <div class="login-brand-sub">ระบบควบคุมการเข้า-ออกจักรยานยนต์</div>
          </div>
        </div>

        <div class="login-heading">
          <div>
            <h1>สมัครสมาชิก</h1>
            <p>สร้างบัญชีสำหรับเข้าใช้งานระบบจัดการไม้กั้น</p>
          </div>
          <button class="theme-toggle login-theme-toggle" id="registerThemeToggle" type="button" title="เปลี่ยนธีม">
            <span id="registerThemeIcon">🌙</span>
            <span id="registerThemeLabel">โหมดมืด</span>
          </button>
        </div>

        <form id="registerForm" class="login-form">
          <div class="login-field">
            <label for="displayName">ชื่อที่แสดง</label>
            <div class="login-input-wrap">
              <span>🪪</span>
              <input id="displayName" autocomplete="name" required placeholder="เช่น นายสมชาย ใจดำ">
            </div>
          </div>

          <div class="login-field">
            <label for="username">ชื่อผู้ใช้</label>
            <div class="login-input-wrap">
              <span>👤</span>
              <input id="username" autocomplete="username" required placeholder="อย่างน้อย 4 ตัวอักษร">
            </div>
          </div>

          <div class="login-field">
            <label for="password">รหัสผ่าน</label>
            <div class="login-input-wrap">
              <span>🔒</span>
              <input id="password" type="password" autocomplete="new-password" required placeholder="อย่างน้อย 6 ตัวอักษร">
              <button class="password-toggle" id="passwordToggle" type="button">👁️</button>
            </div>
          </div>

          <div class="login-field">
            <label for="confirmPassword">ยืนยันรหัสผ่าน</label>
            <div class="login-input-wrap">
              <span>🔐</span>
              <input id="confirmPassword" type="password" autocomplete="new-password" required placeholder="กรอกรหัสผ่านอีกครั้ง">
            </div>
          </div>

          <button class="login-submit" id="registerSubmit" type="submit">
            <span>✨</span> สมัครสมาชิก
          </button>

          <div class="login-status" id="registerStatus"></div>

          <div class="login-register-link">
            มีบัญชีอยู่แล้ว? <a href="login.php">กลับไปเข้าสู่ระบบ</a>
          </div>
        </form>

        <div class="login-footer">
          <span>🛡️ ระบบสำหรับผู้ดูแล</span>
          <span></span>
          <span></span>
        </div>
      </div>
    </section>
  </main>

<script>
const $ = id => document.getElementById(id);

function applyRegisterTheme(theme) {
  const dark = theme === 'dark';
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  localStorage.setItem('sgs_theme', dark ? 'dark' : 'light');
  $('registerThemeIcon').textContent = dark ? '☀️' : '🌙';
  $('registerThemeLabel').textContent = dark ? 'โหมดกลางวัน' : 'โหมดมืด';
}

applyRegisterTheme(localStorage.getItem('sgs_theme') === 'dark' ? 'dark' : 'light');
$('registerThemeToggle').addEventListener('click', () => {
  const current = document.documentElement.getAttribute('data-theme');
  applyRegisterTheme(current === 'dark' ? 'light' : 'dark');
});

$('passwordToggle').addEventListener('click', () => {
  const input = $('password');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  $('passwordToggle').textContent = show ? '🙈' : '👁️';
});

$('registerForm').addEventListener('submit', async event => {
  event.preventDefault();

  const button = $('registerSubmit');
  const status = $('registerStatus');
  button.disabled = true;
  button.innerHTML = '<span>⏳</span> กำลังสมัครสมาชิก...';
  status.className = 'login-status';
  status.textContent = '';

  try {
    const response = await fetch('api/auth.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        action: 'register',
        display_name: $('displayName').value.trim(),
        username: $('username').value.trim(),
        password: $('password').value,
        confirm_password: $('confirmPassword').value
      })
    });

    const raw = await response.text();
    let data = {};
    try { data = JSON.parse(raw); } catch {
      throw new Error('เซิร์ฟเวอร์ส่งข้อมูลกลับมาไม่ถูกต้อง');
    }

    if (!response.ok || data.success === false) {
      throw new Error(data.message || 'สมัครสมาชิกไม่สำเร็จ');
    }

    status.className = 'login-status success';
    status.textContent = '✅ สมัครสมาชิกสำเร็จ กำลังกลับไปหน้าเข้าสู่ระบบ...';
    setTimeout(() => { window.location.href = 'login.php'; }, 700);
  } catch (error) {
    status.className = 'login-status error';
    status.textContent = '❌ ' + error.message;
    button.disabled = false;
    button.innerHTML = '<span>✨</span> สมัครสมาชิก';
  }
});
</script>
</body>
</html>
