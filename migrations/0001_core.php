<?php
declare(strict_types=1);

/** Migration 0001 — โครงสร้างหลัก: ผู้ใช้และการตั้งค่า */

return [
    'name' => 'โครงสร้างหลัก: ตาราง users และ settings',
    'up' => function (PDO $db, int &$sql): void {
        $sql += $db->exec(
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(60) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                display_name VARCHAR(120) NOT NULL DEFAULT '',
                role VARCHAR(20) NOT NULL DEFAULT 'admin',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_users_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $sql += $db->exec(
            "CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(80) NOT NULL,
                value TEXT NOT NULL,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    },
    'down' => function (PDO $db, int &$sql): void {
        $sql += $db->exec('SET FOREIGN_KEY_CHECKS = 0');
        $sql += $db->exec('DROP TABLE IF EXISTS settings');
        $sql += $db->exec('DROP TABLE IF EXISTS users');
        $sql += $db->exec('SET FOREIGN_KEY_CHECKS = 1');
    },
];
