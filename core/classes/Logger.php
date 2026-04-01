<?php
/**
 * Logger - Centralized logging system
 * Supports multiple log levels and channels
 */

class Logger implements \Psr\Log\LoggerInterface
{
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
    private $includeContext = true;
    private $dateFormat = 'Y-m-d H:i:s';

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

    /**
     * @param string $channel
     * @param array  $options Supported: logPath, minLevel, includeContext, dateFormat
     */
    public function __construct($channel = 'app', $options = array())
    {
        $this->channel = $channel;

        $this->logPath = isset($options['logPath'])
            ? $options['logPath']
            : (MANTRA_STORAGE . '/logs');

        if (isset($options['minLevel']) && isset(self::$levels[$options['minLevel']])) {
            $this->minLevel = $options['minLevel'];
        }

        if (isset($options['includeContext'])) {
            $this->includeContext = (bool) $options['includeContext'];
        }

        if (!empty($options['dateFormat'])) {
            $this->dateFormat = $options['dateFormat'];
        }

        // Create logs directory if not exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function emergency($message, array $context = array())
    {
        return $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = array())
    {
        return $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        return $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = array())
    {
        return $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = array())
    {
        return $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = array())
    {
        return $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = array())
    {
        return $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = array())
    {
        return $this->log(self::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        if (!$this->shouldLog($level)) {
            return false;
        }

        $formattedMessage = $this->formatMessage($level, $message, $context);
        return $this->writeLog($level, $formattedMessage);
    }

    private function shouldLog($level)
    {
        if (!isset(self::$levels[$level]) || !isset(self::$levels[$this->minLevel])) {
            return false;
        }

        return self::$levels[$level] >= self::$levels[$this->minLevel];
    }

    private function formatMessage($level, $message, $context)
    {
        $message = $this->interpolate($message, $context);

        $entry = sprintf(
            "[%s] %s.%s: %s",
            date($this->dateFormat),
            $this->channel,
            strtoupper($level),
            $message
        );

        if ($this->includeContext && !empty($context)) {
            $entry .= ' ' . json_encode($this->normalizeContext($context), JSON_UNESCAPED_UNICODE);
        }

        if (in_array($level, array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY), true)) {
            if (isset($context['exception']) && $this->isThrowable($context['exception'])) {
                $entry .= "\n" . $this->formatException($context['exception']);
            }
        }

        return $entry;
    }

    private function interpolate($message, $context)
    {
        $replace = array();

        foreach ($context as $key => $val) {
            if (is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    private function isThrowable($value)
    {
        if ($value instanceof Exception) {
            return true;
        }

        if (interface_exists('Throwable') && $value instanceof Throwable) {
            return true;
        }

        return false;
    }

    private function formatException($exception)
    {
        return sprintf(
            "Exception: %s in %s:%d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }

    private function normalizeContext($context)
    {
        if (!is_array($context)) {
            return $context;
        }

        $out = array();
        foreach ($context as $key => $val) {
            if ($key === 'exception' && $this->isThrowable($val)) {
                $out[$key] = array(
                    'class' => get_class($val),
                    'message' => $val->getMessage(),
                    'file' => $val->getFile(),
                    'line' => $val->getLine()
                );
                continue;
            }

            if (is_resource($val)) {
                $out[$key] = 'resource';
                continue;
            }

            if (is_object($val) && !method_exists($val, '__toString')) {
                $out[$key] = array('object' => get_class($val));
                continue;
            }

            $out[$key] = $val;
        }

        return $out;
    }

    private function writeLog($level, $message)
    {
        // Determine log file based on level
        if (in_array($level, array(self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY), true)) {
            $filename = 'error-' . date('Y-m-d') . '.log';
        } else {
            $filename = $this->channel . '-' . date('Y-m-d') . '.log';
        }

        $logFile = $this->logPath . '/' . $filename;
        return file_put_contents($logFile, $message . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    /**
     * Resolve the effective minimum log level from config/debug flag.
     * Works before Application exists (uses globals only).
     */
    public static function resolveLevel()
    {
        $level = MANTRA_DEBUG ? self::DEBUG : self::INFO;

        $cfgLevel = Config::getNested($GLOBALS['MANTRA_CONFIG'], 'logging.level', null);
        if (!empty($cfgLevel)) {
            $level = $cfgLevel;
        }

        return $level;
    }

    public function setMinLevel($level)
    {
        if (isset(self::$levels[$level])) {
            $this->minLevel = $level;
        }
        return $this;
    }

    public function getLogPath()
    {
        return $this->logPath;
    }

    public function clearOldLogs($days = 30)
    {
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
