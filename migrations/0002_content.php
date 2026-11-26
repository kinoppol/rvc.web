<?php
declare(strict_types=1);

/** Migration 0002 — เนื้อหา: หน่วยงาน (แผนกวิชา) และข่าวสาร */

return [
    'name' => 'เนื้อหา: ตาราง units และ news',
    'up' => function (PDO $db, int &$sql): void {
        $sql += $db->exec(
            "CREATE TABLE IF NOT EXISTS units (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(160) NOT NULL,
                about TEXT NULL,
                contact_name VARCHAR(120) NOT NULL DEFAULT '',
                contact_phone VARCHAR(40) NOT NULL DEFAULT '',
                contact_email VARCHAR(160) NOT NULL DEFAULT '',
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_units_sort (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $sql += $db->exec(
            "CREATE TABLE IF NOT EXISTS news (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                summary VARCHAR(500) NOT NULL DEFAULT '',
                body MEDIUMTEXT NULL,
                category VARCHAR(60) NOT NULL DEFAULT 'ประชาสัมพันธ์',
                unit_id INT UNSIGNED NULL,
                image_path VARCHAR(255) NOT NULL DEFAULT '',
                is_lead TINYINT(1) NOT NULL DEFAULT 0,
                published_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_news_published (published_at),
                KEY idx_news_unit (unit_id),
                CONSTRAINT fk_news_unit FOREIGN KEY (unit_id) REFERENCES units (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
    'down' => function (PDO $db, int &$sql): void {
        $sql += $db->exec('DROP TABLE IF EXISTS news');
        $sql += $db->exec('DROP TABLE IF EXISTS units');
    },
];
