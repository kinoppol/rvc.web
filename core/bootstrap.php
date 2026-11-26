<?php
declare(strict_types=1);

/*
 * โหลดไลบรารีหลักของแอป — ใช้เฉพาะหน้าเว็บสาธารณะและหน้า admin เท่านั้น
 * (install.php จะโหลดเฉพาะไฟล์ที่จำเป็น โดยไม่ต้องมี config.php)
 */

define('BASE_PATH', dirname(__DIR__));

error_reporting(E_ALL);
date_default_timezone_set('Asia/Bangkok');

require BASE_PATH . '/core/helpers.php';
require BASE_PATH . '/core/Database.php';
require BASE_PATH . '/core/Settings.php';
require BASE_PATH . '/core/Auth.php';
require BASE_PATH . '/core/Csrf.php';
require BASE_PATH . '/core/Migrate.php';
require BASE_PATH . '/app/popups.php';

Auth::start();

$configFile = BASE_PATH . '/config/config.php';
if (is_file($configFile)) {
    $config = require $configFile;
    if (isset($config['db']) && is_array($config['db'])) {
        Database::setConfig($config['db']);
    }
}
