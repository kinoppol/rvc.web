# เว็บไซต์วิทยาลัยอาชีวศึกษาร้อยเอ็ด — คู่มือติดตั้ง

## ความต้องการระบบ
- PHP **8.2 ขึ้นไป** (ขยาย: `pdo_mysql`)
- MariaDB / MySQL **10.x ขึ้นไป**
- ไม่ต้องใช้ Composer หรือ framework ใดๆ — PHP ล้วน + PDO

## ขั้นตอนติดตั้ง
1. คัดลอกโฟลเดอร์โปรเจกต์เข้า web root (เช่น `htdocs/rvc.web`)
2. สร้างฐานข้อมูล MySQL/MariaDB ใหม่ (เช่น `rvc_web`)
3. ตั้งสิทธิ์เขียนไดเรกทอรี `data/` และ `runtime/` (สำหรับอัปโหลดรูปและเซสชัน)
4. เปิดเบราว์เซอร์ที่ `https://โดเมนของเว็บ/install.php`
   - **ขั้น 1** ตรวจระบบ (เวอร์ชัน PHP, ขยาย PDO, ความสามารถในการเขียน)
   - **ขั้น 2** กรอกรายการเชื่อมต่อฐานข้อมูล (host, port, user, password, ชื่อฐานข้อมูล)
     ระบบจะทดสอบเชื่อมต่อและ**สร้างตารางทุกตัวผ่าน migrations อัตโนมัติ**
   - **ขั้น 3** แสดงรายการ migrations ที่ติดตั้งแล้ว
   - **ขั้น 4** ตั้งชื่อผู้ใช้และรหัสผ่านผู้ดูแลระบบ (รหัสผ่านถูกเก็บเป็น hash)
5. หลังติดตั้งสำเร็จ **ลบหรือปิดการเข้าถึง `install.php`** ในเซิร์ฟเวอร์จริง
   (ไฟล์จะตรวจว่าติดตั้งแล้ว และบล็อกการทำงานซ้ำอัตโนมัติอยู่แล้ว)

> `install.php` ออกแบบให้**รันซ้ำได้ปลอดภัย** (idempotent) — ตารางใช้
> `CREATE TABLE IF NOT EXISTS`, ข้อมูลตัวอย่างใส่เฉพาะตอนติดตั้งครั้งแรกเท่านั้น

## โครงสร้างไฟล์
```
install.php            ตัวติดตั้ง (รันซ้ำได้)
index.php              หน้าหลัก
news.php / news_view.php   รายการข่าว / อ่านข่าว
units.php / unit.php       รายการแผนก / หน้าแผนก
config/
  config.example.php   ตัวอย่าง config (คัดลอกเป็น config.php ได้ หรือกรอกใน install.php)
  config.php          ข้อมูลเชื่อมต่อ DB (สร้างโดยตัวติดตั้ง — ไม่เข้า git)
core/
  bootstrap.php        โหลด config, session, classes
  Database.php         PDO singleton
  Auth.php             เข้าสู่ระบบผู้ดูแล (session + password_hash)
  Csrf.php             กัน CSRF (ฟิลด์ hidden ในทุกฟอร์ม POST)
  Settings.php         ค่าตั้งค่าจากตาราง settings
  Migrate.php          รัน/ถอย migrations ตามไฟล์ใน migrations/
migrations/            ไฟล์ migration (0001_core, 0002_content, 0003_popups)
admin/                 แผงควบคุมผู้ดูแล
  index.php            ล็อกอิน + แผงควบคุม
  migrations.php       เมนู Migrations: ดูสถานะ / รัน / ถอย migration
  news.php             จัดการข่าว (สร้าง/แก้ไข/ลบ/อัปโหลดรูป)
  units.php            จัดการแผนกวิชา
  popups.php           จัดการป๊อปอัพประกาศ (เลือกแสดงที่ home/main/all)
  settings.php         ตั้งค่าเว็บ (ชื่อเว็บ, ข้อความผู้อำนวยการ ฯลฯ)
  logout.php
views/                 ส่วนหัว/ท้ายเว็บหน้าบ้าน
assets/                CSS
data/uploads/          รูปที่อัปโหลด (สร้างอัตโนมัติ)
runtime/sessions/      ไฟล์เซสชัน
```

## การดูแลระบบ
- **Migrations:** เพิ่มไฟล์ `migrations/000N_ชื่อ.php` (รูปแบบเดียวกับที่มี) แล้วเปิด
  เมนู **Migrations** ใน admin เพื่อติดตั้ง (หรือรัน `install.php` ซ้ำก็ได้)
- **สำรองข้อมูล:** สำรองฐานข้อมูล + โฟลเดอร์ `data/uploads` เป็นประจำ
- **ความปลอดภัย:** เปลี่ยนรหัสผ่านผู้ดูแลเป็นประจำ, จำกัดการเข้าถึง
  `install.php` และ `admin/` ในเซิร์ฟเวอร์จริง, ใช้ HTTPS
