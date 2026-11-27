<?php
declare(strict_types=1);

/*
 * รายการรูปจากโฟลเดอร์ Google Drive ที่แนบในข่าว (JSON) — หน้าอ่านข่าวเรียกเบื้องหลัง
 * รับเฉพาะเลขข่าว จึงอ่านได้เฉพาะโฟลเดอร์ที่ลิงก์ไว้ในข่าวนั้น (และโฟลเดอร์ย่อย)
 */
require __DIR__ . '/app/bootstrap.php';

$n = Database::one('SELECT body, status, published_at FROM news WHERE id = ?', [input_int('id')]);
$isPublic = $n && $n['status'] === 'published' && $n['published_at'] && strtotime($n['published_at']) <= time();
$allowed = $n && ($isPublic || Auth::user());
session_write_close();   // อ่าน Drive อาจใช้เวลาหลายวินาที ไม่ให้หน้าอื่นของผู้ใช้ต้องรอ

header('Content-Type: application/json; charset=utf-8');
if (!$allowed) {
    http_response_code(404);
    exit('{"groups":[],"partial":false}');
}
set_time_limit(90);
echo json_encode(Drive::gallery(Drive::folderIds((string) $n['body'])), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
