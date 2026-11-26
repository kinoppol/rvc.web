<?php
declare(strict_types=1);

/** หน่วยงาน/แผนกวิชา ข่าวและประกาศ และหน้าเว็บ */
return new class {
    public string $description = 'สร้างตาราง units, news, pages';

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS units (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NULL,
            slug VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            name_en VARCHAR(160) NOT NULL DEFAULT '',
            kind VARCHAR(20) NOT NULL DEFAULT 'department',
            group_key VARCHAR(20) NOT NULL DEFAULT 'org',
            intro TEXT NULL,
            cover VARCHAR(255) NULL,
            contact TEXT NULL,
            sort INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slug (slug),
            KEY idx_parent (parent_id),
            KEY idx_kind (kind, sort)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS news (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            excerpt VARCHAR(500) NOT NULL DEFAULT '',
            body MEDIUMTEXT NULL,
            cover VARCHAR(255) NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'pr',
            unit_id INT UNSIGNED NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            published_at DATETIME NULL,
            pinned TINYINT(1) NOT NULL DEFAULT 0,
            views INT UNSIGNED NOT NULL DEFAULT 0,
            author_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_public (status, published_at),
            KEY idx_category (category),
            KEY idx_unit (unit_id),
            CONSTRAINT fk_news_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS pages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) NOT NULL,
            title VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NULL,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS news');
        $db->exec('DROP TABLE IF EXISTS pages');
        $db->exec('DROP TABLE IF EXISTS units');
    }
};
