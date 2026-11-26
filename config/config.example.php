<?php
declare(strict_types=1);

/*
 * ไฟล์ตัวอย่าง — install.php จะสร้าง config.php เอง
 * (ไฟล์ config.php จริงจะถูก git ignore และไม่เก็บรหัสผ่านใน repository)
 */

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'rvc_web',
        'user' => 'root',
        'pass' => '',
    ],
    'site' => [
        'name' => 'วิทยาลัยอาชีวศึกษาร้อยเอ็ด',
    ],
];
