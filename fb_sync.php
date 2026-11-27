<?php
declare(strict_types=1);

/*
 * ดึงโพสต์ใหม่จากเพจ Facebook อัตโนมัติเมื่อถึงรอบ — หน้าแรกเรียกแบบเบื้องหลัง (ไม่ทำให้หน้าเว็บช้า)
 * ทำงานจริงไม่เกิน 1 ครั้งต่อรอบที่ตั้งไว้ ไม่ว่าจะถูกเรียกกี่ครั้ง
 */
require __DIR__ . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!is_post()) {
    http_response_code(405);
    exit('{"ran":false}');
}
ignore_user_abort(true);
set_time_limit(300);
$r = Facebook::autoSync();
echo json_encode(['ran' => $r !== null, 'added' => $r['added'] ?? 0]);
