<?php
/**
 * Logger - Centralized logging system
 * Supports multiple log levels and channels
 */

class Logger {
    // Log levels (PSR-3 compatible)
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    private $logPath = '';
    private $channel = 'app';
    private $minLevel = self::INFO;
    
    private static $levels = array(
        self::EMERGENCY => 800,
        self::ALERT     => 700,
        self::CRITICAL  => 600,
        self::ERROR     => 500,
        self::WARNING   => 400,
        self::NOTICE    => 300,
        self::INFO      => 200,
        self::DEBUG     => 100
    );
    
    public function __construct($channel = 'app') {
        $this->logPath = MANTRA_STORAGE . '/logs';
        $this->channel = $channel;
        
        // Set minimum level from config or debug mode
        if (defined('MANTRA_DEBUG') && MANTRA_DEBUG) {
            $this->minLevel = self::DEBUG;
        } else {
            $this->minLevel = self::INFO;
        }
        
        // Override with config if available
        $app = Application::getInstance();
        if ($app) {
            $configLevel = $app->config('log_level');
            if ($configLevel && isset(self::$levels[$configLevel])) {
                $this->minLevel = $configLevel;
            }
        }
        
        // Create logs directory if not exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }
    
    /**
     * Log emergency message
     */
    public function emergency($message, $context = array()) {
        return $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Log alert message
     */
    public function alert($message, $context = array()) {
        return $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = array()) {
        return $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = array()) {
        return $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = array()) {
        return $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Log notice message
     */
    public function notice($message, $context = array()) {
        return $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = array()) {
        return $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = array()) {
        return $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Main log method
     */
    public function log($level, $message, $context = array()) {
        // Check if level should be logged
        if (!$this->shouldLog($level)) {
            return false;
        }
        
        // Format message
        $formattedMessage = $this->formatMessage($level, $message, $context);
        
        // Write to file
        return $this->writeLog($level, $formattedMessage);
    }
    
    /**
     * Check if level should be logged
     */
    private function shouldLog($level) {
        if (!isset(self::$levels[$level]) || !isset(self::$levels[$this->minLevel])) {
            return false;
        }
        
        return self::$levels[$level] >= self::$levels[$this->minLevel];
    }
    
    /**
     * Format log message
     */
    private function formatMessage($level, $message, $context) {
        // Replace placeholders in message
        $message = $this->interpolate($message, $context);
        
        // Build log entry
        $entry = sprintf(
            "[%s] %s.%s: %s",
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $message
        );
        
        // Add context if present
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Add stack trace for errors
        if (in_array($level, array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY))) {
            if (isset($context['exception']) && $context['exception'] instanceof Exception) {
                $entry .= "\n" . $this->formatException($context['exception']);
            }
        }
        
        return $entry;
    }
    
    /**
     * Interpolate context values into message placeholders
     */
    private function interpolate($message, $context) {
        $replace = array();
        
        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        return strtr($message, $replace);
    }
    
    /**
     * Format exception for logging
     */
    private function formatException($exception) {
        return sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }
    
    /**
     * Write log to file
     */
    private function writeLog($level, $message) {
        // Determine log file based on level
        if (in_array($level, array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY))) {
            $filename = 'error-' . date('Y-m-d') . '.log';
        } else {
            $filename = $this->channel . '-' . date('Y-m-d') . '.log';
        }
        
        $logFile = $this->logPath . '/' . $filename;
        
        return file_put_contents($logFile, $message . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Set minimum log level
     */
    public function setMinLevel($level) {
        if (isset(self::$levels[$level])) {
            $this->minLevel = $level;
        }
        return $this;
    }
    
    /**
     * Get log file path
     */
    public function getLogPath() {
        return $this->logPath;
    }
    
    /**
     * Clear old log files
     */
    public function clearOldLogs($days = 30) {
        $files = glob($this->logPath . '/*.log');
        $now = time();
        $deleted = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    if (unlink($file)) {
                        $deleted++;
                    }
                }
            }
        }
        
        return $deleted;
    }
}
