<?php

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Morbo\Domru\Domru;

require __DIR__ . '/bootstrap.php';

$domru = new Domru();

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$domru->setupLogger($logger);

$domru->run();