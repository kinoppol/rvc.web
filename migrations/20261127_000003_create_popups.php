<?php
declare(strict_types=1);

/** ป๊อปอัพประกาศ / เทศกาล พร้อมช่วงเวลาแสดงผล */
return new class {
    public string $description = 'สร้างตาราง popups';

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS popups (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body VARCHAR(500) NOT NULL DEFAULT '',
            image VARCHAR(255) NULL,
            link_url VARCHAR(500) NOT NULL DEFAULT '',
            start_at DATETIME NOT NULL,
            end_at DATETIME NOT NULL,
            placement VARCHAR(10) NOT NULL DEFAULT 'home',
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_draft TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_window (is_enabled, is_draft, start_at, end_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS popups');
    }
};
