<?php
/**
 * @author cheng.xueming
 * @since  2020-11-28
 */

require __DIR__  . '/vendor/autoload.php';
use Symfony\Component\Console\Application;
use CodeScanner\Command\{
    CallerCommand,
    DigraphCommand,
};

$application = new Application();

// ... register commands
$application->add(new CallerCommand('caller'));
$application->add(new DigraphCommand('digraph'));
$application->run();
