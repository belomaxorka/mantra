<?php
/**
 * Logging helpers
 */

/**
 * Get logger instance
 */
function logger($channel = 'app')
{
    static $loggers = array();
    if (!isset($loggers[$channel])) {
        $minLevel = (defined('MANTRA_DEBUG') && MANTRA_DEBUG) ? Logger::DEBUG : Logger::INFO;

        // Prefer early-loaded config (no Application dependency).
        if (isset($GLOBALS['MANTRA_CONFIG']) && is_array($GLOBALS['MANTRA_CONFIG'])) {
            $level = Config::getNested($GLOBALS['MANTRA_CONFIG'], 'logging.level', null);
            if (!empty($level)) {
                $minLevel = $level;
            }
        }

        $loggers[$channel] = new Logger($channel, array(
            'minLevel' => $minLevel
        ));
    }
    return $loggers[$channel];
}
