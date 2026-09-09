/* ============================================================
   Solar Auto Barrier Gate
   Frontend: HTML/CSS/JavaScript
   Backend: PHP + MySQL

   ข้อมูลนักศึกษาและประวัติการเข้า-ออกเก็บใน MySQL ผ่าน PHP API
   ส่วนธีมและค่าจำลองพลังงานใช้ localStorage ของเบราว์เซอร์
============================================================ */

// ---------- ตัวช่วยทั่วไป ----------
const $ = id => document.getElementById(id);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const normUid = s => String(s || '').replace(/[\s:.-]/g, '').toUpperCase();
const fmtUid = s => { const u = normUid(s); return (u.match(/.{1,2}/g) || [u]).join(' '); };
const pad = n => String(n).padStart(2, '0');
const dateKey = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const randomUidHex = () => Array.from({length:4}, () => Math.floor(Math.random()*256).toString(16).padStart(2,'0').toUpperCase()).join(' ');

function fmtDateTime(iso) {
  const d = new Date(iso);
  return d.toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'numeric' }) + ' ' +
         d.toLocaleTimeString('th-TH', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
}

const fmtTimeShort = iso => new Date(iso).toLocaleTimeString('th-TH', { hour:'2-digit', minute:'2-digit' });

function toInputDate(iso) {
  const d = new Date(iso);
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function fromSqlOrIso(value) {
  if (!value) return new Date();
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value)) return new Date(value.replace(' ', 'T'));
  return new Date(value);
}

// ---------- localStorage เฉพาะค่าที่เป็น UI/ค่าจำลอง ----------
const store = {
  get(key, fallback) {
    try {
      const value = JSON.parse(localStorage.getItem(key));
      return value ?? fallback;
    } catch {
      return fallback;
    }
  },
  set(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }
};

// ---------- ตัวแปรข้อมูลจาก MySQL ----------
let students = [];
let logs = [];
let scanBusy = false;
let simulateUnknownCard = false;
let editingLogId = null;
let editingStudentId = null;
let confirmAction = null;
let toastTimer = null;

// ---------- PHP API ----------
async function apiRequest(url, options = {}) {
  const response = await fetch(url, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {})
    }
  });

  // อ่านเป็น text ก่อน จะได้เห็น Error จาก PHP จริง ๆ
  const rawText = await response.text();

  let payload;

  try {
    payload = JSON.parse(rawText);
  } catch (error) {
    console.error('API RESPONSE:', rawText);

    throw new Error(
      `API ${url} ไม่ได้ส่ง JSON กลับมา\n` +
      `HTTP ${response.status}\n` +
      `Response: ${rawText.substring(0, 500)}`
    );
  }

  if (!response.ok || payload.success === false) {
    throw new Error(
      payload.message || `API Error ${response.status}`
    );
  }

  return payload;
}

async function loadStudents() {
  const data = await apiRequest('api/students.php');
  students = data.students || [];
}

async function loadLogs() {
  const data = await apiRequest('api/logs.php');
  logs = data.logs || [];
}

function setBackendStatus(connected, message) {
  const box = $('backendStatus');
  const text = $('backendStatusText');
  if (!box || !text) return;
  box.classList.toggle('offline', !connected);
  text.textContent = message;
}

function renderAll() {
  renderStats();
  renderChart();
  renderRecent();
  renderLogs();
  renderStudents();
  renderScanSelect();
}

async function loadData() {
  try {
    await Promise.all([loadStudents(), loadLogs()]);
    setBackendStatus(true, 'เชื่อมต่อฐานข้อมูลแล้ว');
    renderAll();
  } catch (error) {
    setBackendStatus(false, 'เชื่อมต่อฐานข้อมูลไม่ได้');
    toast('❌ ' + error.message, 'error');
    console.error(error);
  }
}

async function checkBackend() {
  try {
    const data = await apiRequest('api/health.php');
    setBackendStatus(true, `MySQL เชื่อมต่อแล้ว • ${data.students} นักศึกษา • ${data.logs} บันทึก`);
  } catch (error) {
    setBackendStatus(false, 'PHP / MySQL ยังไม่พร้อม');
    console.error(error);
  }
}

function setBackendStatus(connected, message) {
  const box = $('backendStatus');
  const text = $('backendStatusText');
  if (!box || !text) return;
  box.classList.toggle('offline', !connected);
  text.textContent = message;
}

// ---------- คำนวณสถิติ ----------
function computeInside() {
  const inside = new Set();
  [...logs]
    .sort((a, b) => fromSqlOrIso(a.timestamp) - fromSqlOrIso(b.timestamp))
    .forEach(log => {
      if (log.result !== 'granted') return;
      const key = log.studentId || normUid(log.rfidUid);
      if (log.type === 'in') inside.add(key);
      if (log.type === 'out') inside.delete(key);
    });
  return inside.size;
}

function renderStats() {
  const today = dateKey(new Date());
  const todayLogs = logs.filter(log => dateKey(fromSqlOrIso(log.timestamp)) === today);

  $('statStudents').textContent = students.filter(s => s.status === 'active').length;
  $('statTodayIn').textContent = todayLogs.filter(l => l.result === 'granted' && l.type === 'in').length;
  $('statInside').textContent = computeInside();
  $('statDenied').textContent = todayLogs.filter(l => l.result === 'denied').length;
}

// ---------- กราฟรายชั่วโมง ----------
function renderChart() {
  $('chartSub').textContent = 'วันที่ ' + new Date().toLocaleDateString('th-TH', { day:'numeric', month:'long', year:'numeric' });
  const today = dateKey(new Date());
  const counts = Array(24).fill(0);

  logs.forEach(log => {
    const dt = fromSqlOrIso(log.timestamp);
    if (log.result === 'granted' && log.type === 'in' && dateKey(dt) === today) {
      counts[dt.getHours()]++;
    }
  });

  const max = Math.max(...counts, 1);
  let html = '';
  for (let hour = 6; hour <= 20; hour++) {
    const count = counts[hour];
    html += `<div class="bar-col"><div class="bar-val">${count > 0 ? count : ''}</div>
      <div class="bar-track"><div class="bar" style="height:${Math.max((count/max)*100, count > 0 ? 12 : 3)}%" title="${hour}:00 น. — ${count} ครั้ง"></div></div>
      <div class="bar-label">${hour}</div></div>`;
  }
  $('hourChart').innerHTML = html;
}

// ---------- กิจกรรมล่าสุด ----------
function renderRecent() {
  const list = [...logs]
    .sort((a, b) => fromSqlOrIso(b.timestamp) - fromSqlOrIso(a.timestamp))
    .slice(0, 6);

  $('recentList').innerHTML = list.length ? list.map(log => {
    let icon = '🛵';
    let bg = 'ico-green';
    let badge = '<span class="badge badge-in">เข้า</span>';

    if (log.result === 'denied') {
      icon = '⛔';
      bg = 'ico-red';
      badge = '<span class="badge badge-denied">ปฏิเสธ</span>';
    } else if (log.type === 'out') {
      bg = 'ico-blue';
      badge = '<span class="badge badge-out">ออก</span>';
    } else if (log.type === 'none') {
      badge = '';
    }

    return `<div class="recent-item">
      <div class="recent-ico ${bg}">${icon}</div>
      <div>
        <div class="recent-name">${esc(log.studentName)} ${badge}</div>
        <div class="recent-sub">${esc(log.studentId)}</div>
      </div>
      <div class="recent-time">${fmtDateTime(log.timestamp)}</div>
    </div>`;
  }).join('') : '<div class="empty"><div class="empty-ico">🗂️</div>ยังไม่มีข้อมูล</div>';
}

// ---------- ตารางบันทึกการเข้า-ออก ----------
function getFilteredLogs() {
  const query = ($('logSearch').value || '').toLowerCase().trim();
  const selectedDate = $('logDate').value;

  return [...logs]
    .sort((a, b) => fromSqlOrIso(b.timestamp) - fromSqlOrIso(a.timestamp))
    .filter(log => {
      if (query && !(String(log.studentName) + log.studentId).toLowerCase().includes(query)) return false;
      if (selectedDate && dateKey(fromSqlOrIso(log.timestamp)) !== selectedDate) return false;
      return true;
    });
}

function renderLogs() {
  const list = getFilteredLogs();
  $('logCount').textContent = logs.length + ' รายการ';
  const body = $('logTableBody');

  if (list.length === 0) {
    body.innerHTML = '<tr><td colspan="7"><div class="empty"><div class="empty-ico">🗂️</div>ไม่พบข้อมูลตามเงื่อนไขที่ค้นหา</div></td></tr>';
    return;
  }

  body.innerHTML = list.map((log, index) => {
    const typeBadge = log.type === 'in'
      ? '<span class="badge badge-in">เข้า</span>'
      : log.type === 'out'
        ? '<span class="badge badge-out">ออก</span>'
        : '<span class="badge badge-none">—</span>';

    const resultBadge = log.result === 'granted'
      ? '<span class="badge badge-granted">อนุญาต</span>'
      : '<span class="badge badge-denied">ปฏิเสธ</span>';

    const dim = log.result === 'denied' ? ' dim' : '';

    return `<tr>
      <td class="mono">${index + 1}</td>
      <td><span class="cell-name${dim}">${esc(log.studentName)}</span></td>
      <td class="mono${dim}">${esc(log.studentId)}</td>
      <td>${typeBadge}</td>
      <td>${resultBadge}</td>
      <td class="mono" style="white-space:nowrap">${fmtDateTime(log.timestamp)}</td>
      <td>
        <div class="row-actions">
          <button class="icon-btn" onclick="editLog(${log.id})" title="แก้ไข">✏️</button>
          <button class="icon-btn danger" onclick="askDeleteLog(${log.id})" title="ลบ">🗑️</button>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function openLogModal(id = null) {
  editingLogId = id;
  $('logModalTitle').textContent = id ? '✏️ แก้ไขบันทึก' : '➕ เพิ่มบันทึกการเข้า-ออก';

  if (id) {
    const log = logs.find(item => String(item.id) === String(id));
    if (!log) return;

    $('fLogName').value = log.studentName;
    $('fLogSid').value = log.studentId;
    $('fLogType').value = log.type;
    $('fLogResult').value = log.result;
    $('fLogTime').value = toInputDate(log.timestamp);
  } else {
    $('logForm').reset();
    $('fLogTime').value = toInputDate(new Date().toISOString());
  }

  $('logModal').classList.add('show');
}

function editLog(id) {
  openLogModal(id);
}

$('logForm').addEventListener('submit', async event => {
  event.preventDefault();

  const existing = editingLogId ? logs.find(item => String(item.id) === String(editingLogId)) : null;
  const payload = {
    studentName: $('fLogName').value.trim(),
    studentId: $('fLogSid').value.trim(),
    rfidUid: existing?.rfidUid || randomUidHex(),
    type: $('fLogType').value,
    result: $('fLogResult').value,
    timestamp: new Date($('fLogTime').value).toISOString()
  };

  try {
    if (editingLogId) {
      await apiRequest(`api/logs.php?id=${encodeURIComponent(editingLogId)}`, {
        method: 'PUT',
        body: JSON.stringify(payload)
      });
      toast('✅ แก้ไขบันทึกเรียบร้อยแล้ว');
    } else {
      await apiRequest('api/logs.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      toast('✅ เพิ่มบันทึกเรียบร้อยแล้ว');
    }

    closeModal('logModal');
    await loadData();
  } catch (error) {
    toast('❌ ' + error.message, 'error');
  }
});

function askDeleteLog(id) {
  const log = logs.find(item => String(item.id) === String(id));
  if (!log) return;

  askConfirm(
    'ลบบันทึกนี้?',
    `ต้องการลบบันทึกของ "${log.studentName}" (${fmtDateTime(log.timestamp)}) ใช่หรือไม่?`,
    async () => {
      try {
        await apiRequest(`api/logs.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        await loadData();
        toast('🗑️ ลบบันทึกแล้ว');
      } catch (error) {
        toast('❌ ' + error.message, 'error');
      }
    }
  );
}

// ---------- ตารางนักศึกษา (Whitelist) ----------
function renderStudents() {
  const query = ($('studentSearch').value || '').toLowerCase().trim();
  const list = students.filter(student =>
    (student.name + student.studentId + (student.faculty || '')).toLowerCase().includes(query)
  );

  $('studentCount').textContent = students.length + ' คน';
  const body = $('studentTableBody');

  if (list.length === 0) {
    body.innerHTML = '<tr><td colspan="6"><div class="empty"><div class="empty-ico">🎓</div>ไม่พบข้อมูลนักศึกษา</div></td></tr>';
    return;
  }

  body.innerHTML = list.map((student, index) => `<tr>
    <td class="mono">${index + 1}</td>
    <td><span class="cell-name">${esc(student.name)}</span></td>
    <td class="mono">${esc(student.studentId)}</td>
    <td>${esc(student.faculty || '-')}</td>
    <td><span class="badge ${student.status === 'active' ? 'badge-active' : 'badge-suspended'}">${student.status === 'active' ? 'ใช้งาน' : 'ระงับ'}</span></td>
    <td>
      <div class="row-actions">
        <button class="icon-btn" onclick="editStudent(${student.id})" title="แก้ไข">✏️</button>
        <button class="icon-btn danger" onclick="askDeleteStudent(${student.id})" title="ลบ">🗑️</button>
      </div>
    </td>
  </tr>`).join('');
}

function openStudentModal(id = null) {
  editingStudentId = id;
  $('studentModalTitle').textContent = id ? '✏️ แก้ไขข้อมูลนักศึกษา' : '➕ เพิ่มนักศึกษา';

  if (id) {
    const student = students.find(item => String(item.id) === String(id));
    if (!student) return;

    $('fStuName').value = student.name;
    $('fStuSid').value = student.studentId;
    $('fStuFaculty').value = student.faculty || '';
    $('fStuStatus').value = student.status;
  } else {
    $('studentForm').reset();
  }

  $('studentModal').classList.add('show');
}

function editStudent(id) {
  openStudentModal(id);
}

$('studentForm').addEventListener('submit', async event => {
  event.preventDefault();

  const payload = {
    name: $('fStuName').value.trim(),
    studentId: $('fStuSid').value.trim(),
    faculty: $('fStuFaculty').value.trim(),
    status: $('fStuStatus').value
  };

  try {
    if (editingStudentId) {
      await apiRequest(`api/students.php?id=${encodeURIComponent(editingStudentId)}`, {
        method: 'PUT',
        body: JSON.stringify(payload)
      });
      toast('✅ แก้ไขข้อมูลนักศึกษาเรียบร้อยแล้ว');
    } else {
      payload.rfidUid = randomUidHex();
      await apiRequest('api/students.php', {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      toast('✅ เพิ่มนักศึกษาเรียบร้อยแล้ว');
    }

    closeModal('studentModal');
    await loadData();
  } catch (error) {
    toast('❌ ' + error.message, 'error');
  }
});

function askDeleteStudent(id) {
  const student = students.find(item => String(item.id) === String(id));
  if (!student) return;

  askConfirm(
    'ลบข้อมูลนักศึกษา?',
    `ต้องการลบ "${student.name}" (${student.studentId}) ออกจากระบบใช่หรือไม่? ประวัติเดิมยังคงอยู่`,
    async () => {
      try {
        await apiRequest(`api/students.php?id=${encodeURIComponent(id)}`, { method: 'DELETE' });
        await loadData();
        toast('🗑️ ลบข้อมูลนักศึกษาแล้ว');
      } catch (error) {
        toast('❌ ' + error.message, 'error');
      }
    }
  );
}

// ---------- จำลองการแตะบัตร RFID ----------
function renderScanSelect() {
  const select = $('scanStudentSelect');
  select.innerHTML = '<option value="">— เลือกนักศึกษาที่จะจำลองการแตะบัตร —</option>' +
    students.map(student => `<option value="${student.id}" ${student.status !== 'active' ? 'disabled' : ''}>
      ${esc(student.name)} • ${esc(student.studentId)}${student.status !== 'active' ? ' (ระงับ)' : ''}
    </option>`).join('');
}

async function doScan() {
  if (scanBusy) return;

  const selectedStudentId = $('scanStudentSelect').value;
  if (!selectedStudentId && !simulateUnknownCard) {
    toast('กรุณาเลือกนักศึกษาก่อน หรือใช้ปุ่ม “บัตรแปลกหน้า”', 'warning');
    return;
  }

  scanBusy = true;
  $('scanResult').className = 'scan-result checking';
  $('scanResult').innerHTML = '<div class="r-ico">⏳</div><div><div class="r-title">กำลังอ่านบัตร RFID...</div><div class="r-detail">PHP กำลังตรวจสอบข้อมูลกับฐานข้อมูล MySQL</div></div>';

  try {
    const payload = simulateUnknownCard
      ? { unknown: true }
      : { student_id: Number(selectedStudentId) };

    const result = await apiRequest('api/scan.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    });

    const now = result.timestamp;

    if (result.granted) {
      $('gateLed').className = 'gate-led green';
      $('gateArm').classList.add('open');
      document.querySelector('.scene-hint').textContent = 'ไม้กั้นเปิด';
      $('scanResult').className = 'scan-result granted';
      $('scanResult').innerHTML = `<div class="r-ico">✅</div><div>
        <div class="r-title">ไม้กั้นเปิด — อนุญาต${result.type === 'in' ? 'เข้า' : 'ออก'}</div>
        <div class="r-detail">ยินดีต้อนรับ <b>${esc(result.student.name)}</b> • รหัสนักศึกษา ${esc(result.student.studentId)} • ${fmtTimeShort(now)} น. • บันทึกลงระบบแล้ว</div></div>`;

      setTimeout(() => $('sceneBike').classList.add('go'), 700);
      setTimeout(() => {
        $('gateArm').classList.remove('open');
        $('gateLed').className = 'gate-led';
        document.querySelector('.scene-hint').textContent = 'ไม้กั้นปิด';

        const bike = $('sceneBike');
        bike.style.transition = 'none';
        bike.classList.remove('go');
        requestAnimationFrame(() => requestAnimationFrame(() => bike.style.transition = ''));
      }, 4300);
    } else {
      $('gateLed').className = 'gate-led blink';
      $('gateScene').classList.add('shake');
      setTimeout(() => {
        $('gateScene').classList.remove('shake');
        $('gateLed').className = 'gate-led';
      }, 2400);

      $('scanResult').className = 'scan-result denied';
      $('scanResult').innerHTML = `<div class="r-ico">⛔</div><div>
        <div class="r-title">ปฏิเสธ — ไม้กั้นไม่เปิด</div>
        <div class="r-detail">${esc(result.reason)} • ${fmtTimeShort(now)} น. • บันทึกเหตุการณ์แล้ว</div></div>`;
    }

    $('scanStudentSelect').value = '';
    simulateUnknownCard = false;
    await loadData();
  } catch (error) {
    toast('❌ ' + error.message, 'error');
  } finally {
    scanBusy = false;
  }
}

$('btnScan').addEventListener('click', doScan);
$('btnRandomCard').addEventListener('click', () => {
  simulateUnknownCard = true;
  $('scanStudentSelect').value = '';
  doScan();
});

// ---------- ส่งออก CSV ----------
$('btnExport').addEventListener('click', () => {
  const rows = getFilteredLogs();
  if (rows.length === 0) {
    toast('ไม่มีข้อมูลสำหรับส่งออก', 'warning');
    return;
  }

  const lines = [['ลำดับ','ชื่อนักศึกษา','รหัสนักศึกษา','ประเภท','สถานะ','วันที่และเวลา'].join(',')];

  rows.forEach((log, index) => {
    const cells = [
      index + 1,
      log.studentName,
      log.studentId,
      log.type === 'in' ? 'เข้า' : log.type === 'out' ? 'ออก' : '-',
      log.result === 'granted' ? 'อนุญาต' : 'ปฏิเสธ',
      fmtDateTime(log.timestamp)
    ];

    lines.push(cells.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','));
  });

  const blob = new Blob(['\uFEFF' + lines.join('\n')], { type:'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `gate-logs-${dateKey(new Date())}.csv`;
  link.click();
  URL.revokeObjectURL(link.href);
  toast('⬇️ ส่งออกไฟล์ CSV แล้ว');
});

// ---------- พิมพ์ PDF ----------
$('btnPrintPdf').addEventListener('click', () => {
  const rows = getFilteredLogs();
  if (rows.length === 0) {
    toast('ไม่มีข้อมูลสำหรับพิมพ์ PDF', 'warning');
    return;
  }

  const dateText = $('logDate').value
    ? new Date($('logDate').value + 'T00:00:00').toLocaleDateString('th-TH', { day:'numeric', month:'long', year:'numeric' })
    : 'ทั้งหมด';

  $('printMeta').textContent = `รายการ ${rows.length} รายการ • วันที่: ${dateText} • พิมพ์เมื่อ ${fmtDateTime(new Date().toISOString())}`;
  window.print();
});

// ---------- Modal / Confirm / Toast ----------
function closeModal(id) {
  $(id).classList.remove('show');
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', event => {
    if (event.target === overlay) closeModal(overlay.id);
  });
});

function askConfirm(title, message, action) {
  $('confirmTitle').textContent = title;
  $('confirmMsg').textContent = message;
  confirmAction = action;
  $('confirmModal').classList.add('show');
}

$('confirmOk').addEventListener('click', async () => {
  const action = confirmAction;
  confirmAction = null;
  closeModal('confirmModal');
  if (action) await action();
});

function toast(message, type = 'success') {
  const element = $('toast');
  element.textContent = message;
  element.className = 'toast show ' + type;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => element.classList.remove('show'), 2600);
}

$('btnClearLogs').addEventListener('click', () => {
  if (logs.length === 0) {
    toast('ไม่มีบันทึกให้ล้าง', 'warning');
    return;
  }

  askConfirm(
    'ล้างบันทึกทั้งหมด?',
    `ต้องการลบบันทึกการเข้า-ออกทั้งหมด ${logs.length} รายการใช่หรือไม่? (ข้อมูลนักศึกษายังอยู่)`,
    async () => {
      try {
        await apiRequest('api/logs.php?all=1', { method: 'DELETE' });
        await loadData();
        toast('🗑️ ล้างบันทึกทั้งหมดแล้ว');
      } catch (error) {
        toast('❌ ' + error.message, 'error');
      }
    }
  );
});

$('btnAddLog').addEventListener('click', () => openLogModal());
$('btnAddStudent').addEventListener('click', () => openStudentModal());
$('btnDateClear').addEventListener('click', clearSelectedDate);
$('logSearch').addEventListener('input', renderLogs);
$('studentSearch').addEventListener('input', renderStudents);
$('btnMenu').addEventListener('click', () => {
  $('sidebar').classList.toggle('show');
  $('backdrop').classList.toggle('show');
});
$('backdrop').addEventListener('click', () => {
  $('sidebar').classList.remove('show');
  $('backdrop').classList.remove('show');
});

// ---------- ปฏิทินภาษาไทย ----------
let calendarMonth = new Date(new Date().getFullYear(), new Date().getMonth(), 1);

function thaiMonthYear(date) {
  return date.toLocaleDateString('th-TH', { month:'long', year:'numeric' });
}

function renderThaiCalendar() {
  const year = calendarMonth.getFullYear();
  const month = calendarMonth.getMonth();
  const selected = $('logDate').value;

  $('dateMonthLabel').textContent = thaiMonthYear(calendarMonth);

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const previousMonthDays = new Date(year, month, 0).getDate();
  let html = '';

  for (let i = 0; i < 42; i++) {
    const dayNumber = i - firstDay + 1;
    let displayDay = dayNumber;
    let cellClass = '';
    let cellDate;

    if (dayNumber < 1) {
      displayDay = previousMonthDays + dayNumber;
      cellClass = 'muted';
      cellDate = new Date(year, month - 1, displayDay);
    } else if (dayNumber > daysInMonth) {
      displayDay = dayNumber - daysInMonth;
      cellClass = 'muted';
      cellDate = new Date(year, month + 1, displayDay);
    } else {
      cellDate = new Date(year, month, displayDay);
    }

    const key = dateKey(cellDate);
    if (key === selected) cellClass += ' selected';
    if (key === dateKey(new Date())) cellClass += ' today';

    html += `<button type="button" class="date-day ${cellClass}" data-date="${key}">${displayDay}</button>`;
  }

  $('dateDays').innerHTML = html;
  $('dateDays').querySelectorAll('.date-day').forEach(button => {
    button.addEventListener('click', () => selectDate(button.dataset.date));
  });
}

function selectDate(value) {
  $('logDate').value = value;
  const date = new Date(value + 'T00:00:00');
  $('logDateDisplay').value = date.toLocaleDateString('th-TH', { day:'numeric', month:'short', year:'numeric' });
  $('datePickerPopup').classList.remove('show');
  renderThaiCalendar();
  renderLogs();
}

function clearSelectedDate() {
  $('logDate').value = '';
  $('logDateDisplay').value = '';
  $('datePickerPopup').classList.remove('show');
  renderThaiCalendar();
  renderLogs();
}

$('logDateDisplay').addEventListener('click', event => {
  event.stopPropagation();
  $('datePickerPopup').classList.toggle('show');
  renderThaiCalendar();
});

$('datePickerPopup').addEventListener('click', event => event.stopPropagation());
$('datePrevMonth').addEventListener('click', () => {
  calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() - 1, 1);
  renderThaiCalendar();
});
$('dateNextMonth').addEventListener('click', () => {
  calendarMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth() + 1, 1);
  renderThaiCalendar();
});
$('dateToday').addEventListener('click', () => selectDate(dateKey(new Date())));
$('dateClear').addEventListener('click', clearSelectedDate);
document.addEventListener('click', () => $('datePickerPopup').classList.remove('show'));

// ---------- นำทางระหว่างหน้า ----------
const pageMeta = {
  dashboard: ['📊 แดชบอร์ดภาพรวม', 'ภาพรวมการใช้งานระบบวันนี้'],
  logs: ['📋 บันทึกการเข้า-ออก', 'ประวัติการแตะบัตร RFID ทั้งหมด — เพิ่ม / แก้ไข / ลบ ได้'],
  students: ['🎓 จัดการข้อมูลนักศึกษา', 'รายชื่อนักศึกษาที่มีสิทธิ์ผ่านไม้กั้น (Whitelist)'],
  solar: ['🔋 ระบบพลังงานแสงอาทิตย์', 'สถานะแผงโซลาร์เซลล์ แบตเตอรี่ และโครงระบบ']
};

function go(page) {
  document.querySelectorAll('.page').forEach(element => element.classList.remove('active'));
  $('page-' + page).classList.add('active');
  document.querySelectorAll('.nav-item').forEach(button => button.classList.toggle('active', button.dataset.page === page));
  $('pageTitle').textContent = pageMeta[page][0];
  $('pageSub').textContent = pageMeta[page][1];
  $('sidebar').classList.remove('show');
  $('backdrop').classList.remove('show');
  window.scrollTo(0, 0);
}

document.querySelectorAll('.nav-item').forEach(button => button.addEventListener('click', () => go(button.dataset.page)));
document.querySelectorAll('[data-goto]').forEach(button => button.addEventListener('click', () => go(button.dataset.goto)));

// ---------- จำลองระบบโซลาร์เซลล์ ----------
function tickSolar() {
  const hour = new Date().getHours();
  const isDay = hour >= 7 && hour < 18;
  let battery = store.get('sgs_battery', 78);

  battery = Math.max(25, Math.min(100, battery + (isDay ? 0.15 + Math.random()*0.25 : -(0.05 + Math.random()*0.15))));
  store.set('sgs_battery', battery);

  const solarV = isDay ? 15.5 + Math.random()*3.5 : 0.1 + Math.random()*0.4;
  const solarA = isDay ? 0.4 + Math.random()*1.6 : 0;
  const batteryV = 11.6 + (battery / 100) * 1.5;
  const state = isDay ? 'กำลังชาร์จจากแผง' : 'ใช้ไฟจากแบตเตอรี่';
  const fillClass = 'batt-fill' + (battery < 35 ? ' low' : battery < 55 ? ' mid' : '');

  $('solarVolt').textContent = solarV.toFixed(1);
  $('solarWatt').textContent = (solarV * solarA).toFixed(1);
  $('solarBattPct').textContent = battery.toFixed(0);
  $('solarBattV').textContent = batteryV.toFixed(1);
  $('solarBattFill').className = fillClass;
  $('solarBattFill').style.width = battery + '%';
  $('solarBattText2').textContent = battery.toFixed(0) + '% — ' + state;
  $('solarState').textContent = state;

  $('dashBattFill').className = fillClass;
  $('dashBattFill').style.width = battery + '%';
  $('dashBattText').textContent = battery.toFixed(0) + '%';
  $('dashBattV').textContent = batteryV.toFixed(1) + ' V';
  $('dashSolarV').textContent = isDay ? solarV.toFixed(1) + ' V' : '— (กลางคืน)';
  $('dashChargeState').textContent = state;
}

// ---------- ธีมกลางวัน / ธีมมืด ----------
function applyTheme(theme) {
  const isDark = theme === 'dark';
  document.documentElement.setAttribute('data-theme', isDark ? 'dark' : 'light');
  localStorage.setItem('sgs_theme', isDark ? 'dark' : 'light');

  if ($('themeIcon')) $('themeIcon').textContent = isDark ? '☀️' : '🌙';
  if ($('themeLabel')) $('themeLabel').textContent = isDark ? 'โหมดกลางวัน' : 'โหมดมืด';
}

function initTheme() {
  applyTheme(localStorage.getItem('sgs_theme') === 'dark' ? 'dark' : 'light');
  $('themeToggle').addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });
}

// ---------- นาฬิกา ----------
function tickClock() {
  const now = new Date();
  $('clock').textContent = now.toLocaleDateString('th-TH', {
    weekday:'short', day:'numeric', month:'short', year:'numeric'
  }) + ' • ' + now.toLocaleTimeString('th-TH');
}

// ---------- เริ่มระบบ ----------
async function initApp() {
  initTheme();
  go('dashboard');
  renderThaiCalendar();
  tickSolar();
  tickClock();
  setInterval(tickSolar, 5000);
  setInterval(tickClock, 1000);
  await checkBackend();
  await loadData();
}

initApp();
