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
<title>เข้าสู่ระบบ | Solar Gate System</title>
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
        <div class="login-visual-icon">☀️</div>
        <div class="login-visual-title">Solar Gate</div>
        <div class="login-visual-sub">ระบบควบคุมการเข้า-ออก
          <br>รถจักรยานยนต์อัตโนมัติ</div>
        <div class="login-feature-list">
          <div><span>✓</span> ตรวจสอบสิทธิ์ด้วย RFID</div>
          <div><span>✓</span> บันทึกการเข้า-ออก</div>
          <div><span>✓</span> ใช้พลังงานแสงอาทิตย์</div>
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
          <h1>เข้าสู่ระบบ</h1>
          <p>กรุณาเข้าสู่ระบบเพื่อจัดการข้อมูลและควบคุมระบบไม้กั้น</p>
        </div>
        <button class="theme-toggle login-theme-toggle" id="loginThemeToggle" type="button" title="เปลี่ยนธีม">
          <span id="loginThemeIcon">🌙</span>
          <span id="loginThemeLabel">โหมดมืด</span>
        </button>
      </div>

      <form id="loginForm" class="login-form">
        <div class="login-field">
          <label for="username">ชื่อผู้ใช้</label>
          <div class="login-input-wrap">
            <span>👤</span>
            <input id="username" name="username" autocomplete="username" required placeholder="กรอกชื่อผู้ใช้">
          </div>
        </div>

        <div class="login-field">
          <label for="password">รหัสผ่าน</label>
          <div class="login-input-wrap">
            <span>🔒</span>
            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="กรอกรหัสผ่าน">
            <button class="password-toggle" id="passwordToggle" type="button" title="แสดงรหัสผ่าน">👁️</button>
          </div>
        </div>

        <div class="login-options">
          <label class="remember-check"><input type="checkbox" id="rememberMe"> <span>จดจำชื่อผู้ใช้</span></label>
        </div>

        <button class="login-submit" id="loginSubmit" type="submit">
          <span>🔐</span> เข้าสู่ระบบ
        </button>

        <div class="login-status" id="loginStatus"></div>


      </form>

      <div class="login-footer">
        <span>🛡️ ระบบสำหรับผู้ดูแล</span>
        <span>•</span>
        <span>เข้าสู่ระบบอย่างปลอดภัย</span>
      </div>
      </div>
    </section>
  </main>

<script>
const $ = id => document.getElementById(id);

function applyLoginTheme(theme) {
  const dark = theme === 'dark';
  document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
  localStorage.setItem('sgs_theme', dark ? 'dark' : 'light');
  $('loginThemeIcon').textContent = dark ? '☀️' : '🌙';
  $('loginThemeLabel').textContent = dark ? 'โหมดกลางวัน' : 'โหมดมืด';
}

applyLoginTheme(localStorage.getItem('sgs_theme') === 'dark' ? 'dark' : 'light');
$('loginThemeToggle').addEventListener('click', () => {
  const current = document.documentElement.getAttribute('data-theme');
  applyLoginTheme(current === 'dark' ? 'light' : 'dark');
});

const savedUsername = localStorage.getItem('sgs_login_username');
if (savedUsername) {
  $('username').value = savedUsername;
  $('rememberMe').checked = true;
}

$('passwordToggle').addEventListener('click', () => {
  const input = $('password');
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  $('passwordToggle').textContent = show ? '🙈' : '👁️';
});

$('loginForm').addEventListener('submit', async event => {
  event.preventDefault();
  const button = $('loginSubmit');
  const status = $('loginStatus');
  button.disabled = true;
  button.innerHTML = '<span>⏳</span> กำลังตรวจสอบ...';
  status.className = 'login-status';
  status.textContent = '';

  try {
    const response = await fetch('api/auth.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        username: $('username').value.trim(),
        password: $('password').value
      })
    });

    const raw = await response.text();
    let data = {};
    try { data = JSON.parse(raw); } catch {
      throw new Error('เซิร์ฟเวอร์ส่งข้อมูลกลับมาไม่ถูกต้อง');
    }

    if (!response.ok || data.success === false) {
      throw new Error(data.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
    }

    if ($('rememberMe').checked) localStorage.setItem('sgs_login_username', $('username').value.trim());
    else localStorage.removeItem('sgs_login_username');

    status.className = 'login-status success';
    status.textContent = '✅ เข้าสู่ระบบสำเร็จ กำลังเปิดระบบ...';
    setTimeout(() => { window.location.href = 'index.php'; }, 350);
  } catch (error) {
    status.className = 'login-status error';
    status.textContent = '❌ ' + error.message;
    button.disabled = false;
    button.innerHTML = '<span>🔐</span> เข้าสู่ระบบ';
  }
});
</script>
</body>
</html>
