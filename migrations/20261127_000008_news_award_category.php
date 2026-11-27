<?php
declare(strict_types=1);

/** ผลงานนักศึกษาที่นำเข้าจากเว็บเดิมเคยถูกรวมไว้ในหมวดประชาสัมพันธ์ — ย้ายมาหมวดของตัวเอง */
return new class {
    public string $description = 'ย้ายผลงานและรางวัลที่นำเข้าจากเว็บเดิมไปหมวด "ผลงานนักศึกษา"';

    public function up(PDO $db): void
    {
        // updated_at = updated_at: ไม่ให้การย้ายหมวดนับเป็นการแก้ไขข่าว
        $db->exec("UPDATE news SET category = 'award', updated_at = updated_at WHERE category = 'pr' AND source_ref LIKE 'award:%'");
    }

    public function down(PDO $db): void
    {
        $db->exec("UPDATE news SET category = 'pr', updated_at = updated_at WHERE category = 'award'");
    }
};
