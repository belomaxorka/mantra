<?php
/**
 * String helpers
 */

/**
 * Sanitize string for output (XSS protection)
 */
function sanitize($value)
{
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Escape output (alias for sanitize)
 */
function e($value)
{
    return sanitize($value);
}

/**
 * Check if string contains substring
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool
 */
function str_contains($haystack, $needle)
{
    return strpos($haystack, $needle) !== false;
}

/**
 * Check if string starts with substring
 * @param string $haystack The string to check
 * @param string $needle The prefix to check for
 * @return bool
 */
function str_starts_with($haystack, $needle)
{
    return strpos($haystack, $needle) === 0;
}

/**
 * Parse comma-separated string into array
 * @param string|array $value Comma-separated string or array
 * @return array Trimmed, filtered array
 */
function parse_csv($value)
{
    if (is_array($value)) {
        return array_filter(array_map('trim', $value), 'strlen');
    }
    if (is_string($value)) {
        return array_filter(array_map('trim', explode(',', $value)), 'strlen');
    }
    return array();
}

/**
 * Generate slug from string
 */
function slugify($text)
{
    // Transliteration map for Cyrillic characters
    $cyrillic = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
        'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
        'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
        'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
        'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
        'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '',
        'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya'
    );

    // Transliterate Cyrillic
    $text = strtr($text, $cyrillic);

    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // Transliterate remaining characters
    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // Trim
    $text = trim($text, '-');

    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // Lowercase
    $text = strtolower($text);

    return empty($text) ? 'n-a' : $text;
}
