<?php
declare(strict_types=1);

return new class {
    public string $description = 'ภาพสไลด์ในส่วนบนของหน้าแรก (ต่อจากฉากเปิดของเว็บ)';

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS slides (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            image VARCHAR(255) NOT NULL,
            link_url VARCHAR(500) NOT NULL DEFAULT '',
            sort INT NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            start_at DATETIME NULL,
            end_at DATETIME NULL,
            source_ref VARCHAR(60) NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_slides_source (source_ref),
            KEY idx_slides_show (is_enabled, sort)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS slides');
    }
};
