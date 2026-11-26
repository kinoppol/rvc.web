<?php
declare(strict_types=1);

/** Migration 0003 — ป๊อปอัพประกาศ/เทศกาล */

return [
    'name' => 'ป๊อปอัพประกาศ: ตาราง popups',
    'up' => function (PDO $db, int &$sql): void {
        $sql += $db->exec(
            "CREATE TABLE IF NOT EXISTS popups (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                link_url VARCHAR(500) NOT NULL DEFAULT '',
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                placement ENUM('home','main','all') NOT NULL DEFAULT 'home',
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_popups_window (enabled, starts_at, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
    'down' => function (PDO $db, int &$sql): void {
        $sql += $db->exec('DROP TABLE IF EXISTS popups');
    },
];
