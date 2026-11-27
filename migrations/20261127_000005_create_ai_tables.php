<?php
declare(strict_types=1);

/** ผู้ช่วย AI — ผู้ให้บริการ API ภายนอก (หลายราย), โมเดลที่เปิดใช้งาน และบันทึกการใช้งาน */
return new class {
    public string $description = 'สร้างตาราง ai_providers, ai_models, ai_usage_log สำหรับผู้ช่วย AI';

    public function up(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS ai_providers (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            preset VARCHAR(30) NOT NULL DEFAULT 'custom',
            base_url VARCHAR(500) NOT NULL,
            api_key TEXT NULL,
            auth_header VARCHAR(60) NOT NULL DEFAULT 'Authorization',
            auth_prefix VARCHAR(30) NOT NULL DEFAULT 'Bearer ',
            models_path VARCHAR(120) NOT NULL DEFAULT '/models',
            chat_path VARCHAR(120) NOT NULL DEFAULT '/chat/completions',
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_test_at DATETIME NULL,
            last_test_ok TINYINT(1) NULL,
            last_test_msg VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS ai_models (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            provider_id INT UNSIGNED NOT NULL,
            model_id VARCHAR(190) NOT NULL,
            label VARCHAR(190) NOT NULL DEFAULT '',
            context_length INT UNSIGNED NULL,
            is_free TINYINT(1) NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            is_manual TINYINT(1) NOT NULL DEFAULT 0,
            is_available TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_provider_model (provider_id, model_id),
            KEY idx_enabled (is_enabled),
            CONSTRAINT fk_ai_models_provider FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS ai_usage_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            provider_id INT UNSIGNED NULL,
            model_id VARCHAR(190) NOT NULL DEFAULT '',
            task VARCHAR(30) NOT NULL DEFAULT '',
            ok TINYINT(1) NOT NULL DEFAULT 1,
            prompt_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            completion_tokens INT UNSIGNED NOT NULL DEFAULT 0,
            duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
            error VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_created (created_at),
            KEY idx_user (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS ai_usage_log');
        $db->exec('DROP TABLE IF EXISTS ai_models');
        $db->exec('DROP TABLE IF EXISTS ai_providers');
    }
};
