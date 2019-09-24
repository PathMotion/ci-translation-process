<?php
define('ROOT', dirname(__FILE__));
require_once ROOT . '/vendor/autoload.php';

use Symfony\Component\Console\Application as ConsoleApplication;
use PathMotion\CI\Command\ImportMo as ImportMoCommand;

$application = new ConsoleApplication('Pathmotion CI', '0.1');
$application->add(new ImportMoCommand());
$application->run();
