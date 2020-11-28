<?php
/**
 * @author cheng.xueming
 * @since  2020-11-28
 */

require __DIR__  . '/vendor/autoload.php';

use \CodeScanner\Application;

$app = new Application('./test.yaml');
$app->run();