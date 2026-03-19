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

    // Just now (less than 10 seconds)
    if (abs($diff) < 10) {
        return t('datetime.just_now', 'just now');
    }

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
        $unit = pluralize($value, 'datetime.second');
    } elseif ($diff < 3600) {
        $value = floor($diff / 60);
        $unit = pluralize($value, 'datetime.minute');
    } elseif ($diff < 86400) {
        $value = floor($diff / 3600);
        $unit = pluralize($value, 'datetime.hour');
    } elseif ($diff < 2592000) {
        $value = floor($diff / 86400);
        $unit = pluralize($value, 'datetime.day');
    } elseif ($diff < 31536000) {
        $value = floor($diff / 2592000);
        $unit = pluralize($value, 'datetime.month');
    } else {
        $value = floor($diff / 31536000);
        $unit = pluralize($value, 'datetime.year');
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

/**
 * Parse a date string into DateTime object
 *
 * @param string $dateString Date string to parse
 * @param string $format PHP date format (default: 'Y-m-d H:i:s')
 * @param string|null $timezone Timezone for the parsed date (default: configured timezone)
 * @return DateTime|false DateTime object or false on error
 */
function parse_date($dateString, $format = 'Y-m-d H:i:s', $timezone = null)
{
    if ($timezone === null) {
        $timezone = config('locale.timezone', 'UTC');
    }

    try {
        $tz = new DateTimeZone($timezone);
        $dt = DateTime::createFromFormat($format, $dateString, $tz);

        if ($dt === false) {
            logger()->debug('Failed to parse date', array(
                'string' => $dateString,
                'format' => $format
            ));
            return false;
        }

        return $dt;
    } catch (Exception $e) {
        logger()->warning('Date parsing failed', array(
            'string' => $dateString,
            'format' => $format,
            'error' => $e->getMessage()
        ));
        return false;
    }
}

/**
 * Get timezone information
 *
 * @param string|null $timezone Timezone identifier (default: configured timezone)
 * @return array|false Array with timezone info or false on error
 */
function get_timezone_info($timezone = null)
{
    if ($timezone === null) {
        $timezone = config('locale.timezone', 'UTC');
    }

    try {
        $tz = new DateTimeZone($timezone);
        $now = new DateTime('now', $tz);

        $offset = $tz->getOffset($now);
        $hours = floor(abs($offset) / 3600);
        $minutes = floor((abs($offset) % 3600) / 60);
        $sign = $offset >= 0 ? '+' : '-';

        return array(
            'name' => $timezone,
            'offset' => $offset,
            'offset_seconds' => $offset,
            'offset_string' => sprintf('%s%02d:%02d', $sign, $hours, $minutes),
            'abbreviation' => $now->format('T'),
            'is_dst' => (bool)$now->format('I'),
        );
    } catch (Exception $e) {
        logger()->warning('Failed to get timezone info', array(
            'timezone' => $timezone,
            'error' => $e->getMessage()
        ));
        return false;
    }
}

/**
 * Check if a timezone identifier is valid
 *
 * @param string $timezone Timezone identifier to validate
 * @return bool True if valid, false otherwise
 */
function is_valid_timezone($timezone)
{
    try {
        new DateTimeZone($timezone);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get start of day for a given date
 *
 * @param DateTime|string|null $date Date (default: today)
 * @return DateTime DateTime object set to 00:00:00
 */
function start_of_day($date = null)
{
    if ($date === null) {
        $dt = now();
    } elseif ($date instanceof DateTime) {
        $dt = clone $date;
    } else {
        $dt = new DateTime($date, new DateTimeZone(config('locale.timezone', 'UTC')));
    }

    $dt->setTime(0, 0, 0);
    return $dt;
}

/**
 * Get end of day for a given date
 *
 * @param DateTime|string|null $date Date (default: today)
 * @return DateTime DateTime object set to 23:59:59
 */
function end_of_day($date = null)
{
    if ($date === null) {
        $dt = now();
    } elseif ($date instanceof DateTime) {
        $dt = clone $date;
    } else {
        $dt = new DateTime($date, new DateTimeZone(config('locale.timezone', 'UTC')));
    }

    $dt->setTime(23, 59, 59);
    return $dt;
}

/**
 * Check if a date is today
 *
 * @param DateTime|string $date Date to check
 * @return bool True if date is today
 */
function is_today($date)
{
    if ($date instanceof DateTime) {
        $dt = clone $date;
    } else {
        try {
            $dt = new DateTime($date);
        } catch (Exception $e) {
            return false;
        }
    }

    $today = now()->format('Y-m-d');
    return $dt->format('Y-m-d') === $today;
}

/**
 * Check if a date is yesterday
 *
 * @param DateTime|string $date Date to check
 * @return bool True if date is yesterday
 */
function is_yesterday($date)
{
    if ($date instanceof DateTime) {
        $dt = clone $date;
    } else {
        try {
            $dt = new DateTime($date);
        } catch (Exception $e) {
            return false;
        }
    }

    $yesterday = now()->modify('-1 day')->format('Y-m-d');
    return $dt->format('Y-m-d') === $yesterday;
}

/**
 * Get difference between two dates in days
 *
 * @param DateTime|string $date1 First date
 * @param DateTime|string|null $date2 Second date (default: now)
 * @return int|false Number of days or false on error
 */
function days_between($date1, $date2 = null)
{
    try {
        if (!($date1 instanceof DateTime)) {
            $date1 = new DateTime($date1);
        }

        if ($date2 === null) {
            $date2 = now();
        } elseif (!($date2 instanceof DateTime)) {
            $date2 = new DateTime($date2);
        }

        $interval = $date1->diff($date2);
        return (int)$interval->format('%r%a');
    } catch (Exception $e) {
        return false;
    }
}
