<?php declare(strict_types=1);

/**
 * JsonCodec - Simple JSON encoding/decoding wrapper
 *
 * Provides clean separation between JSON format handling and file operations.
 * This is a lightweight wrapper around json_encode/json_decode with proper error handling.
 */
class JsonCodecException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

class JsonCodec
{
    /**
     * Encode data to JSON string
     *
     * @param mixed $data Data to encode
     * @return string JSON string
     * @throws JsonCodecException If encoding fails
     */
    public static function encode($data)
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new JsonCodecException('Failed to encode JSON: ' . json_last_error_msg());
        }
        return $json;
    }

    /**
     * Decode JSON string to array
     *
     * @param string $json JSON string
     * @return array Decoded data
     * @throws JsonCodecException If decoding fails or result is not an array
     */
    public static function decode($json)
    {
        // json_decode() exceptions require PHP 7.3+ (JSON_THROW_ON_ERROR).
        // The project supports PHP 5.5+, so use json_last_error().
        $data = json_decode($json, true);

        if (!is_array($data)) {
            $err = json_last_error();
            if ($err !== JSON_ERROR_NONE) {
                throw new JsonCodecException('Invalid JSON: ' . json_last_error_msg());
            }
            // Valid JSON but not an object/array.
            throw new JsonCodecException('JSON root must be an object or array');
        }

        return $data;
    }
}
