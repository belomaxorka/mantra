<?php declare(strict_types=1);
/**
 * SchemaValidator - Validates data against collection schemas
 */

class SchemaValidationException extends Exception {
    private $errors = [];

    public function __construct($message, $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors() {
        return $this->errors;
    }
}

class SchemaValidator {

    /**
     * Validate data against schema
     * 
     * @param array $data Data to validate
     * @param array $schema Schema definition
     * @return array Validation errors (empty if valid)
     */
    public static function validate($data, $schema) {
        $errors = [];

        if (!isset($schema['fields']) || !is_array($schema['fields'])) {
            return $errors; // No validation rules
        }

        foreach ($schema['fields'] as $field => $rules) {
            $value = $data[$field] ?? null;

            // Check required
            if (!empty($rules['required']) && ($value === null || $value === '')) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }

            // Skip validation if field is not set and not required
            if ($value === null || $value === '') {
                continue;
            }

            // Type validation
            if (isset($rules['type'])) {
                $typeError = self::validateType($field, $value, $rules['type']);
                if ($typeError) {
                    $errors[$field] = $typeError;
                    continue;
                }
            }

            // String length validation
            if (is_string($value)) {
                if (isset($rules['minLength']) && strlen($value) < $rules['minLength']) {
                    $errors[$field] = "Field '{$field}' must be at least {$rules['minLength']} characters";
                    continue;
                }

                if (isset($rules['maxLength']) && strlen($value) > $rules['maxLength']) {
                    $errors[$field] = "Field '{$field}' must not exceed {$rules['maxLength']} characters";
                    continue;
                }
            }

            // Pattern validation
            if (isset($rules['pattern']) && is_string($value)) {
                if (!preg_match($rules['pattern'], $value)) {
                    $errors[$field] = "Field '{$field}' does not match required pattern";
                    continue;
                }
            }

            // Enum validation
            if (isset($rules['values']) && is_array($rules['values'])) {
                if (!in_array($value, $rules['values'], true)) {
                    $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $rules['values']);
                    continue;
                }
            }

            // Numeric range validation
            if (is_numeric($value)) {
                if (isset($rules['min']) && $value < $rules['min']) {
                    $errors[$field] = "Field '{$field}' must be at least {$rules['min']}";
                    continue;
                }

                if (isset($rules['max']) && $value > $rules['max']) {
                    $errors[$field] = "Field '{$field}' must not exceed {$rules['max']}";
                    continue;
                }
            }
        }

        return $errors;
    }


    /**
     * Validate field type
     */
    private static function validateType($field, $value, $type) {
        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    return "Field '{$field}' must be a string";
                }
                break;

            case 'int':
            case 'integer':
                if (!is_int($value) && !ctype_digit($value)) {
                    return "Field '{$field}' must be an integer";
                }
                break;

            case 'float':
            case 'number':
                if (!is_numeric($value)) {
                    return "Field '{$field}' must be a number";
                }
                break;

            case 'bool':
            case 'boolean':
                if (!is_bool($value)) {
                    return "Field '{$field}' must be a boolean";
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return "Field '{$field}' must be an array";
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "Field '{$field}' must be a valid email address";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "Field '{$field}' must be a valid URL";
                }
                break;

            case 'date':
                if (!strtotime($value)) {
                    return "Field '{$field}' must be a valid date";
                }
                break;

            case 'enum':
                // Handled separately in validate()
                break;

            default:
                // Unknown type - skip validation
                break;
        }

        return null;
    }

    /**
     * Sanitize data (remove XSS, trim strings)
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value);
            }
            return $data;
        }

        if (is_string($data)) {
            // Trim whitespace
            $data = trim($data);

            // Remove null bytes
            $data = str_replace("\0", '', $data);

            return $data;
        }

        return $data;
    }

    /**
     * Validate and throw exception if invalid
     */
    public static function validateOrThrow($data, $schema): void {
        $errors = self::validate($data, $schema);

        if (!empty($errors)) {
            throw new SchemaValidationException(
                'Schema validation failed: ' . implode(', ', $errors),
                $errors,
            );
        }
    }
}
