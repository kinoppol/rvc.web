<?php
declare(strict_types=1);

// ดึงโพสต์ใหม่จากเพจ Facebook ผ่าน cron (ทางเลือกแทนการดึงเมื่อมีผู้เข้าชมหน้าแรก) เช่น ทุก 10 นาที:
//   */10 * * * * php /path/to/web/cron/facebook_sync.php
// ถ้าเปิด "ดึงอัตโนมัติ" ไว้ จะเคารพรอบเวลาที่ตั้งในระบบ · ใส่ --force เพื่อดึงทันที
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

$force = in_array('--force', $argv, true);
if (!Facebook::configured()) {
    fwrite(STDERR, "ยังไม่ได้ตั้งค่าการเชื่อมต่อเพจ Facebook\n");
    exit(1);
}
$r = Facebook::autoSync($force || Settings::get('fb_auto') !== '1');
if ($r === null) {
    $last = Facebook::lastResult();
    echo isset($last['error']) ? 'ผิดพลาด: ' . $last['error'] . "\n" : "ยังไม่ถึงรอบ หรือกำลังดึงอยู่\n";
    exit(isset($last['error']) ? 1 : 0);
}
printf("นำเข้า %d โพสต์ · ข้าม %d · ไม่สำเร็จ %d\n", $r['added'], $r['skipped'], count($r['failed']));
foreach ($r['failed'] as $f) {
    echo '- ', $f, "\n";
}
