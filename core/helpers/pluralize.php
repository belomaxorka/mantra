<?php
/**
 * Pluralization helpers
 */

/**
 * Get plural form for a number based on language rules
 *
 * @param int $n Number
 * @param string $key Translation key base (e.g., 'datetime.hour')
 * @param string|null $locale Locale to use (defaults to current locale)
 * @return string Pluralized string
 */
function pluralize($n, $key, $locale = null)
{
    if ($locale === null) {
        $locale = config('locale.default_language', 'en');
    }

    $n = abs((int)$n);

    // Russian pluralization (3 forms)
    if ($locale === 'ru') {
        return pluralize_ru($n, $key);
    }

    // English pluralization (2 forms)
    if ($n === 1) {
        return t($key . '.one', t($key, ''));
    }

    return t($key . '.other', t($key . 's', ''));
}

/**
 * Russian pluralization rules
 *
 * Russian has 3 plural forms:
 * - one: 1, 21, 31, 41, 51, 61, 71, 81, 91, 101, 121, etc.
 * - few: 2-4, 22-24, 32-34, 42-44, 52-54, 62, 102-104, etc.
 * - many: 0, 5-20, 25-30, 35-40, etc.
 *
 * @param int $n Number
 * @param string $key Translation key base
 * @return string
 */
function pluralize_ru($n, $key)
{
    $n = abs((int)$n);
    $mod10 = $n % 10;
    $mod100 = $n % 100;

    // 11-14 are special cases (many form)
    if ($mod100 >= 11 && $mod100 <= 14) {
        return t($key . '.many', t($key, ''));
    }

    // 1, 21, 31, etc. (one form)
    if ($mod10 === 1) {
        return t($key . '.one', t($key, ''));
    }

    // 2-4, 22-24, 32-34, etc. (few form)
    if ($mod10 >= 2 && $mod10 <= 4) {
        return t($key . '.few', t($key, ''));
    }

    // 0, 5-20, 25-30, etc. (many form)
    return t($key . '.many', t($key, ''));
}
