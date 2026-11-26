<?php
declare(strict_types=1);

/**
 * ป๊อปอัพประกาศ/เทศกาล — แสดงหน้าเว็บตามช่วงเวลาและตำแหน่งที่ตั้งไว้
 *
 * placement:
 *   home = หน้าแรกเท่านั้น
 *   main = ทุกหน้าของเว็บไซต์หลัก (รวมหน้าข่าว)
 *   all  = ทุกหน้ารวมเว็บไซต์ย่อยหน่วยงาน
 */

/**
 * @param string $scope ขอบเขตของหน้าที่กำลังแสดง: 'home' | 'main' | 'unit'
 */
function active_popup(string $scope): ?array
{
    $in = match ($scope) {
        'home' => ['home', 'main', 'all'],
        'main' => ['main', 'all'],
        'unit' => ['all'],
        default => ['home'],
    };
    $ph = implode(',', array_fill(0, count($in), '?'));
    try {
        $st = Database::pdo()->prepare(
            "SELECT * FROM popups
             WHERE enabled = 1 AND starts_at <= NOW() AND ends_at >= NOW() AND placement IN ($ph)
             ORDER BY ends_at DESC LIMIT 1"
        );
        $st->execute($in);
        $r = $st->fetch();
        return $r ?: null;
    } catch (Throwable) {
        return null;
    }
}
