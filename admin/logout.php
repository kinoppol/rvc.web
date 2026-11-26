<?php
declare(strict_types=1);

require __DIR__ . '/../core/bootstrap.php';
require_installed();

Auth::logout();
redirect(url('admin/index.php'));
