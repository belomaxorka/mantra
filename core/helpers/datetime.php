<?php
/**
 * Date and time helpers
 *
 * All functions respect the timezone configured in admin settings (locale.timezone).
 * Database timestamps are stored in UTC and converted to configured timezone for display.
 */

/**
 * Get current DateTime object in configured timezone
 *
 * @return DateTime
 */
function now()
{
    $tz = config('locale.timezone', 'UTC');
    try {
        $timezone = new DateTimeZone($tz);
    } catch (Exception $e) {
        logger()->warning('Invalid timezone in config, falling back to UTC', array('timezone' => $tz));
        $timezone = new DateTimeZone('UTC');
    }

    return new DateTime('now', $timezone);
}

/**
 * Get current UTC DateTime object
 *
 * @return DateTime
 */
function now_utc()
{
    return new DateTime('now', new DateTimeZone('UTC'));
}

/**
 * Format a timestamp or DateTime according to configured timezone
 *
 * @param int|string|DateTime $time Unix timestamp, date string (assumed UTC), or DateTime object
 * @param string $format PHP date format (default: 'Y-m-d H:i:s')
 * @return string Formatted date string
 */
function format_date($time = null, $format = 'Y-m-d H:i:s')
{
    if ($time === null) {
        $dt = now();
    } elseif ($time instanceof DateTime) {
        $dt = clone $time;
        $tz = config('locale.timezone', 'UTC');
        try {
            $dt->setTimezone(new DateTimeZone($tz));
        } catch (Exception $e) {
            logger()->warning('Invalid timezone in config', array('timezone' => $tz));
        }
    } elseif (is_numeric($time)) {
        $dt = now();
        $dt->setTimestamp((int)$time);
    } else {
        // String input - assume UTC and convert to configured timezone
        try {
            $utc = new DateTimeZone('UTC');
            $dt = new DateTime($time, $utc);

            $tz = config('locale.timezone', 'UTC');
            $timezone = new DateTimeZone($tz);
            $dt->setTimezone($timezone);
        } catch (Exception $e) {
            logger()->warning('Invalid date string', array('time' => $time, 'error' => $e->getMessage()));
            return '';
        }
    }

    return $dt->format($format);
}

/**
 * Get relative time string (e.g., "2 hours ago", "in 3 days")
 *
 * @param int|string|DateTime $time Unix timestamp, date string, or DateTime object
 * @return string Relative time string
 */
function time_ago($time)
{
    if ($time instanceof DateTime) {
        $dt = clone $time;
    } elseif (is_numeric($time)) {
        $dt = new DateTime();
        $dt->setTimestamp((int)$time);
    } else {
        try {
            $dt = new DateTime($time);
        } catch (Exception $e) {
            return '';
        }
    }

    $now = now();
    $diff = $now->getTimestamp() - $dt->getTimestamp();

    if ($diff < 0) {
        // Future time
        $diff = abs($diff);
        $suffix = t('datetime.in_future', 'in %s');
    } else {
        $suffix = t('datetime.ago', '%s ago');
    }

    // Calculate time difference
    if ($diff < 60) {
        $value = $diff;
        $unit = $value === 1 ? t('datetime.second', 'second') : t('datetime.seconds', 'seconds');
    } elseif ($diff < 3600) {
        $value = floor($diff / 60);
        $unit = $value === 1 ? t('datetime.minute', 'minute') : t('datetime.minutes', 'minutes');
    } elseif ($diff < 86400) {
        $value = floor($diff / 3600);
        $unit = $value === 1 ? t('datetime.hour', 'hour') : t('datetime.hours', 'hours');
    } elseif ($diff < 2592000) {
        $value = floor($diff / 86400);
        $unit = $value === 1 ? t('datetime.day', 'day') : t('datetime.days', 'days');
    } elseif ($diff < 31536000) {
        $value = floor($diff / 2592000);
        $unit = $value === 1 ? t('datetime.month', 'month') : t('datetime.months', 'months');
    } else {
        $value = floor($diff / 31536000);
        $unit = $value === 1 ? t('datetime.year', 'year') : t('datetime.years', 'years');
    }

    return sprintf($suffix, $value . ' ' . $unit);
}

/**
 * Get list of common timezones grouped by region
 *
 * @return array Associative array of timezone identifiers => display names
 */
function get_timezones()
{
    static $timezones = null;

    if ($timezones !== null) {
        return $timezones;
    }

    $timezones = array();
    $regions = array(
        DateTimeZone::AFRICA,
        DateTimeZone::AMERICA,
        DateTimeZone::ANTARCTICA,
        DateTimeZone::ASIA,
        DateTimeZone::ATLANTIC,
        DateTimeZone::AUSTRALIA,
        DateTimeZone::EUROPE,
        DateTimeZone::INDIAN,
        DateTimeZone::PACIFIC,
        DateTimeZone::UTC,
    );

    foreach ($regions as $region) {
        $list = DateTimeZone::listIdentifiers($region);
        foreach ($list as $tz) {
            // Format timezone name for display
            $display = str_replace('_', ' ', $tz);
            $timezones[$tz] = $display;
        }
    }

    return $timezones;
}

/**
 * Convert a date string from one timezone to another
 *
 * @param string $dateString Date string to convert
 * @param string $fromTz Source timezone (default: UTC)
 * @param string $toTz Target timezone (default: configured timezone)
 * @return DateTime|false DateTime object in target timezone, or false on error
 */
function convert_timezone($dateString, $fromTz = 'UTC', $toTz = null)
{
    if ($toTz === null) {
        $toTz = config('locale.timezone', 'UTC');
    }

    try {
        $fromTimezone = new DateTimeZone($fromTz);
        $toTimezone = new DateTimeZone($toTz);

        $dt = new DateTime($dateString, $fromTimezone);
        $dt->setTimezone($toTimezone);

        return $dt;
    } catch (Exception $e) {
        logger()->warning('Timezone conversion failed', array(
            'date' => $dateString,
            'from' => $fromTz,
            'to' => $toTz,
            'error' => $e->getMessage()
        ));
        return false;
    }
}
