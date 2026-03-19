<?php
/**
 * Localized date formatting helpers
 *
 * Provides locale-aware date formatting using PHP's intl extension
 * with graceful fallback for systems without intl.
 */

/**
 * Format a date with locale-specific formatting
 *
 * Uses IntlDateFormatter for proper localization when available.
 * Falls back to format_date() if intl extension is not loaded.
 *
 * @param int|string|DateTime $time Unix timestamp, date string, or DateTime object
 * @param string $style Date style: 'short', 'medium', 'long', 'full'
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
