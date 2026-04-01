<?php

/**
 * ErrorHandler - Centralized PHP error/exception/fatal handling.
 *
 * Logs to a dedicated "php" channel.
 */
class ErrorHandler
{
    private static $registered = false;
    private static $logger = null;

    private static function isCli()
    {
        return MANTRA_CLI;
    }

    private static function writeCli($text)
    {
        fwrite(STDERR, $text);
    }

    /**
     * Register handlers.
     *
     * @param Logger|null $logger
     */
    public static function register($logger = null)
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        self::$logger = $logger;

        set_error_handler(array(__CLASS__, 'handleError'));
        set_exception_handler(array(__CLASS__, 'handleException'));
        register_shutdown_function(array(__CLASS__, 'handleShutdown'));
    }

    private static function getLogger()
    {
        if (self::$logger instanceof Logger) {
            return self::$logger;
        }

        $minLevel = Logger::resolveLevel();
        self::$logger = new Logger('php', array('minLevel' => $minLevel));
        return self::$logger;
    }

    public static function handleError($severity, $message, $file, $line)
    {
        // Respect error_reporting mask.
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $level = self::mapErrorSeverityToLevel($severity);

        self::getLogger()->log($level, 'PHP error: {message}', array(
            'message' => $message,
            'severity' => $severity,
            'file' => $file,
            'line' => $line,
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null
        ));

        // Let PHP continue with its internal handler as well.
        return false;
    }

    public static function handleException($exception)
    {
        self::getLogger()->error('Uncaught exception', array(
            'exception' => $exception,
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null
        ));

        if (self::isCli()) {
            // CLI-friendly output
            self::writeCli("Uncaught exception: " . $exception->getMessage() . "\n");
            if (MANTRA_DEBUG) {
                self::writeCli($exception->getTraceAsString() . "\n");
            }
            exit(1);
        }

        // Discard any partial output left in ob buffers (e.g. from View)
        self::cleanOutputBuffers();

        // Preserve existing application behavior: show a generic 500 if not in debug.
        http_response_code(500);

        if (MANTRA_DEBUG) {
            echo '<h1>Error</h1>';
            echo '<p>' . htmlspecialchars($exception->getMessage()) . '</p>';
            echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
        } else {
            echo '<h1>Something went wrong</h1>';
            echo '<p>Please try again later.</p>';
        }
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if (!$error) {
            return;
        }

        if (!self::isFatalErrorType($error['type'])) {
            return;
        }

        self::getLogger()->critical('Fatal error during shutdown: {message}', array(
            'message' => isset($error['message']) ? $error['message'] : null,
            'severity' => isset($error['type']) ? $error['type'] : null,
            'file' => isset($error['file']) ? $error['file'] : null,
            'line' => isset($error['line']) ? $error['line'] : null,
            'url' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'method' => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null
        ));

        if (self::isCli()) {
            $msg = isset($error['message']) ? $error['message'] : 'unknown error';
            $file = isset($error['file']) ? $error['file'] : 'unknown file';
            $line = isset($error['line']) ? $error['line'] : 0;
            self::writeCli("Fatal error: {$msg} in {$file}:{$line}\n");
            exit(1);
        }

        // Discard any partial output left in ob buffers
        self::cleanOutputBuffers();

        // Ensure a 500 response for fatals.
        if (!headers_sent()) {
            http_response_code(500);
        }
    }

    /**
     * Discard all open output buffers so error output reaches the client.
     */
    private static function cleanOutputBuffers()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private static function isFatalErrorType($type)
    {
        return in_array($type, array(
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_CORE_WARNING,
            E_COMPILE_ERROR,
            E_COMPILE_WARNING,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR
        ), true);
    }

    private static function mapErrorSeverityToLevel($severity)
    {
        switch ($severity) {
            case E_ERROR:
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                return Logger::ERROR;

            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                return Logger::CRITICAL;

            case E_WARNING:
            case E_USER_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return Logger::WARNING;

            case E_NOTICE:
            case E_USER_NOTICE:
            case E_STRICT:
                return Logger::NOTICE;

            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return Logger::NOTICE;

            default:
                return Logger::INFO;
        }
    }
}
