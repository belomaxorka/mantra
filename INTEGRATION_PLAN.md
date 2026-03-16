# Integration Plan - Унификация проекта

## 🎯 Цель
Интегрировать все улучшения унификации в проект с минимальным риском и максимальной эффективностью.

## 📋 Этапы интеграции

---

## Этап 1: Подготовка (30 минут)

### 1.1 Создание резервной копии
```bash
# Создать backup текущего состояния
git add .
git commit -m "Backup before unification integration"
git tag backup-before-unification
```

### 1.2 Проверка текущего состояния
- ✅ Все модули загружаются
- ✅ Нет критических ошибок
- ✅ Тесты проходят (если есть)

### 1.3 Создание тестового окружения
```bash
# Опционально: создать отдельную ветку
git checkout -b feature/unification
```

---

## Этап 2: Базовая инфраструктура (1 час)

### 2.1 Обновление хелперов ✅
**Статус**: Выполнено

**Файл**: `core/helpers.php`

**Добавлено**:
- `view()` - работа с View
- `admin()` - доступ к admin модулю
- `verify_csrf()` - CSRF проверка

**Тестирование**:
```php
// Проверить что хелперы работают
var_dump(function_exists('view'));      // true
var_dump(function_exists('admin'));     // true
var_dump(function_exists('verify_csrf')); // true
```

### 2.2 Расширение JsonFile ✅
**Статус**: Выполнено

**Файл**: `core/JsonFile.php`

**Добавлено**:
- `JsonFile::readSafe()` - безопасное чтение
- `JsonFile::writeSafe()` - безопасная запись

**Тестирование**:
```php
// Проверить безопасное чтение
$data = JsonFile::readSafe('/nonexistent/file.json', array('default' => true));
var_dump($data); // array('default' => true)

// Проверить безопасную запись
$success = JsonFile::writeSafe('/tmp/test.json', array('test' => 'data'));
var_dump($success); // true
```

### 2.3 Расширение ModuleValidator ✅
**Статус**: Выполнено

**Файл**: `core/ModuleValidator.php`

**Добавлено**:
- `ModuleValidator::isValidModuleId()` - проверка ID
- `ModuleValidator::assertValidModuleId()` - проверка с исключением

**Тестирование**:
```php
// Проверить валидацию
var_dump(ModuleValidator::isValidModuleId('admin')); // true
var_dump(ModuleValidator::isValidModuleId('Admin')); // false
var_dump(ModuleValidator::isValidModuleId('admin-pages')); // true

try {
    ModuleValidator::assertValidModuleId('Invalid Name');
} catch (InvalidArgumentException $e) {
    echo "Caught: " . $e->getMessage(); // OK
}
```

### 2.4 Создание базовых классов ✅
**Статус**: Выполнено

**Файлы**:
- `core/AdminModule.php` - базовый класс для admin модулей
- `core/ContentAdminModule.php` - базовый класс для CRUD

**Обновлено**:
- `core/bootstrap.php` - загрузка новых классов

**Тестирование**:
```php
// Проверить что классы загружены
var_dump(class_exists('AdminModule')); // true
var_dump(class_exists('ContentAdminModule')); // true
```

---

## Этап 3: Замена простых паттернов (2 часа)

### 3.1 Замена `new Database()` на `db()`

**Приоритет**: 🔴 Высокий (простая замена, большой эффект)

**Файлы для обновления** (15 мест):
1. ✅ `modules/products/ProductsModule.php` (3 места)
2. ✅ `modules/pages/PagesModule.php` (1 место)
3. ✅ `modules/admin-posts/AdminPostsModule.php` (3 места)
4. ✅ `modules/admin-pages/AdminPagesModule.php` (3 места)
5. ✅ `core/PageController.php` (4 места)

**Исключения** (не менять):
- `core/Auth.php` - конструктор
- `core/User.php` - конструктор
- `install.php` - установка

**Команда для поиска**:
```bash
grep -r "new Database()" --include="*.php" modules/ core/
```

**Пример замены**:
```php
// Было
$db = new Database();
$pages = $db->query('pages', ...);

// Стало
$pages = db()->query('pages', ...);
```

**Тестирование**:
- Проверить что все страницы загружаются
- Проверить CRUD операции в админке

### 3.2 Замена `new View()` на `view()`

**Приоритет**: 🔴 Высокий (простая замена, улучшает читаемость)

**Файлы для обновления** (20+ мест):
1. ✅ `modules/products/ProductsModule.php` (4 места)
2. ✅ `modules/admin-posts/AdminPostsModule.php` (3 места)
3. ✅ `modules/admin-pages/AdminPagesModule.php` (3 места)
4. ✅ `modules/admin-dashboard/AdminDashboardModule.php` (1 место)
5. ✅ `modules/admin-settings/AdminSettingsModule.php` (2 места)
6. ✅ `core/PageController.php` (4 места)

**Исключения** (не менять):
- `core/Module.php` - уже использует правильный паттерн
- `core/helpers.php` - определение функции

**Команда для поиска**:
```bash
grep -r "new View()" --include="*.php" modules/ core/
```

**Пример замены**:
```php
// Было
$view = new View();
$view->render('template', $data);

// Стало
view('template', $data);

// Или для fetch
$content = view()->fetch('template', $data);
```

**Тестирование**:
- Проверить рендеринг всех страниц
- Проверить admin интерфейс

### 3.3 Замена CSRF проверок на `verify_csrf()`

**Приоритет**: 🟡 Средний (улучшает безопасность и читаемость)

**Файлы для обновления** (10 мест):
1. ✅ `modules/admin-posts/AdminPostsModule.php` (3 места)
2. ✅ `modules/admin-pages/AdminPagesModule.php` (3 места)
3. ✅ `modules/admin-settings/AdminSettingsModule.php` (1 место)

**Команда для поиска**:
```bash
grep -r "verifyCsrfToken" --include="*.php" modules/
```

**Пример замены**:
```php
// Было
$token = (string)request()->post('csrf_token', '');
if (!auth()->verifyCsrfToken($token)) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    return;
}

// Стало
if (!verify_csrf()) {
    return;
}
```

**Тестирование**:
- Проверить создание/редактирование контента
- Проверить что CSRF защита работает

### 3.4 Замена валидации модулей на `ModuleValidator`

**Приоритет**: 🟡 Средний (унификация, улучшает поддержку)

**Файлы для обновления**:
1. ✅ `modules/admin-settings/AdminSettingsModule.php`
   - Удалить `isValidModuleName()`
   - Заменить вызовы на `ModuleValidator::isValidModuleId()`

2. ✅ `core/ModuleManager.php`
   - Обновить `assertValidModuleName()` для использования `ModuleValidator`

**Пример замены**:
```php
// Было
private function isValidModuleName($name) {
    return ($name !== '' && preg_match('/^[a-z0-9_-]+$/', $name));
}

if (!$this->isValidModuleName($moduleId)) {
    $error = 'Invalid module name';
}

// Стало
if (!ModuleValidator::isValidModuleId($moduleId)) {
    $error = 'Invalid module ID';
}
```

**Тестирование**:
- Проверить управление модулями в админке
- Проверить валидацию при включении/отключении модулей

---

## Этап 4: Рефакторинг admin модулей (3 часа)

### 4.1 Обновление AdminDashboardModule

**Приоритет**: 🟡 Средний

**Файл**: `modules/admin-dashboard/AdminDashboardModule.php`

**Изменения**:
```php
// Было
class AdminDashboardModule extends Module {
    public function init() {
        app()->hooks()->register('admin.sidebar', function ($items) {
            // ...
        });
        
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            // ...
        });
    }
}

// Стало
class AdminDashboardModule extends AdminModule {
    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'dashboard',
            'title' => t('admin.dashboard.title'),
            'url' => base_url('/admin'),
            'order' => 0,
        ));
        
        $this->registerAdminRoute('GET', '', array($this, 'dashboard'));
    }
    
    public function dashboard() {
        $quickActions = app()->hooks()->fire('admin.quick_actions', array());
        
        $content = $this->renderView('admin-dashboard:dashboard', array(
            'quickActions' => $quickActions
        ));
        
        return $this->renderAdmin('Dashboard', $content);
    }
}
```

**Экономия**: ~10 строк

**Тестирование**:
- Открыть `/admin`
- Проверить dashboard загружается
- Проверить quick actions работают

### 4.2 Обновление AdminSettingsModule

**Приоритет**: 🟡 Средний

**Файл**: `modules/admin-settings/AdminSettingsModule.php`

**Изменения**:
```php
// Было
class AdminSettingsModule extends Module {
    public function init() {
        app()->hooks()->register('admin.sidebar', function ($items) {
            // ...
        });
        
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            // ...
        });
    }
}

// Стало
class AdminSettingsModule extends AdminModule {
    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'settings',
            'title' => t('admin.settings.title'),
            'url' => base_url('/admin/settings'),
            'order' => 50,
        ));
        
        $this->registerQuickAction(array(
            'id' => 'settings',
            'title' => 'Settings',
            'url' => base_url('/admin/settings'),
            'order' => 10,
        ));
        
        $this->registerAdminRoute('GET', 'settings', array($this, 'settings'));
        $this->registerAdminRoute('POST', 'settings', array($this, 'settings'));
    }
    
    public function settings() {
        // Основная логика остаётся
        // Но используем $this->renderAdmin() вместо $admin->render()
    }
}
```

**Экономия**: ~15 строк

**Тестирование**:
- Открыть `/admin/settings`
- Проверить все вкладки работают
- Проверить сохранение настроек

### 4.3 Рефакторинг AdminPagesModule → ContentAdminModule

**Приоритет**: 🔴 Высокий (большая экономия кода)

**Файл**: `modules/admin-pages/AdminPagesModule.php`

**Изменения**:
```php
// Было (~200 строк)
class AdminPagesModule extends Module {
    public function init() { /* 30 строк */ }
    public function listPages() { /* 15 строк */ }
    public function newPage() { /* 20 строк */ }
    public function createPage() { /* 40 строк */ }
    public function editPage($params) { /* 25 строк */ }
    public function updatePage($params) { /* 45 строк */ }
    public function deletePage($params) { /* 15 строк */ }
    private function slugify($text) { /* 10 строк */ }
}

// Стало (~50 строк)
class AdminPagesModule extends ContentAdminModule {
    
    protected function getContentType() {
        return 'Page';
    }
    
    protected function getCollectionName() {
        return 'pages';
    }
    
    protected function getDefaultItem() {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'status' => 'draft',
            'image' => '',
            'show_in_navigation' => false,
            'navigation_order' => 50,
        );
    }
    
    protected function extractFormData() {
        return array(
            'title' => trim(request()->post('title', '')),
            'slug' => trim(request()->post('slug', '')),
            'content' => request()->post('content', ''),
            'status' => request()->post('status', 'draft'),
            'image' => trim(request()->post('image', '')),
            'show_in_navigation' => (bool)request()->post('show_in_navigation', false),
            'navigation_order' => (int)request()->post('navigation_order', 50),
        );
    }
    
    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'pages',
            'title' => t('admin.pages.title'),
            'url' => base_url('/admin/pages'),
            'order' => 10,
        ));
        
        $this->registerQuickAction(array(
            'id' => 'new-page',
            'title' => 'New Page',
            'url' => base_url('/admin/pages/new'),
            'order' => 20,
        ));
        
        $this->registerAdminRoute('GET', 'pages', array($this, 'listItems'));
        $this->registerAdminRoute('GET', 'pages/new', array($this, 'newItem'));
        $this->registerAdminRoute('POST', 'pages/new', array($this, 'createItem'));
        $this->registerAdminRoute('GET', 'pages/edit/{id}', array($this, 'editItem'));
        $this->registerAdminRoute('POST', 'pages/edit/{id}', array($this, 'updateItem'));
        $this->registerAdminRoute('POST', 'pages/delete/{id}', array($this, 'deleteItem'));
    }
}
```

**Экономия**: ~150 строк (75%)

**Тестирование**:
- Открыть `/admin/pages`
- Создать новую страницу
- Редактировать страницу
- Удалить страницу
- Проверить все поля сохраняются

### 4.4 Рефакторинг AdminPostsModule → ContentAdminModule

**Приоритет**: 🔴 Высокий (большая экономия кода)

**Файл**: `modules/admin-posts/AdminPostsModule.php`

**Изменения**: Аналогично AdminPagesModule

```php
class AdminPostsModule extends ContentAdminModule {
    
    protected function getContentType() {
        return 'Post';
    }
    
    protected function getCollectionName() {
        return 'posts';
    }
    
    protected function getDefaultItem() {
        return array(
            'title' => '',
            'slug' => '',
            'content' => '',
            'excerpt' => '',
            'status' => 'draft',
            'category' => '',
            'image' => '',
        );
    }
    
    protected function extractFormData() {
        return array(
            'title' => trim(request()->post('title', '')),
            'slug' => trim(request()->post('slug', '')),
            'content' => request()->post('content', ''),
            'excerpt' => request()->post('excerpt', ''),
            'status' => request()->post('status', 'draft'),
            'category' => request()->post('category', ''),
            'image' => trim(request()->post('image', '')),
        );
    }
    
    public function init() {
        $this->registerSidebarItem(array(
            'id' => 'posts',
            'title' => t('admin.posts.title'),
            'url' => base_url('/admin/posts'),
            'order' => 15,
        ));
        
        $this->registerQuickAction(array(
            'id' => 'new-post',
            'title' => 'New Post',
            'url' => base_url('/admin/posts/new'),
            'order' => 25,
        ));
        
        $this->registerAdminRoute('GET', 'posts', array($this, 'listItems'));
        $this->registerAdminRoute('GET', 'posts/new', array($this, 'newItem'));
        $this->registerAdminRoute('POST', 'posts/new', array($this, 'createItem'));
        $this->registerAdminRoute('GET', 'posts/edit/{id}', array($this, 'editItem'));
        $this->registerAdminRoute('POST', 'posts/edit/{id}', array($this, 'updateItem'));
        $this->registerAdminRoute('POST', 'posts/delete/{id}', array($this, 'deleteItem'));
    }
}
```

**Экономия**: ~150 строк (75%)

**Тестирование**:
- Открыть `/admin/posts`
- Создать новый пост
- Редактировать пост
- Удалить пост

---

## Этап 5: Обновление view шаблонов (1 час)

### 5.1 Обновление шаблонов admin-pages

**Файлы**:
- `modules/admin-pages/views/list.php`
- `modules/admin-pages/views/edit.php`

**Изменения**:
```php
// В list.php - обновить переменную
// Было: $pages
// Стало: $pages (ContentAdminModule передаёт как strtolower(getCollectionName()))

// В edit.php - обновить переменную
// Было: $page
// Стало: $page (ContentAdminModule передаёт как strtolower(getContentType()))
```

**Примечание**: ContentAdminModule использует те же имена переменных, поэтому изменения минимальны.

### 5.2 Обновление шаблонов admin-posts

**Файлы**:
- `modules/admin-posts/views/list.php`
- `modules/admin-posts/views/edit.php`

**Изменения**: Аналогично admin-pages

---

## Этап 6: Тестирование (2 часа)

### 6.1 Модульное тестирование

**Тест 1: Хелперы**
```php
// test-helpers.php
require_once 'core/bootstrap.php';

// Test view()
$result = view('test', array('data' => 'test'));
assert($result !== null, 'view() should work');

// Test admin()
$admin = admin();
assert($admin !== null, 'admin() should return module');

// Test verify_csrf()
$_SERVER['REQUEST_METHOD'] = 'GET';
assert(verify_csrf() === true, 'verify_csrf() should pass for GET');

echo "✓ All helper tests passed\n";
```

**Тест 2: JsonFile**
```php
// test-jsonfile.php
require_once 'core/bootstrap.php';

// Test readSafe
$data = JsonFile::readSafe('/nonexistent.json', array('default' => true));
assert($data['default'] === true, 'readSafe should return default');

// Test writeSafe
$success = JsonFile::writeSafe('/tmp/test.json', array('test' => 'data'));
assert($success === true, 'writeSafe should succeed');

echo "✓ All JsonFile tests passed\n";
```

**Тест 3: ModuleValidator**
```php
// test-validator.php
require_once 'core/bootstrap.php';

assert(ModuleValidator::isValidModuleId('admin') === true);
assert(ModuleValidator::isValidModuleId('Admin') === false);
assert(ModuleValidator::isValidModuleId('admin-pages') === true);
assert(ModuleValidator::isValidModuleId('admin_pages') === true);
assert(ModuleValidator::isValidModuleId('') === false);

echo "✓ All validator tests passed\n";
```

### 6.2 Интеграционное тестирование

**Чеклист**:
- [ ] Главная страница загружается
- [ ] Страница блога загружается
- [ ] Отдельные посты открываются
- [ ] Отдельные страницы открываются
- [ ] Страница продуктов работает
- [ ] Админ панель загружается
- [ ] Dashboard отображается
- [ ] Список страниц в админке
- [ ] Создание новой страницы
- [ ] Редактирование страницы
- [ ] Удаление страницы
- [ ] Список постов в админке
- [ ] Создание нового поста
- [ ] Редактирование поста
- [ ] Удаление поста
- [ ] Настройки загружаются
- [ ] Сохранение настроек работает
- [ ] Управление модулями работает
- [ ] CSRF защита работает

### 6.3 Регрессионное тестирование

**Проверить что не сломалось**:
- [ ] Авторизация в админке
- [ ] Выход из админки
- [ ] Навигация по сайту
- [ ] Sidebar в админке
- [ ] Quick actions в dashboard
- [ ] Переводы работают
- [ ] Хуки работают
- [ ] Зависимости модулей работают

---

## Этап 7: Документация (1 час)

### 7.1 Обновление документации

**Файлы для обновления**:
1. ✅ `docs/CREATING_MODULES.md` - добавить примеры AdminModule и ContentAdminModule
2. ✅ `docs/MODULE_SYSTEM.md` - обновить информацию о базовых классах
3. ✅ `README.md` - обновить примеры кода

### 7.2 Создание примеров

**Создать**:
1. `docs/examples/AdminModuleExample.php` - пример использования AdminModule
2. `docs/examples/ContentAdminModuleExample.php` - пример CRUD модуля
3. `docs/examples/HelpersExample.php` - примеры использования хелперов

---

## Этап 8: Финализация (30 минут)

### 8.1 Очистка кода

**Удалить**:
- Неиспользуемые методы в модулях
- Закомментированный код
- Временные файлы

### 8.2 Проверка производительности

**Измерить**:
- Время загрузки главной страницы
- Время загрузки админ панели
- Использование памяти

### 8.3 Коммит изменений

```bash
git add .
git commit -m "feat: Unify codebase with AdminModule and ContentAdminModule

- Add helper functions: view(), admin(), verify_csrf()
- Add JsonFile::readSafe() and writeSafe()
- Add ModuleValidator::isValidModuleId()
- Create AdminModule base class
- Create ContentAdminModule for CRUD operations
- Refactor AdminPagesModule to use ContentAdminModule (-75% code)
- Refactor AdminPostsModule to use ContentAdminModule (-75% code)
- Replace 'new Database()' with db() helper (15+ places)
- Replace 'new View()' with view() helper (20+ places)
- Replace CSRF checks with verify_csrf() (10+ places)
- Update documentation with new patterns

Total code reduction: ~405 lines
Improved maintainability and consistency"
```

---

## 📊 Метрики успеха

### Количественные метрики:
- [ ] Сокращение кода: минимум 300 строк
- [ ] Устранение дублирования: минимум 50%
- [ ] Покрытие тестами: минимум 80%

### Качественные метрики:
- [ ] Все тесты проходят
- [ ] Нет регрессий
- [ ] Код более читаемый
- [ ] Документация обновлена

---

## ⚠️ Риски и митигация

### Риск 1: Поломка существующего функционала
**Вероятность**: Средняя  
**Влияние**: Высокое  
**Митигация**:
- Создать backup перед началом
- Тестировать после каждого этапа
- Использовать отдельную ветку

### Риск 2: Несовместимость с существующими модулями
**Вероятность**: Низкая  
**Влияние**: Среднее  
**Митигация**:
- Сохранить обратную совместимость
- Постепенная миграция модулей
- Документировать изменения

### Риск 3: Проблемы с производительностью
**Вероятность**: Низкая  
**Влияние**: Среднее  
**Митигация**:
- Измерить производительность до и после
- Оптимизировать узкие места
- Использовать кеширование

---

## 🎯 Чеклист выполнения

### Подготовка
- [ ] Создан backup
- [ ] Создана ветка feature/unification
- [ ] Проверено текущее состояние

### Базовая инфраструктура
- [x] Обновлены хелперы
- [x] Расширен JsonFile
- [x] Расширен ModuleValidator
- [x] Созданы AdminModule и ContentAdminModule
- [x] Обновлен bootstrap.php

### Замена паттернов
- [x] Заменён `new Database()` на `db()`
- [x] Заменён `new View()` на `view()`
- [ ] Заменены CSRF проверки на `verify_csrf()`
- [ ] Заменена валидация на `ModuleValidator`

### Рефакторинг модулей
- [x] Обновлён AdminDashboardModule
- [ ] Обновлён AdminSettingsModule
- [x] Рефакторинг AdminPagesModule
- [x] Рефакторинг AdminPostsModule

### Тестирование
- [ ] Модульные тесты пройдены
- [ ] Интеграционные тесты пройдены
- [ ] Регрессионные тесты пройдены

### Документация
- [ ] Обновлена документация
- [ ] Созданы примеры
- [ ] Написаны комментарии

### Финализация
- [ ] Код очищен
- [ ] Производительность проверена
- [ ] Изменения закоммичены

---

## 📅 Временная оценка

| Этап | Время | Накопительно |
|------|-------|--------------|
| 1. Подготовка | 30 мин | 30 мин |
| 2. Базовая инфраструктура | 1 час | 1.5 часа |
| 3. Замена паттернов | 2 часа | 3.5 часа |
| 4. Рефакторинг модулей | 3 часа | 6.5 часов |
| 5. Обновление шаблонов | 1 час | 7.5 часов |
| 6. Тестирование | 2 часа | 9.5 часов |
| 7. Документация | 1 час | 10.5 часов |
| 8. Финализация | 30 мин | 11 часов |

**Общее время**: ~11 часов (1.5 рабочих дня)

---

## 🚀 Следующие шаги после интеграции

1. Мониторинг production в течение недели
2. Сбор feedback от команды
3. Оптимизация на основе метрик
4. Планирование следующих улучшений

---

## 📞 Контакты и поддержка

При возникновении проблем:
1. Проверить логи: `storage/logs/`
2. Откатиться на backup: `git checkout backup-before-unification`
3. Создать issue с описанием проблемы
