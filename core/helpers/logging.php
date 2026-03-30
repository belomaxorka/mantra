<?php
/**
 * Logging helpers
 */

/**
 * Resolve the effective minimum log level.
 *
 * Priority: config "logging.level" > MANTRA_DEBUG flag > default (INFO).
 *
 * @return string One of the Logger::* level constants
 */
function resolve_log_level()
{
    $level = (defined('MANTRA_DEBUG') && MANTRA_DEBUG) ? Logger::DEBUG : Logger::INFO;

    if (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) {
        $cfgLevel = Config::getNested($GLOBALS['MANTRA_CONFIG'], 'logging.level', null);
        if (!empty($cfgLevel)) {
            $level = $cfgLevel;
        }
    }

    return $level;
}

/**
 * Get logger instance
 */
function logger($channel = 'app')
{
    static $loggers = array();
    if (!isset($loggers[$channel])) {
        $loggers[$channel] = new Logger($channel, array(
            'minLevel' => resolve_log_level()
        ));
    }
    return $loggers[$channel];
}
