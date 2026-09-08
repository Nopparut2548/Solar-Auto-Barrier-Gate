# Solar Gate System (PHP + MySQL)

โปรเจกต์เว็บควบคุมการเข้า-ออกรถจักรยานยนต์ด้วยไม้กั้นอัตโนมัติ ใช้พลังงานแสงอาทิตย์
โดยคง UI เดิมไว้ และเปลี่ยนการเก็บข้อมูลนักศึกษา/ประวัติจาก localStorage มาเป็น PHP + MySQL

## โครงสร้าง

```text
solar-gate-system/
├── index.php
├── database.sql
├── css/
│   └── style.css
├── js/
│   └── app.js
└── api/
    ├── config.php
    ├── bootstrap.php
    ├── health.php
    ├── students.php
    ├── logs.php
    └── scan.php
```

## ติดตั้งบน XAMPP

1. วางโฟลเดอร์ `solar-gate-system` ไว้ใน `C:\xampp\htdocs\`
2. เปิด Apache และ MySQL ใน XAMPP
3. เข้า phpMyAdmin แล้ว Import ไฟล์ `database.sql`
4. เปิด `http://localhost/solar-gate-system/`

ค่าเริ่มต้นของ XAMPP ที่โปรเจกต์ใช้คือ MySQL `root` และไม่มีรหัสผ่าน หากเครื่องของผู้ใช้ตั้งรหัสผ่านไว้ ให้แก้ `api/config.php`

## API หลัก

- `GET api/health.php` ตรวจการเชื่อมต่อฐานข้อมูล
- `GET/POST/PUT/DELETE api/students.php` จัดการข้อมูลนักศึกษา
- `GET/POST/PUT/DELETE api/logs.php` จัดการประวัติการเข้า-ออก
- `DELETE api/logs.php?all=1` ล้างประวัติทั้งหมด
- `POST api/scan.php` จำลอง/รับการแตะบัตรและให้เซิร์ฟเวอร์เป็นผู้ตรวจสิทธิ์

### เมื่อเชื่อม ESP32 จริง

ESP32 สามารถส่ง UID ไปที่ `api/scan.php` เช่น

```json
{
  "rfid_uid": "A1 B2 C3 D4"
}
```

ฝั่ง PHP จะตรวจสอบกับ `students.rfid_uid` และ `students.status` แล้วตอบกลับว่าอนุญาตหรือปฏิเสธ
จึงเหมาะสำหรับต่อยอดเป็น ESP32 → PHP → MySQL → ควบคุมไม้กั้น
