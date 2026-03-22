# Анализ View.php

## Критические проблемы

### 1. Несогласованность логики layout wrapping (строки 169-175)

**Проблема:** Условие `!str_contains($template, ':')` проверяет исходное имя шаблона, но не учитывает, что шаблон мог быть разрешён в модульный через параметр `_module`.

**Пример бага:**
```php
// Вызов
$view->render('page', ['_module' => 'admin', 'title' => 'Admin']);

// Что происходит:
// 1. Шаблон 'page' не содержит ':'
// 2. Разрешается в modules/admin/views/page.php (через _module)
// 3. НО условие !str_contains('page', ':') = true
// 4. Шаблон модуля ОШИБОЧНО оборачивается в layout темы
```

**Исправление:**
```php
// Отслеживать, был ли использован модульный шаблон
$isModuleTemplate = false;

if (!file_exists($templatePath)) {
    if (str_contains($template, ':')) {
        list($module, $tpl) = explode(':', $template, 2);
        $templatePath = MANTRA_MODULES . '/' . $module . '/views/' . $tpl . '.php';
        $isModuleTemplate = true;
    } else {
        if (isset($data['_module']) && !empty($data['_module'])) {
            $modulePath = MANTRA_MODULES . '/' . $data['_module'] . '/views/' . $template . '.php';
            if (file_exists($modulePath)) {
                $templatePath = $modulePath;
                $isModuleTemplate = true;
            }
        }
    }
}

// ...

// Wrap in layout if not a module template
if (!$isModuleTemplate) {
    $layoutPath = $this->themePath . '/templates/layout.php';
    if (file_exists($layoutPath)) {
        $content = $this->renderLayout($layoutPath, $this->data, $content);
    }
}
```

### 2. Перезапись $content через extract() (строки 50-55)

**Проблема:** `extract($data)` может перезаписать параметр `$content`, если в `$data` есть ключ `'content'`.

**Пример бага:**
```php
$view->render('page', [
    'title' => 'Test',
    'content' => 'User provided content'  // Перезапишет рендеренный контент!
]);
```

**Исправление:**
```php
private function renderLayout($layoutPath, $data, $content) {
    return $this->captureOutput(function() use ($layoutPath, $data, $content) {
        extract($data);
        $content = $content;  // Явно восстанавливаем после extract
        include $layoutPath;
    });
}
```

Или лучше:
```php
private function renderLayout($layoutPath, $data, $content) {
    // Добавляем $content в данные, чтобы избежать конфликтов
    $layoutData = array_merge($data, ['content' => $content]);

    return $this->captureOutput(function() use ($layoutPath, $layoutData) {
        extract($layoutData);
        include $layoutPath;
    });
}
```

## Некритические проблемы

### 3. Двойной слеш в asset() URL

**Проблема:**
```php
$baseUrl = 'http://example.com/';  // Заканчивается на /
// Результат: http://example.com//themes/default/assets/style.css
```

**Исправление:**
```php
public function asset($path) {
    $baseUrl = rtrim(config('site.url', ''), '/');
    return $baseUrl . '/' . basename(MANTRA_THEMES) . '/' . basename($this->themePath) . '/assets/' . ltrim($path, '/');
}
```

### 4. Порядок вызова хука view.render

**Вопрос:** Хук вызывается до оборачивания в layout. Это задумано?

Если хуки должны модифицировать финальный HTML:
```php
// Render content
$content = $this->renderTemplate($templatePath, $this->data);

// Wrap in layout if not a module template
if (!$isModuleTemplate) {
    $layoutPath = $this->themePath . '/templates/layout.php';
    if (file_exists($layoutPath)) {
        $content = $this->renderLayout($layoutPath, $this->data, $content);
    }
}

// Apply filters to final output
$app = Application::getInstance();
$content = $app->hooks()->fire('view.render', $content);

return $content;
```

## Положительные моменты

✅ **Отличная работа с output buffering:**
- Правильное сохранение уровня буферизации
- Корректная очистка при исключениях
- Использование цикла для вложенных буферов

✅ **Устойчивость к ошибкам виджетов:**
- Ошибки виджетов не ломают страницу
- Логирование ошибок
- Возврат HTML-комментария вместо краша

✅ **Безопасность:**
- Использование `basename()` для путей
- HTML-экранирование через `escape()`
- Валидация существования файлов

✅ **Гибкая система разрешения шаблонов:**
- Приоритет темы над модулями
- Поддержка явного синтаксиса "module:template"
- Умный fallback через _module

## Итоговая оценка

**Общее качество:** Хорошее (7/10)

**Критичность проблем:**
- Проблема #1 (layout wrapping): **Средняя** - может привести к неправильному отображению
- Проблема #2 (extract content): **Средняя** - может сломать layout при определённых данных
- Проблема #3 (double slash): **Низкая** - косметическая
- Проблема #4 (hook order): **Низкая** - возможно, задумано

**Рекомендации:**
1. Исправить логику определения модульных шаблонов
2. Защитить переменную $content от перезаписи
3. Нормализовать URL в asset()
4. Уточнить требования к порядку вызова хуков
