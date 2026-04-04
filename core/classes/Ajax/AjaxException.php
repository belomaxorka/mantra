<?php declare(strict_types=1);

namespace Ajax;

/**
 * Exception thrown by AJAX action handlers.
 *
 * The exception code is used as the HTTP status code in the JSON response.
 */
class AjaxException extends \Exception
{
    public function __construct(string $message, int $httpCode = 400, \Throwable $previous = null)
    {
        parent::__construct($message, $httpCode, $previous);
    }
}
