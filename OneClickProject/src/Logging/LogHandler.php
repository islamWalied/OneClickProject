<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
class LogHandler
{
    public function __invoke(array $config)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callingClass = isset($backtrace[1]['class']) ? class_basename($backtrace[1]['class']) : 'Bootstrap';
        $logFilePath = storage_path('logs/' . now()->format('Y-m-d') . '.log');
        $logger = new Logger('custom-daily');
        $format = "[%datetime%] [$callingClass] %level_name%: %message% %context%\n";
        $formatter = new LineFormatter($format, null, true, true);
        $handler = new StreamHandler($logFilePath, Logger::DEBUG);
        $handler->setFormatter($formatter);
        $logger->pushHandler($handler);
        return $logger;
    }
}
