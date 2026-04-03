<?php declare(strict_types=1);

namespace Psr\Log;

/**
 * Minimal PSR-3 LoggerInterface (vendored, no Composer required).
 */
interface LoggerInterface
{
    public function emergency($message, array $context = []);
    public function alert($message, array $context = []);
    public function critical($message, array $context = []);
    public function error($message, array $context = []);
    public function warning($message, array $context = []);
    public function notice($message, array $context = []);
    public function info($message, array $context = []);
    public function debug($message, array $context = []);

    /**
     * @param mixed  $level
     * @param string $message
     */
    public function log($level, $message, array $context = []);
}
