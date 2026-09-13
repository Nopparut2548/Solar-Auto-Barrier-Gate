# Solar Gate System (PHP + MySQL)

โปรเจกต์เว็บควบคุมการเข้า-ออกรถจักรยานยนต์ด้วยไม้กั้นอัตโนมัติ ใช้พลังงานแสงอาทิตย์
โดยคง UI เดิมไว้ และเชื่อมข้อมูลนักศึกษา/ประวัติการเข้า-ออกกับ PHP + MySQL

## ฐานข้อมูลที่ระบบนี้ใช้

```text
solar_auto_barrier_gate
```

ตารางที่ใช้:

- `students`
  - `id`
  - `student_code`
  - `student_name`
  - `rfid_uid`
  - `active`
  - `created_at`
  - `updated_at`
- `access_logs`
  - `id`
  - `student_id`
  - `rfid_uid`
  - `status`
  - `tap_at`

โครงสร้างนี้ตรงกับฐานข้อมูลที่ใช้อยู่ใน phpMyAdmin ของโปรเจกต์

## ติดตั้งบน XAMPP

1. วางโฟลเดอร์ไว้ใน `C:\xampp\htdocs\`
2. เปิด Apache และ MySQL
3. ไม่จำเป็นต้องสร้างฐานข้อมูลใหม่ ถ้ามี `solar_auto_barrier_gate` อยู่แล้ว
4. เปิด `http://localhost/Solar_Auto_Barrier_Gate/`

ถ้าใช้โฟลเดอร์ชื่ออื่น ให้เปิด URL ให้ตรงกับชื่อโฟลเดอร์

## ตรวจการเชื่อมต่อ

เปิด:

```text
http://localhost/Solar_Auto_Barrier_Gate/api/health.php
```

ถ้าเชื่อมต่อสำเร็จ จะได้ JSON เช่น:

```json
{
  "success": true,
  "database": "solar_auto_barrier_gate"
}
```

## หมายเหตุ

หน้าเว็บยังคงใช้ `localStorage` เฉพาะค่าธีมและค่าจำลองพลังงานเท่านั้น
ข้อมูลนักศึกษาและประวัติการเข้า-ออกใช้ MySQL ผ่าน PHP API

ฐานข้อมูลเดิมไม่มีคอลัมน์สำหรับเก็บประเภทเข้า/ออกโดยตรง ระบบจึงสลับ `เข้า → ออก → เข้า...` จากประวัติการอนุญาตล่าสุดของนักศึกษา
