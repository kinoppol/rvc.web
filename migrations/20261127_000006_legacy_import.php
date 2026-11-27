<?php
declare(strict_types=1);

/** ดึงข้อมูลจากเว็บไซต์เดิม — สำเนาไฟล์จากเว็บเดิม และอ้างอิงต้นฉบับเพื่อกันนำเข้าซ้ำ */
return new class {
    public string $description = 'สร้างตาราง legacy_files และคอลัมน์ source_ref ใน news, pages สำหรับนำเข้าจากเว็บเดิม';

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS legacy_files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            src_hash CHAR(40) NOT NULL,
            src_url VARCHAR(1000) NOT NULL,
            path VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NOT NULL DEFAULT '',
            bytes INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_src (src_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec('ALTER TABLE news ADD COLUMN IF NOT EXISTS source_ref VARCHAR(60) NULL AFTER author_id');
        $db->exec('ALTER TABLE news ADD UNIQUE INDEX IF NOT EXISTS uq_news_source (source_ref)');
        $db->exec('ALTER TABLE pages ADD COLUMN IF NOT EXISTS source_ref VARCHAR(120) NULL');
        $db->exec('ALTER TABLE pages ADD UNIQUE INDEX IF NOT EXISTS uq_pages_source (source_ref)');
    }

    public function down(PDO $db): void
    {
        $db->exec('ALTER TABLE pages DROP INDEX IF EXISTS uq_pages_source');
        $db->exec('ALTER TABLE pages DROP COLUMN IF EXISTS source_ref');
        $db->exec('ALTER TABLE news DROP INDEX IF EXISTS uq_news_source');
        $db->exec('ALTER TABLE news DROP COLUMN IF EXISTS source_ref');
        $db->exec('DROP TABLE IF EXISTS legacy_files');   // ไฟล์ใน uploads/legacy ยังอยู่ ลบเองได้ถ้าไม่ใช้แล้ว
    }
};
