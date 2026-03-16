<?php

namespace Psr\Log;

/**
 * Minimal PSR-3 LogLevel constants (vendored, no Composer required).
 */
final class LogLevel
{
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    private function __construct() {}
}
