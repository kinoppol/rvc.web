# เว็บไซต์วิทยาลัยอาชีวศึกษาร้อยเอ็ด — คู่มือติดตั้งและดูแลระบบ

## ความต้องการของระบบ
- **PHP 8.2 ขึ้นไป** พร้อมส่วนขยาย `pdo`, `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `session`, `openssl`
  (แนะนำเพิ่ม: `gd`, `intl`, `zip`, `curl`, OPcache)
- **MariaDB 10.0 ขึ้นไป** (หรือ MySQL 8+)
- Apache ที่อ่าน `.htaccess` ได้ (`AllowOverride All`) — ใช้บล็อกโฟลเดอร์ `app/ config/ storage/ migrations/`
  ถ้าใช้ Nginx ให้ตั้ง `deny all` ให้โฟลเดอร์เหล่านี้เอง และห้ามรันไฟล์ PHP ใน `uploads/`
- ไม่ต้องใช้ Composer — PHP ล้วน

## ติดตั้ง
1. วางไฟล์ไว้ใน web root เช่น `htdocs/rvc.web`
2. ให้ผู้ใช้ของเว็บเซิร์ฟเวอร์เขียนได้ที่ `config/`, `storage/` (รวม `sessions/`, `logs/`), `uploads/`
   และ `migrations/` (ไม่บังคับ — ใช้สร้างไฟล์ migration จากหน้า admin)
3. เปิด `http://โดเมน/rvc.web/install.php` แล้วทำตาม 5 ขั้น
   1. **ตรวจระบบ** — เวอร์ชัน PHP, แพ็กเกจที่ต้องมี/แนะนำ, ค่า php.ini, สิทธิ์อ่าน/เขียนโฟลเดอร์
   2. **ฐานข้อมูล** — ทดสอบเชื่อมต่อ ตรวจเวอร์ชัน MariaDB สร้างฐานข้อมูล (utf8mb4) และบันทึก `config/config.php`
   3. **โครงสร้างข้อมูล** — รัน migrations ทั้งหมด
   4. **ผู้ดูแลระบบ** — ตั้งชื่อผู้ใช้/รหัสผ่าน admin (เลือกใส่ข่าวตัวอย่างได้)
   5. **เสร็จสิ้น** — สร้าง `storage/installed.lock` เพื่อล็อกตัวติดตั้ง

## การติดตั้งซ้ำ
`install.php` รันซ้ำได้เสมอ:
- **หลังติดตั้งแล้ว** หน้าจะถูกล็อก — ต้องเข้าสู่ระบบเป็นผู้ดูแลก่อนจึงใช้ได้
- **อัปเกรด** — ขั้น 3 รันเฉพาะ migration ใหม่ ข้อมูลเดิมไม่หาย
- **ย้ายฐานข้อมูล** — ขั้น 2 แก้ค่าการเชื่อมต่อแล้วบันทึกใหม่
- **รีเซ็ตรหัสผู้ดูแล** — ขั้น 4 กรอกชื่อผู้ใช้เดิมพร้อมรหัสผ่านใหม่
- **ติดตั้งใหม่ทั้งหมด** — ขั้น 3 › "ติดตั้งใหม่ทั้งหมด" (พิมพ์ชื่อฐานข้อมูลเพื่อยืนยัน) ระบบเรียก `down()` ของทุก migration
  ลบเฉพาะตารางของระบบนี้ แล้วติดตั้งใหม่
- **ลืมรหัสผ่าน / เข้าระบบไม่ได้** — ลบไฟล์ `storage/installed.lock` บนเซิร์ฟเวอร์ แล้วเปิด `install.php`
  (ข้อมูลไม่หาย) ไปที่ขั้น 4 ตั้งรหัสผ่านใหม่ แล้วกด "เสร็จสิ้น" เพื่อล็อกอีกครั้ง

## เมนู Migrations (ระบบจัดการ › ผู้ดูแลระบบ › Migrations)
- ดูสถานะทุก migration: ติดตั้งแล้ว / รอติดตั้ง / ไฟล์ถูกแก้หลังรัน (ตรวจด้วย checksum) / ไม่พบไฟล์
- รันทั้งหมดที่รอ หรือรันทีละรายการ — แต่ละครั้งเป็น "batch" ใหม่
- ย้อนกลับ batch ล่าสุด (พิมพ์ `ROLLBACK` ยืนยัน) หรือย้อนกลับทีละรายการ
- สร้างไฟล์ migration ใหม่จากแม่แบบ, ดูประวัติการรัน, ดูขนาดตาราง
- กันการรันพร้อมกันด้วย `GET_LOCK` ของ MariaDB

### เขียน migration
ไฟล์ `migrations/YYYYMMDD_HHMMSS_ชื่อ.php` รันตามลำดับชื่อไฟล์:
```php
<?php
return new class {
    public string $description = 'เพิ่มคอลัมน์ subtitle ในข่าว';
    public function up(PDO $db): void {
        $db->exec("ALTER TABLE news ADD COLUMN IF NOT EXISTS subtitle VARCHAR(255) NULL AFTER title");
    }
    public function down(PDO $db): void {
        $db->exec("ALTER TABLE news DROP COLUMN IF EXISTS subtitle");
    }
};
```
- คำสั่ง DDL ของ MariaDB commit อัตโนมัติ (ย้อนด้วย transaction ไม่ได้) จึงควรใช้ `IF NOT EXISTS` / `IF EXISTS` ให้รันซ้ำได้
- อย่าแก้ไฟล์ที่รันไปแล้ว — สร้าง migration ใหม่แทน

## โครงสร้างไฟล์
```
install.php                ตัวติดตั้ง (รันซ้ำได้)
index.php news.php news_view.php units.php unit.php page.php   หน้าเว็บสาธารณะ
admin/                     ระบบจัดการ (ข่าว หน้าเว็บ ป๊อปอัพ หน่วยงาน ผู้ใช้ ตั้งค่า Migrations ตรวจสอบระบบ)
app/                       แกนระบบ: bootstrap, Database, Auth, Csrf, Migrator, Requirements, Settings, views/
migrations/                ไฟล์ migration
config/config.php          สร้างโดยตัวติดตั้ง (ไม่เข้า git)
storage/                   installed.lock, sessions/, logs/ (ไม่เข้า git)
uploads/                   รูปที่อัปโหลด (ไม่เข้า git, ห้ามรันสคริปต์)
assets/                    CSS/JS
*.dc.html, canvas.json     ไฟล์ดีไซน์ต้นแบบ
```

## สิทธิ์ผู้ใช้
| สิทธิ์ | ทำได้ |
|---|---|
| ผู้ดูแลระบบ | ทุกเมนู รวม Migrations, ผู้ใช้, ตั้งค่า, ตัวติดตั้ง |
| ผู้เขียนข่าว | เขียนข่าวของหน่วยงานตน (ต้องรอผู้ดูแลอนุมัติ), ตั้งป๊อปอัพ |

## ความปลอดภัย
รหัสผ่าน bcrypt · CSRF ทุกฟอร์ม · จำกัดการเข้าสู่ระบบผิด 5 ครั้ง/15 นาที · ตรวจชนิดไฟล์อัปโหลดจากเนื้อไฟล์ ·
ข้อความทุกจุด escape ก่อนแสดง · บนเซิร์ฟเวอร์จริงควรใช้ HTTPS และผู้ใช้ฐานข้อมูลที่มีสิทธิ์เฉพาะฐานข้อมูลนี้
