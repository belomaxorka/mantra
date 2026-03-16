# Testing Admin Panel Translations

## Quick Test Checklist

### 1. Check Language Configuration

Verify your language settings in `content/settings/config.json`:

```json
{
  "locale": {
    "default_language": "ru",
    "fallback_locale": "en"
  }
}
```

### 2. Clear Cache

If you have caching enabled, clear it:

```bash
rm -rf storage/cache/*
```

### 3. Test Pages to Check

Visit these admin pages and verify translations appear correctly:

#### Login Page (`/admin/login`)
- ✅ "Вход" (title)
- ✅ "Войдите в свой аккаунт" (subtitle)
- ✅ "Имя пользователя" (username label)
- ✅ "Пароль" (password label)
- ✅ "Войти" (button)

#### Dashboard (`/admin`)
- ✅ "Панель управления" (title)
- ✅ "Добро пожаловать" (welcome)
- ✅ "Быстрые действия" (quick actions)
- ✅ Sidebar groups: "Общее", "Контент", "Система"
- ✅ "Вы вошли как admin" (footer)
- ✅ "Выйти" (logout link)

#### Pages List (`/admin/pages`)
- ✅ "Страницы" (title)
- ✅ "Новая страница" (button)
- ✅ Table headers: "Заголовок", "URL", "Статус", "Навигация", "Обновлено", "Действия"
- ✅ Status badges: "Опубликовано", "Черновик"

#### Page Edit (`/admin/pages/edit/{id}`)
- ✅ "Редактировать" or "Новая страница" (title)
- ✅ "Вернуться к страницам" (back link)
- ✅ Form labels: "Заголовок", "URL", "Содержимое"
- ✅ "Публикация" (publish section)
- ✅ "Изображение" (featured image section)
- ✅ "Навигация" (navigation section)
- ✅ "Создать страницу" or "Обновить страницу" (button)

#### Posts List (`/admin/posts`)
- ✅ "Посты" (title)
- ✅ "Новый пост" (button)
- ✅ Table headers: "Заголовок", "Автор", "Категория", "Статус", "Обновлено", "Действия"

#### Post Edit (`/admin/posts/edit/{id}`)
- ✅ "Новый пост" or "Редактировать пост" (title)
- ✅ Form labels: "Заголовок", "URL", "Краткое описание", "Содержимое"
- ✅ "Публикация" (publish section)
- ✅ "Метаданные" (metadata section)
- ✅ "Создать пост" or "Обновить пост" (button)
- ✅ "Отмена" (cancel button)

#### Settings (`/admin/settings`)
- ✅ "Настройки" (title)
- ✅ "Сохранить настройки" (button)
- ✅ Module management: "Автор", "Домашняя страница", "Настройки", "Удалить"

### 4. Test Sidebar Navigation

Check that sidebar items show translated text:

**Russian (ru):**
- Общее
  - Панель управления
- Контент
  - Страницы
  - Посты
- Система
  - Настройки

**English (en):**
- General
  - Dashboard
- Content
  - Pages
  - Posts
- System
  - Settings

### 5. Test Universal Translations

These should work across all admin pages:

- ✅ "Сохранить" / "Save" (save buttons)
- ✅ "Отмена" / "Cancel" (cancel links)
- ✅ "Удалить" / "Delete" (delete buttons)
- ✅ "Редактировать" / "Edit" (edit links)
- ✅ "Просмотр" / "View" (view links)

### 6. Test Confirmation Dialogs

- ✅ Delete page: "Вы уверены, что хотите удалить эту страницу?"
- ✅ Delete post: "Вы уверены, что хотите удалить этот пост?"
- ✅ Delete module: "Вы уверены, что хотите удалить этот модуль?"

## Troubleshooting

### Translations Not Showing

1. **Check language files exist:**
   ```
   modules/admin/lang/ru.php
   modules/admin-dashboard/lang/ru.php
   modules/admin-pages/lang/ru.php
   modules/admin-posts/lang/ru.php
   modules/admin-settings/lang/ru.php
   ```

2. **Verify config.json:**
   - Check `locale.default_language` is set to "ru"
   - Check `locale.fallback_locale` is set to "en"

3. **Check PHP syntax:**
   ```bash
   php -l modules/admin/lang/ru.php
   php -l modules/admin-dashboard/lang/ru.php
   php -l modules/admin-pages/lang/ru.php
   php -l modules/admin-posts/lang/ru.php
   php -l modules/admin-settings/lang/ru.php
   ```

4. **Clear cache:**
   ```bash
   rm -rf storage/cache/*
   ```

5. **Check file permissions:**
   ```bash
   chmod 644 modules/*/lang/*.php
   ```

### Only Some Translations Work

If only layout translations work (like "Вы вошли как" and "Выйти"), but page-specific translations don't:

1. Check that the translation key matches exactly
2. Verify the module ID in the key (e.g., `admin.pages.title` not `pages.title`)
3. Check for typos in translation keys

### Fallback to English

If you see English text instead of Russian:

1. The key might be missing from `ru.php`
2. The key might have a typo
3. The fallback locale is being used (check `config.json`)

## Switching Languages

To switch between languages, edit `content/settings/config.json`:

**For Russian:**
```json
{
  "locale": {
    "default_language": "ru",
    "fallback_locale": "en"
  }
}
```

**For English:**
```json
{
  "locale": {
    "default_language": "en",
    "fallback_locale": "en"
  }
}
```

Then clear cache and refresh the page.

## Adding New Translations

To add a new translation key:

1. Add to English file: `modules/{module}/lang/en.php`
2. Add to Russian file: `modules/{module}/lang/ru.php`
3. Use in template: `<?php echo t('module.category.key'); ?>`
4. Clear cache and test

## Debugging

To see which translation key is being used, temporarily modify the `t()` function in `core/helpers.php` to log keys:

```php
function t($key, $params = array()) {
    error_log("Translation key: " . $key);
    return app()->language()->translate($key, $params);
}
```

Then check `storage/logs/php-{date}.log` for the logged keys.
