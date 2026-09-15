<?php
session_start();

if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ระบบควบคุมการเข้า-ออกรถจักรยานยนต์ (Solar Gate System)</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">

</head>
<body>
<div class="layout">

  <!-- ============ Sidebar ============ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">☀️</div>
      <div>
        <div class="brand-title">Solar Gate System</div>
        <div class="brand-sub">ระบบไม้กั้นอัตโนมัติ</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <button class="nav-item active" data-page="dashboard"><span>📊</span> แดชบอร์ด</button>
      <button class="nav-item" data-page="logs"><span>📋</span> บันทึกการเข้า-ออก</button>
      <button class="nav-item" data-page="students"><span>🎓</span> ข้อมูลนักศึกษา</button>
      <button class="nav-item" data-page="solar"><span>🔋</span> ระบบพลังงาน</button>
    </nav>
    <div class="sidebar-foot">
      โปรเจกต์: ระบบควบคุมการเข้า-ออกรถจักรยานยนต์<br>ด้วยไม้กั้นอัตโนมัติ จากพลังงานแสงอาทิตย์<br>
      <span style="opacity:.85"></span>
      <button class="logout-btn" id="btnLogout" type="button">🚪 ออกจากระบบ</button>
    </div>
  </aside>
  <div class="backdrop" id="backdrop"></div>

  <!-- ============ Main ============ -->
  <main class="main">
    <header class="topbar">
      <button class="hamburger" id="btnMenu">☰</button>
      <div>
        <h1 id="pageTitle">📊 แดชบอร์ดภาพรวม</h1>
        <div class="topbar-sub" id="pageSub">ภาพรวมการใช้งานระบบวันนี้</div>
      </div>
      <div class="topbar-right">
        <div class="clock" id="clock"></div>
        <button class="theme-toggle" id="themeToggle" type="button" title="เปลี่ยนธีม">
          <span id="themeIcon">🌙</span>
          <span id="themeLabel">โหมดมืด</span>
        </button>
      </div>
    </header>

    <!-- ================= หน้าแดชบอร์ด ================= -->
    <section class="page active" id="page-dashboard">
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-ico ico-teal">🎓</div><div><div class="stat-num" id="statStudents">0</div><div class="stat-label">นักศึกษาที่ลงทะเบียน (ใช้งานได้)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-green">🛵</div><div><div class="stat-num" id="statTodayIn">0</div><div class="stat-label">การเข้าวันนี้ (ครั้ง)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-blue">🏫</div><div><div class="stat-num" id="statInside">0</div><div class="stat-label">อยู่ในวิทยาลัยขณะนี้ (คน)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-red">⛔</div><div><div class="stat-num" id="statDenied">0</div><div class="stat-label">ถูกปฏิเสธวันนี้ (ครั้ง)</div></div></div>
      </div>

      <div class="grid-2">
        <!-- จำลองการแตะบัตร -->
        <div class="card">
          <div class="card-head">
            <div><h2>🚧 ทดสอบการแตะบัตร / ไม้กั้นอัตโนมัติ</h2>
            <div class="card-sub">จำลองการทำงาน — ตรวจบัตร → เปิด/ไม่เปิดไม้กั้น → บันทึกประวัติ</div>
            <div class="backend-status" id="backendStatus"><span class="backend-dot"></span><span id="backendStatusText">กำลังเชื่อมต่อฐานข้อมูล...</span></div></div>
          </div>
          <div class="gate-scene" id="gateScene">
            <div class="scene-sun">☀️</div>
            <div class="scene-panel"></div>
            <div class="scene-road"></div>
            <div class="scene-hint">ไม้กั้นปิด</div>
            <div class="gate-post"><div class="gate-led" id="gateLed"></div></div>
            <div class="gate-arm" id="gateArm"></div>
            <div class="scene-bike" id="sceneBike">🛵</div>
          </div>
          <div class="scan-controls">
            <select id="scanStudentSelect" class="input"></select>
            <div class="scan-row">
              <button class="btn btn-primary" id="btnScan">📡 แตะบัตร</button>
              <button class="btn btn-outline" id="btnRandomCard" title="สุ่มบัตรที่ไม่ได้ลงทะเบียน เพื่อทดสอบการปฏิเสธ">🎲 บัตรแปลกหน้า</button>
            </div>
          </div>
          <div class="scan-result idle" id="scanResult">ระบบพร้อมทำงาน — เลือกนักศึกษาสำหรับจำลองการแตะบัตร แล้วกด "แตะบัตร"</div>
        </div>

        <!-- สถานะพลังงาน -->
        <div class="card">
          <div class="card-head">
            <h2>🔋 สถานะพลังงานแสงอาทิตย์</h2>
            <button class="link-btn" data-goto="solar">รายละเอียด →</button>
          </div>
          <div class="batt-outer">
            <div class="batt-fill" id="dashBattFill" style="width:0%"></div>
            <span class="batt-text" id="dashBattText">--%</span>
          </div>
          <div class="mini-stats">
            <div>แผงโซลาร์เซลล์<br><b id="dashSolarV">--</b></div>
            <div>แรงดันแบตเตอรี่<br><b id="dashBattV">--</b></div>
            <div>สถานะ<br><b id="dashChargeState">--</b></div>
          </div>
          <div class="card-sub" style="margin-top:12px">* ค่าเป็นการจำลองเพื่อสาธิต อัปเดตทุก 5 วินาที</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-head"><div><h2>📈 การเข้า-ออกรายชั่วโมง (วันนี้)</h2><div class="card-sub" id="chartSub"></div></div></div>
          <div class="chart" id="hourChart"></div>
        </div>
        <div class="card">
          <div class="card-head"><h2>🕘 กิจกรรมล่าสุด</h2><button class="link-btn" data-goto="logs">ดูทั้งหมด →</button></div>
          <div id="recentList"></div>
        </div>
      </div>
    </section>

    <!-- ================= หน้าบันทึกการเข้า-ออก ================= -->
    <section class="page" id="page-logs">
      <div class="card">
        <div class="card-head">
          <h2>📋 บันทึกการแตะบัตร RFID <span class="count-pill" id="logCount">0 รายการ</span></h2>
          <div style="display:flex; gap:8px; flex-wrap:wrap">
            <button class="btn btn-outline btn-sm" id="btnExport">⬇️ ส่งออก CSV</button>
            <button class="btn btn-outline btn-sm" id="btnPrintPdf">🖨️ พิมพ์ PDF</button>
            <button class="btn btn-danger-outline btn-sm" id="btnClearLogs">🗑️ ล้างทั้งหมด</button>
            <button class="btn btn-primary btn-sm" id="btnAddLog">➕ เพิ่มบันทึก</button>
          </div>
        </div>
        <div class="print-header" id="printHeader">
          <h1>รายงานบันทึกการเข้า-ออก</h1>
          <div class="print-meta" id="printMeta"></div>
        </div>
        <div class="filters">
          <input type="search" id="logSearch" placeholder="🔍 ค้นหาชื่อ / รหัสนักศึกษา...">
          <div class="thai-date-picker" id="thaiDatePicker">
            <input type="text" class="input" id="logDateDisplay" placeholder="📅 เลือกวันที่" readonly aria-label="เลือกวันที่">
            <input type="hidden" id="logDate">
            <div class="date-picker-popup" id="datePickerPopup">
              <div class="date-picker-head">
                <button type="button" class="date-nav" id="datePrevMonth" aria-label="เดือนก่อนหน้า">‹</button>
                <div class="date-month-label" id="dateMonthLabel"></div>
                <button type="button" class="date-nav" id="dateNextMonth" aria-label="เดือนถัดไป">›</button>
              </div>
              <div class="date-weekdays">
                <span>อา</span><span>จ</span><span>อ</span><span>พ</span><span>พฤ</span><span>ศ</span><span>ส</span>
              </div>
              <div class="date-days" id="dateDays"></div>
              <div class="date-picker-foot">
                <button type="button" class="date-text-btn" id="dateToday">วันนี้</button>
                <button type="button" class="date-text-btn danger" id="dateClear">ล้าง</button>
              </div>
            </div>
          </div>
          <button class="btn btn-outline btn-sm" id="btnDateClear">ล้างวันที่</button>
        </div>
        <div class="log-tabs" role="tablist" aria-label="ประเภทการเข้าออก">
          <button type="button" class="log-tab active" data-log-filter="all">ทั้งหมด <span id="countAll">0</span></button>
          <button type="button" class="log-tab" data-log-filter="in">คนที่เข้า <span id="countIn">0</span></button>
          <button type="button" class="log-tab" data-log-filter="out">คนที่ออก <span id="countOut">0</span></button>
        </div>

        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>ลำดับ</th><th>ชื่อนักศึกษา</th><th>รหัสนักศึกษา</th>
              <th>ประเภท</th><th>สถานะ</th><th>วันที่และเวลา</th><th>จัดการ</th>
            </tr></thead>
            <tbody id="logTableBody"></tbody>
          </table>
        </div>
        <div class="pagination" id="logPagination">
          <button type="button" class="pagination-btn" id="logPrevPage">‹</button>
          <div class="pagination-info" id="logPageInfo">หน้า 1 / 1</div>
          <button type="button" class="pagination-btn" id="logNextPage">›</button>
        </div>
      </div>
    </section>

    <!-- ================= หน้าข้อมูลนักศึกษา ================= -->
    <section class="page" id="page-students">
      <div class="card">
        <div class="card-head">
          <h2>🎓 รายชื่อนักศึกษาที่มีสิทธิ์ผ่านไม้กั้น <span class="count-pill" id="studentCount">0 คน</span></h2>
          <button class="btn btn-primary btn-sm" id="btnAddStudent">➕ เพิ่มนักศึกษา</button>
        </div>
        <div class="filters">
          <input type="search" id="studentSearch" placeholder="🔍 ค้นหาชื่อ / รหัสนักศึกษา / สาขา...">
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>ลำดับ</th><th>ชื่อ-นามสกุล</th><th>รหัสนักศึกษา</th>
              <th>สาขา</th><th>สถานะบัตร</th><th>จัดการ</th>
            </tr></thead>
            <tbody id="studentTableBody"></tbody>
          </table>
        </div>
        <div class="card-sub" style="margin-top:12px">* รายชื่อนี้คือ Whitelist — บัตรที่ไม่อยู่ในรายการ (หรือถูกระงับ) ไม้กั้นจะไม่เปิด</div>
      </div>
    </section>

    <!-- ================= หน้าระบบพลังงาน ================= -->
    <section class="page" id="page-solar">
      <div class="stat-grid">
        <div class="stat-card"><div class="stat-ico ico-blue">☀️</div><div><div class="stat-num" id="solarVolt">--</div><div class="stat-label">แรงดันแผงโซลาร์เซลล์ (V)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-teal">⚡</div><div><div class="stat-num" id="solarWatt">--</div><div class="stat-label">กำลังการชาร์จ (W)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-green">🔋</div><div><div class="stat-num" id="solarBattPct">--</div><div class="stat-label">ระดับแบตเตอรี่ (%)</div></div></div>
        <div class="stat-card"><div class="stat-ico ico-red">🔌</div><div><div class="stat-num" id="solarBattV">--</div><div class="stat-label">แรงดันแบตเตอรี่ (V)</div></div></div>
      </div>
      <div class="grid-2">
        <div class="card">
          <div class="card-head"><h2>🔋 แบตเตอรี่ 12V</h2></div>
          <div class="batt-outer" style="height:34px">
            <div class="batt-fill" id="solarBattFill" style="width:0%"></div>
            <span class="batt-text" id="solarBattText2">--</span>
          </div>
          <div class="mini-stats">
            <div>สถานะปัจจุบัน<br><b id="solarState">--</b></div>
            <div>อุณหภูมิระบบ<br><b>ปกติ ✅</b></div>
          </div>
          <div class="status-line">
            <span>🟢 ESP32 เชื่อมต่อ Wi-Fi</span><span>🟢 RFID Reader พร้อม</span><span>🟢 ไม้กั้นพร้อมทำงาน</span>
          </div>
          <div class="card-sub" style="margin-top:14px">* ค่าทั้งหมดเป็นการจำลองเพื่อสาธิต — ระบบจริงให้ ESP32 อ่านค่าจากเซ็นเซอร์วัดแรงดัน (Voltage Divider) แล้วส่งมาแสดงผล</div>
        </div>
        <div class="card">
          <div class="card-head"><div><h2>⚙️ โครงระบบ</h2><div class="card-sub">เส้นทางพลังงานและการทำงานของระบบ</div></div></div>
          <div class="flow">
            <div class="flow-item"><span class="flow-ico">☀️</span><div><b>แผงโซลาร์เซลล์ 50W 12V</b><small>เปลี่ยนพลังงานแสงอาทิตย์เป็นพลังงานไฟฟ้า</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">🎛️</span><div><b>Solar Charge Controller</b><small>ควบคุมการชาร์จแบตเตอรี่ ป้องกันชาร์จเกิน/ถ่ายเกิน</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">🔋</span><div><b>แบตเตอรี่ 12V</b><small>กักเก็บพลังงานไว้ใช้ต่อเนื่องแม้ไม่มีแสง</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">📉</span><div><b>LM2596 (ลดแรงดัน 12V → 5V)</b><small>จ่ายไฟเลี้ยง ESP32 และ RFID Reader</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">🧠</span><div><b>ESP32 (มี Wi-Fi)</b><small>ประมวลผล ตรวจสอบบัตร และส่งข้อมูลขึ้นเว็บ</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">📡</span><div><b>RFID Reader (RC522)</b><small>อ่านบัตรนักศึกษาและส่งข้อมูลให้ ESP32 ตรวจสอบสิทธิ์</small></div></div>
            <div class="flow-arrow">⬇</div>
            <div class="flow-item"><span class="flow-ico">🚧</span><div><b>ไม้กั้นอัตโนมัติ (เซอร์โว/มอเตอร์ + รีเลย์)</b><small>เปิดเมื่อบัตรถูกต้อง • แสดง LED "ยินดีต้อนรับ"</small></div></div>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- ============ Modal: เพิ่ม/แก้ไขบันทึก ============ -->
<div class="modal-overlay" id="logModal">
  <div class="modal">
    <div class="modal-head"><h3 id="logModalTitle">➕ เพิ่มบันทึกการเข้า-ออก</h3><button class="modal-close" onclick="closeModal('logModal')">✕</button></div>
    <form id="logForm">
      <div class="form-grid">
        <div class="form-group full"><label>ชื่อนักศึกษา *</label><input id="fLogName" required placeholder="เช่น นายสมชาย ใจดี"></div>
        <div class="form-group"><label>รหัสนักศึกษา *</label><input id="fLogSid" required placeholder="เช่น 6631501001"></div>
        <!-- RFID UID เก็บภายในระบบ ไม่แสดงบนหน้าเว็บ -->
        <div class="form-group"><label>ประเภท</label><select id="fLogType"><option value="in">เข้า</option><option value="out">ออก</option><option value="none">— (ไม่ระบุ)</option></select></div>
        <div class="form-group"><label>สถานะ</label><select id="fLogResult"><option value="granted">อนุญาต (ไม้กั้นเปิด)</option><option value="denied">ปฏิเสธ (ไม้กั้นไม่เปิด)</option></select></div>
        <div class="form-group full"><label>วันที่และเวลาที่แตะบัตร *</label><input type="datetime-local" id="fLogTime" required></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('logModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">💾 บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: เพิ่ม/แก้ไขนักศึกษา ============ -->
<div class="modal-overlay" id="studentModal">
  <div class="modal">
    <div class="modal-head"><h3 id="studentModalTitle">➕ เพิ่มนักศึกษา</h3><button class="modal-close" onclick="closeModal('studentModal')">✕</button></div>
    <form id="studentForm">
      <div class="form-grid">
        <div class="form-group full"><label>ชื่อ-นามสกุล *</label><input id="fStuName" required placeholder="เช่น นายภูมิรพี แสงทอง"></div>
        <div class="form-group"><label>รหัสนักศึกษา *</label><input id="fStuSid" required placeholder="เช่น 6631501001"></div>
        <!-- RFID UID เก็บภายในระบบ ไม่แสดงบนหน้าเว็บ -->
        <div class="form-group full"><label>สาขา</label><input id="fStuFaculty" placeholder="เช่น วิศวกรรมศาสตร์ (ไฟฟ้า)"></div>
        <div class="form-group"><label>สถานะบัตร</label><select id="fStuStatus"><option value="active">ใช้งาน</option><option value="suspended">ระงับ (ไม้กั้นไม่เปิด)</option></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" onclick="closeModal('studentModal')">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">💾 บันทึก</button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Modal: ยืนยันการลบ ============ -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal modal-sm">
    <div class="confirm-body">
      <div class="confirm-ico" id="confirmIcon">🗑️</div>
      <h3 id="confirmTitle">ยืนยันการลบ</h3>
      <p id="confirmMsg"></p>
    </div>
    <div class="modal-foot center">
      <button class="btn btn-outline" onclick="closeModal('confirmModal')">ยกเลิก</button>
      <button class="btn btn-danger" id="confirmOk">ยืนยันลบ</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script src="js/app.js"></script>

</body>
</html>