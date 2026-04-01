<?php
/**
 * Clock - Centralized time management
 *
 * Single point of access for all time-related operations:
 * timestamps, formatting, parsing, and relative time.
 *
 * Registered as a lazy service: app()->service('clock'), helper: clock().
 */
class Clock {
    /** Storage format for timestamps in JSON documents. */
    const STORAGE_FORMAT = 'Y-m-d H:i:s';

    private $timezone;
    private $dateFormat;
    private $timeFormat;

    /**
     * @param string $timezone   IANA timezone identifier (e.g. 'UTC', 'Europe/Moscow')
     * @param string $dateFormat PHP date format for display (e.g. 'j F Y')
     * @param string $timeFormat PHP date format for time display (e.g. 'H:i')
     */
    public function __construct($timezone = 'UTC', $dateFormat = 'j F Y', $timeFormat = 'H:i') {
        $this->timezone = new \DateTimeZone($timezone);
        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
    }

    /**
     * Get current time as DateTimeImmutable in the configured timezone.
     *
     * @return \DateTimeImmutable
     */
    public function now() {
        return new \DateTimeImmutable('now', $this->timezone);
    }

    /**
     * Get current time as a storage-ready string (Y-m-d H:i:s).
     *
     * @return string
     */
    public function timestamp() {
        return $this->now()->format(self::STORAGE_FORMAT);
    }

    /**
     * Parse a datetime value into DateTimeImmutable.
     *
     * Accepts:
     *  - string in self::STORAGE_FORMAT ('Y-m-d H:i:s') or any strtotime-compatible string
     *  - DateTimeInterface instance
     *  - int (Unix timestamp)
     *
     * @param string|\DateTimeInterface|int $datetime
     * @return \DateTimeImmutable
     */
    public function parse($datetime) {
        if ($datetime instanceof \DateTimeImmutable) {
            return $datetime->setTimezone($this->timezone);
        }
        if ($datetime instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromMutable($datetime)->setTimezone($this->timezone);
        }
        if (is_int($datetime)) {
            return (new \DateTimeImmutable('@' . $datetime))->setTimezone($this->timezone);
        }

        $str = (string)$datetime;

        // Try exact storage format first
        $dt = \DateTimeImmutable::createFromFormat(self::STORAGE_FORMAT, $str, $this->timezone);
        if ($dt !== false) {
            return $dt;
        }

        // Fall back to free-form parsing
        return new \DateTimeImmutable($str, $this->timezone);
    }

    // ── Display formatting ──────────────────────────────────────────────

    /**
     * Format for display using the configured date format.
     *
     * @param string|\DateTimeInterface|int $datetime
     * @return string
     */
    public function formatDate($datetime) {
        return $this->format($datetime, $this->dateFormat);
    }

    /**
     * Format for display using the configured time format.
     *
     * @param string|\DateTimeInterface|int $datetime
     * @return string
     */
    public function formatTime($datetime) {
        return $this->format($datetime, $this->timeFormat);
    }

    /**
     * Format for display using date + time formats (comma-separated).
     *
     * @param string|\DateTimeInterface|int $datetime
     * @return string
     */
    public function formatDatetime($datetime) {
        return $this->format($datetime, $this->dateFormat . ', ' . $this->timeFormat);
    }

    /**
     * Format with a custom PHP date format string.
     *
     * @param string|\DateTimeInterface|int $datetime
     * @param string $format
     * @return string
     */
    public function format($datetime, $format) {
        return $this->parse($datetime)->format($format);
    }

    // ── Relative time ───────────────────────────────────────────────────

    /**
     * Human-readable relative time ("5 minutes ago", "2 hours ago").
     *
     * Uses translation keys from the 'core' domain for locale-aware output.
     *
     * @param string|\DateTimeInterface|int $datetime
     * @return string
     */
    public function ago($datetime) {
        $dt = $this->parse($datetime);
        $diff = $this->now()->getTimestamp() - $dt->getTimestamp();

        if ($diff < 0) {
            $diff = 0;
        }

        if ($diff < 60) {
            return t('core.time.just_now');
        }

        if ($diff < 3600) {
            $count = (int)floor($diff / 60);
            return t('core.time.minutes_ago', array('count' => $count));
        }

        if ($diff < 86400) {
            $count = (int)floor($diff / 3600);
            return t('core.time.hours_ago', array('count' => $count));
        }

        if ($diff < 604800) {
            $count = (int)floor($diff / 86400);
            return t('core.time.days_ago', array('count' => $count));
        }

        if ($diff < 2592000) {
            $count = (int)floor($diff / 604800);
            return t('core.time.weeks_ago', array('count' => $count));
        }

        if ($diff < 31536000) {
            $count = (int)floor($diff / 2592000);
            return t('core.time.months_ago', array('count' => $count));
        }

        $count = (int)floor($diff / 31536000);
        return t('core.time.years_ago', array('count' => $count));
    }

    // ── Accessors ───────────────────────────────────────────────────────

    /**
     * Get the configured timezone.
     *
     * @return \DateTimeZone
     */
    public function timezone() {
        return $this->timezone;
    }

    /**
     * Get the configured date format string.
     *
     * @return string
     */
    public function dateFormat() {
        return $this->dateFormat;
    }

    /**
     * Get the configured time format string.
     *
     * @return string
     */
    public function timeFormat() {
        return $this->timeFormat;
    }
}
