<?php
declare(strict_types=1);

/*
 * จุดเริ่มต้นของทุกหน้า (ยกเว้น install.php ที่โหลดเองแบบไม่ต้องมี config)
 * ถ้ายังไม่ได้ติดตั้ง จะพาไปหน้า install.php อัตโนมัติ
 */

define('BASE_PATH', dirname(__DIR__));

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
mb_internal_encoding('UTF-8');

require BASE_PATH . '/app/helpers.php';
require BASE_PATH . '/app/Database.php';
require BASE_PATH . '/app/Auth.php';
require BASE_PATH . '/app/Csrf.php';
require BASE_PATH . '/app/Settings.php';
require BASE_PATH . '/app/Migrator.php';
require BASE_PATH . '/app/Requirements.php';
require BASE_PATH . '/app/content.php';
require BASE_PATH . '/app/Ai.php';
require BASE_PATH . '/app/LegacySite.php';
require BASE_PATH . '/app/Agent.php';
require BASE_PATH . '/app/Facebook.php';
require BASE_PATH . '/app/Drive.php';

$configFile = BASE_PATH . '/config/config.php';
$config = is_file($configFile) ? require $configFile : null;
if (!is_array($config) || empty($config['db']) || !is_file(BASE_PATH . '/storage/installed.lock')) {
    redirect(url('install.php'));
}
$GLOBALS['config'] = $config;
date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Bangkok');
if (!empty($config['app']['debug'])) {
    ini_set('display_errors', '1');
}
Database::configure($config['db']);

set_exception_handler(function (Throwable $ex): void {
    log_error(get_class($ex) . ': ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    $debug = !empty($GLOBALS['config']['app']['debug']);
    abort(500, $debug ? $ex->getMessage() : 'ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง');
});

Auth::startSession();
