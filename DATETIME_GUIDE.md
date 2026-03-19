# Руководство по работе с датами и временем в Mantra CMS

## Обзор

Система работы с датами и временем в Mantra CMS предоставляет полный набор функций для:
- Хранения временных меток в UTC
- Отображения дат в настроенном часовом поясе
- Локализации форматов дат для разных языков
- Правильных склонений в относительном времени

## Основные функции

### 1. Получение текущего времени

```php
// Текущее время в настроенном часовом поясе
$now = now();
echo $now->format('Y-m-d H:i:s');  // 2026-03-19 15:30:00

// Текущее время в UTC (для сохранения в базу)
$utcNow = now_utc();
echo $utcNow->format('Y-m-d H:i:s');  // 2026-03-19 12:30:00 (если timezone +03:00)
```

### 2. Форматирование дат

```php
// Базовое форматирование (с учетом часового пояса)
echo format_date($post['created_at'], 'Y-m-d H:i');  // 2026-03-19 15:30
echo format_date($post['created_at'], 'F j, Y');     // March 19, 2026

// Локализованное форматирование (требует расширение intl)
echo format_date_localized($post['created_at'], 'medium');  // 19 марта 2026 г. (RU)
echo format_date_localized($post['created_at'], 'short');   // 19.03.2026 (RU)
echo format_date_localized($post['created_at'], 'long');    // 19 марта 2026 г. (RU)

// С временем
echo format_date_localized($post['created_at'], 'medium', true);  // 19 марта 2026 г., 15:30

// Кастомный паттерн (ICU формат)
echo format_date_pattern($date, 'dd MMMM yyyy');  // 19 марта 2026
```

### 3. Относительное время

```php
// Простой формат
echo time_ago($post['created_at']);
// "2 часа назад" (RU) / "2 hours ago" (EN)
// "21 час назад" (RU) / "21 hours ago" (EN)
// "5 дней назад" (RU) / "5 days ago" (EN)

// Детальный формат (для недавних дат)
echo time_ago($post['created_at'], true);
// "сегодня в 14:30" (RU) / "today at 2:30 PM" (EN)
// "вчера в 18:00" (RU) / "yesterday at 6:00 PM" (EN)
// "только что" (RU) / "just now" (EN)
```

### 4. Парсинг дат

```php
// Парсинг даты из строки
$date = parse_date('2026-03-19 15:30:00', 'Y-m-d H:i:s');
if ($date) {
    echo $date->format('d.m.Y');  // 19.03.2026
}

// Парсинг с кастомным форматом
$date = parse_date('19.03.2026', 'd.m.Y');
```

### 5. Конвертация часовых поясов

```php
// Конвертация из UTC в настроенный часовой пояс
$dt = convert_timezone('2026-03-19 12:00:00', 'UTC', 'Europe/Moscow');
echo $dt->format('H:i');  // 15:00

// Конвертация между любыми поясами
$dt = convert_timezone('2026-03-19 15:00:00', 'Europe/Moscow', 'America/New_York');
echo $dt->format('H:i');  // 07:00
```

### 6. Информация о часовом поясе

```php
$info = get_timezone_info('Europe/Moscow');
print_r($info);
/*
Array (
    [name] => Europe/Moscow
    [offset] => 10800
    [offset_seconds] => 10800
    [offset_string] => +03:00
    [abbreviation] => MSK
    [is_dst] => false
)
*/

// Проверка валидности часового пояса
if (is_valid_timezone('Europe/Moscow')) {
    echo 'Валидный часовой пояс';
}
```

### 7. Работа с границами дня

```php
// Начало дня (00:00:00)
$start = start_of_day();  // сегодня 00:00:00
$start = start_of_day('2026-03-19');  // 2026-03-19 00:00:00

// Конец дня (23:59:59)
$end = end_of_day();  // сегодня 23:59:59
$end = end_of_day('2026-03-19');  // 2026-03-19 23:59:59

// Использование для выборки за день
$posts = db()->query('posts', function($post) use ($start, $end) {
    $created = new DateTime($post['created_at']);
    return $created >= $start && $created <= $end;
});
```

### 8. Проверка дат

```php
// Проверка, является ли дата сегодняшней
if (is_today($post['created_at'])) {
    echo 'Опубликовано сегодня';
}

// Проверка, является ли дата вчерашней
if (is_yesterday($post['created_at'])) {
    echo 'Опубликовано вчера';
}

// Разница между датами в днях
$days = days_between($post['created_at'], now());
echo "Опубликовано {$days} дней назад";
```

### 9. Локализованные названия

```php
// Названия месяцев
echo get_month_name(3, 'long');   // "март" (RU) / "March" (EN)
echo get_month_name(3, 'short');  // "мар." (RU) / "Mar" (EN)

// Названия дней недели
echo get_day_name(1, 'long');   // "понедельник" (RU) / "Monday" (EN)
echo get_day_name(1, 'short');  // "пн" (RU) / "Mon" (EN)
```

## Примеры использования

### В шаблонах тем

```php
<!-- Простое отображение даты -->
<time datetime="<?php echo $post['created_at']; ?>">
    <?php echo format_date($post['created_at'], 'F j, Y'); ?>
</time>

<!-- Локализованная дата -->
<time datetime="<?php echo $post['created_at']; ?>">
    <?php echo format_date_localized($post['created_at'], 'long'); ?>
</time>

<!-- Относительное время -->
<span class="text-muted">
    <?php echo time_ago($post['created_at']); ?>
</span>

<!-- Детальное относительное время -->
<span class="text-muted">
    <?php echo time_ago($post['created_at'], true); ?>
</span>
```

### В модулях

```php
class MyModule extends Module
{
    public function init()
    {
        // Создание записи с UTC временем
        $data = array(
            'title' => 'Test',
            'created_at' => now_utc()->format('Y-m-d H:i:s'),
            'updated_at' => now_utc()->format('Y-m-d H:i:s'),
        );

        db()->write('my_collection', 'id', $data);

        // Отображение с учетом часового пояса
        $item = db()->read('my_collection', 'id');
        echo format_date($item['created_at'], 'Y-m-d H:i:s');
    }
}
```

### В админке

```php
// Список с датами обновления
foreach ($items as $item) {
    echo '<td>';
    echo '<small class="text-muted">';
    echo format_date($item['updated_at'], 'Y-m-d H:i');
    echo '</small>';
    echo '</td>';
}

// Детальная информация
echo '<p>Создано: ' . format_date_localized($item['created_at'], 'long', true) . '</p>';
echo '<p>Обновлено: ' . time_ago($item['updated_at'], true) . '</p>';
```

## Правильные склонения

Система автоматически использует правильные склонения для русского языка:

```php
echo time_ago('-1 hour');   // "1 час назад"
echo time_ago('-2 hours');  // "2 часа назад"
echo time_ago('-5 hours');  // "5 часов назад"
echo time_ago('-21 hours'); // "21 час назад"
echo time_ago('-22 hours'); // "22 часа назад"
echo time_ago('-25 hours'); // "25 часов назад"
```

Для английского языка:
```php
echo time_ago('-1 hour');  // "1 hour ago"
echo time_ago('-2 hours'); // "2 hours ago"
echo time_ago('-5 hours'); // "5 hours ago"
```

## Настройка часового пояса

### Через админку

1. Перейдите в **Настройки → Локализация**
2. Выберите нужный часовой пояс из выпадающего списка
3. Сохраните настройки

Все даты на сайте будут автоматически отображаться в выбранном часовом поясе.

### Программно

```php
// Изменение часового пояса
config()->set('locale.timezone', 'Europe/Moscow');

// Получение текущего часового пояса
$timezone = config('locale.timezone', 'UTC');
```

## Хранение дат в базе данных

**Важно:** Все даты должны храниться в UTC!

```php
// ✅ Правильно - сохранение в UTC
$data['created_at'] = now_utc()->format('Y-m-d H:i:s');

// ❌ Неправильно - сохранение в локальном времени
$data['created_at'] = now()->format('Y-m-d H:i:s');

// ✅ Правильно - отображение в локальном времени
echo format_date($data['created_at'], 'Y-m-d H:i:s');
```

Класс `Database` автоматически устанавливает `created_at` и `updated_at` в UTC при использовании метода `write()`.

## Требования

### Базовые функции
- PHP 5.5+
- Все основные функции работают без дополнительных расширений

### Локализованное форматирование
- PHP расширение `intl` (опционально)
- Если `intl` не установлено, используется fallback на стандартное форматирование

### Проверка наличия intl

```php
if (extension_loaded('intl')) {
    echo 'Локализованное форматирование доступно';
} else {
    echo 'Используется стандартное форматирование';
}
```

## Производительность

### Кеширование

Функция `get_timezones()` использует статическое кеширование:

```php
// Первый вызов - генерирует список (~400 часовых поясов)
$timezones = get_timezones();

// Последующие вызовы - возвращают закешированный результат
$timezones = get_timezones();
```

### Рекомендации

1. Используйте `format_date()` для простого форматирования
2. Используйте `format_date_localized()` только когда нужна полная локализация
3. Кешируйте результаты `get_timezone_info()` если используете часто
4. Избегайте вызова `time_ago()` в циклах с большим количеством итераций

## Отладка

### Логирование

Все функции логируют ошибки через `logger()`:

```php
// Проверка логов
tail -f storage/logs/app-2026-03-19.log

// Примеры логов
[2026-03-19 12:00:00] WARNING: Invalid timezone in config {"timezone":"Invalid/Timezone"}
[2026-03-19 12:00:01] WARNING: Date parsing failed {"string":"invalid","format":"Y-m-d"}
```

### Проверка конфигурации

```php
// Текущий часовой пояс
echo config('locale.timezone', 'UTC');

// Текущий язык
echo config('locale.default_language', 'en');

// Информация о часовом поясе
print_r(get_timezone_info());
```

## Миграция существующего кода

### Замена date() на format_date()

```php
// Старый код
echo date('Y-m-d H:i:s', strtotime($post['created_at']));

// Новый код
echo format_date($post['created_at'], 'Y-m-d H:i:s');
```

### Замена time() на now()

```php
// Старый код
$timestamp = time();

// Новый код
$timestamp = now()->getTimestamp();
```

### Сохранение в UTC

```php
// Старый код
$data['created_at'] = date('Y-m-d H:i:s');

// Новый код
$data['created_at'] = now_utc()->format('Y-m-d H:i:s');
```

## Поддержка языков

Текущая поддержка:
- ✅ Английский (en) - 2 формы множественного числа
- ✅ Русский (ru) - 3 формы множественного числа
- ⏳ Другие языки - требуют добавления переводов

### Добавление нового языка

1. Создайте файл переводов `modules/admin-settings/lang/{locale}.php`
2. Добавьте переводы для datetime ключей:

```php
return array(
    'datetime.ago' => '%s ago',
    'datetime.in_future' => 'in %s',
    'datetime.just_now' => 'just now',

    // Формы множественного числа
    'datetime.hour.one' => 'hour',
    'datetime.hour.other' => 'hours',
    // ... остальные единицы времени
);
```

3. Для языков с 3+ формами множественного числа добавьте функцию в `core/helpers/pluralize.php`

## Дополнительные ресурсы

- [PHP DateTime](https://www.php.net/manual/en/class.datetime.php)
- [PHP DateTimeZone](https://www.php.net/manual/en/class.datetimezone.php)
- [PHP IntlDateFormatter](https://www.php.net/manual/en/class.intldateformatter.php)
- [ICU Date Format Patterns](https://unicode-org.github.io/icu/userguide/format_parse/datetime/)
- [Список часовых поясов](https://www.php.net/manual/en/timezones.php)
