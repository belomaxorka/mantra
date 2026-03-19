<?php
/**
 * Localized date formatting helpers
 *
 * These functions provide locale-aware date formatting using PHP's intl extension
 * when available, with graceful fallback to standard formatting.
 */

/**
 * Format a date with locale-specific formatting
 *
 * Uses IntlDateFormatter for proper localization when available.
 * Falls back to format_date() if intl extension is not loaded.
 *
 * @param int|string|DateTime $time Unix timestamp, date string, or DateTime object
 * @param string $style Date style: 'short', 'medium', 'long', 'full', or custom format
 * @param bool $includeTime Whether to include time in output
 * @param string|null $locale Locale to use (default: configured locale)
 * @return string Formatted date string
 */
function format_date_localized($time = null, $style = 'medium', $includeTime = false, $locale = null)
{
    // Check if intl extension is available
    if (!extension_loaded('intl')) {
        // Fallback to standard format_date
        $formats = array(
            'short' => 'Y-m-d',
            'medium' => 'M j, Y',
            'long' => 'F j, Y',
            'full' => 'l, F j, Y',
        );

        $format = isset($formats[$style]) ? $formats[$style] : $style;
        if ($includeTime) {
            $format .= ' H:i';
        }

        return format_date($time, $format);
    }

    // Get locale
    if ($locale === null) {
        $locale = config('locale.default_language', 'en');
    }

    // Convert locale code to full locale (e.g., 'en' -> 'en_US', 'ru' -> 'ru_RU')
    $localeMap = array(
        'en' => 'en_US',
        'ru' => 'ru_RU',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
        'it' => 'it_IT',
        'pt' => 'pt_BR',
        'ja' => 'ja_JP',
        'zh' => 'zh_CN',
        'ko' => 'ko_KR',
        'ar' => 'ar_SA',
        'pl' => 'pl_PL',
        'nl' => 'nl_NL',
        'tr' => 'tr_TR',
        'uk' => 'uk_UA',
    );

    $fullLocale = isset($localeMap[$locale]) ? $localeMap[$locale] : $locale . '_' . strtoupper($locale);

    // Get timezone
    $timezone = config('locale.timezone', 'UTC');

    // Map style names to IntlDateFormatter constants
    $dateStyles = array(
        'short' => IntlDateFormatter::SHORT,
        'medium' => IntlDateFormatter::MEDIUM,
        'long' => IntlDateFormatter::LONG,
        'full' => IntlDateFormatter::FULL,
    );

    $dateStyle = isset($dateStyles[$style]) ? $dateStyles[$style] : IntlDateFormatter::MEDIUM;
    $timeStyle = $includeTime ? IntlDateFormatter::SHORT : IntlDateFormatter::NONE;

    // Convert input to timestamp
    if ($time === null) {
        $timestamp = now()->getTimestamp();
    } elseif ($time instanceof DateTime) {
        $timestamp = $time->getTimestamp();
    } elseif (is_numeric($time)) {
        $timestamp = (int)$time;
    } else {
        try {
            $dt = new DateTime($time, new DateTimeZone('UTC'));
            $timestamp = $dt->getTimestamp();
        } catch (Exception $e) {
            return '';
        }
    }

    try {
        $formatter = new IntlDateFormatter(
            $fullLocale,
            $dateStyle,
            $timeStyle,
            $timezone
        );

        $result = $formatter->format($timestamp);
        return $result !== false ? $result : '';
    } catch (Exception $e) {
        logger()->warning('IntlDateFormatter failed', array(
            'locale' => $fullLocale,
            'error' => $e->getMessage()
        ));

        // Fallback to standard formatting
        return format_date($time, 'Y-m-d H:i:s');
    }
}

/**
 * Format a date with a custom pattern using locale-specific formatting
 *
 * @param int|string|DateTime $time Unix timestamp, date string, or DateTime object
 * @param string $pattern ICU date format pattern (e.g., 'dd MMMM yyyy')
 * @param string|null $locale Locale to use (default: configured locale)
 * @return string Formatted date string
 */
function format_date_pattern($time, $pattern, $locale = null)
{
    if (!extension_loaded('intl')) {
        // Fallback: try to convert ICU pattern to PHP format (limited support)
        $phpPattern = str_replace(
            array('dd', 'MMMM', 'MMM', 'MM', 'yyyy', 'yy', 'HH', 'mm', 'ss'),
            array('d', 'F', 'M', 'm', 'Y', 'y', 'H', 'i', 's'),
            $pattern
        );
        return format_date($time, $phpPattern);
    }

    if ($locale === null) {
        $locale = config('locale.default_language', 'en');
    }

    $localeMap = array(
        'en' => 'en_US',
        'ru' => 'ru_RU',
        'de' => 'de_DE',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
    );

    $fullLocale = isset($localeMap[$locale]) ? $localeMap[$locale] : $locale . '_' . strtoupper($locale);
    $timezone = config('locale.timezone', 'UTC');

    if ($time === null) {
        $timestamp = now()->getTimestamp();
    } elseif ($time instanceof DateTime) {
        $timestamp = $time->getTimestamp();
    } elseif (is_numeric($time)) {
        $timestamp = (int)$time;
    } else {
        try {
            $dt = new DateTime($time, new DateTimeZone('UTC'));
            $timestamp = $dt->getTimestamp();
        } catch (Exception $e) {
            return '';
        }
    }

    try {
        $formatter = new IntlDateFormatter(
            $fullLocale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            $timezone
        );

        $formatter->setPattern($pattern);
        $result = $formatter->format($timestamp);
        return $result !== false ? $result : '';
    } catch (Exception $e) {
        logger()->warning('IntlDateFormatter pattern failed', array(
            'pattern' => $pattern,
            'error' => $e->getMessage()
        ));

        return format_date($time, 'Y-m-d H:i:s');
    }
}

/**
 * Get localized month name
 *
 * @param int $month Month number (1-12)
 * @param string $format 'long' or 'short'
 * @param string|null $locale Locale to use (default: configured locale)
 * @return string Month name
 */
function get_month_name($month, $format = 'long', $locale = null)
{
    if (!extension_loaded('intl')) {
        $months = array(
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        );
        return isset($months[$month]) ? $months[$month] : '';
    }

    if ($locale === null) {
        $locale = config('locale.default_language', 'en');
    }

    $localeMap = array(
        'en' => 'en_US',
        'ru' => 'ru_RU',
    );

    $fullLocale = isset($localeMap[$locale]) ? $localeMap[$locale] : $locale . '_' . strtoupper($locale);

    try {
        $formatter = new IntlDateFormatter(
            $fullLocale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC'
        );

        $pattern = $format === 'short' ? 'MMM' : 'MMMM';
        $formatter->setPattern($pattern);

        $date = new DateTime("2000-{$month}-01");
        $result = $formatter->format($date);

        return $result !== false ? $result : '';
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Get localized day name
 *
 * @param int $day Day of week (1=Monday, 7=Sunday)
 * @param string $format 'long' or 'short'
 * @param string|null $locale Locale to use (default: configured locale)
 * @return string Day name
 */
function get_day_name($day, $format = 'long', $locale = null)
{
    if (!extension_loaded('intl')) {
        $days = array(
            1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
            5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
        );
        return isset($days[$day]) ? $days[$day] : '';
    }

    if ($locale === null) {
        $locale = config('locale.default_language', 'en');
    }

    $localeMap = array(
        'en' => 'en_US',
        'ru' => 'ru_RU',
    );

    $fullLocale = isset($localeMap[$locale]) ? $localeMap[$locale] : $locale . '_' . strtoupper($locale);

    try {
        $formatter = new IntlDateFormatter(
            $fullLocale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            'UTC'
        );

        $pattern = $format === 'short' ? 'EEE' : 'EEEE';
        $formatter->setPattern($pattern);

        // 2024-01-01 was a Monday, so add days to get the right day
        $date = new DateTime('2024-01-01');
        $date->modify('+' . ($day - 1) . ' days');
        $result = $formatter->format($date);

        return $result !== false ? $result : '';
    } catch (Exception $e) {
        return '';
    }
}
