# Project Unification Analysis

## Обнаруженные паттерны для унификации

### 1. 🔴 Дублирование создания Database

**Проблема**: В 15+ местах создаётся `new Database()`

**Текущий код:**
```php
// В каждом модуле
$db = new Database();
$pages = $db->query('pages', ...);
```

**Решение**: Использовать хелпер `db()`
```php
// Уже есть в helpers.php!
$pages = db()->query('pages', ...);
```

**Места для замены:**
- modules/products/ProductsModule.php (3 места)
- modules/pages/PagesModule.php (1 место)
- modules/admin-posts/AdminPostsModule.php (3 места)
- modules/admin-pages/AdminPagesModule.php (3 места)
- core/PageController.php (4 места)
- core/Auth.php (1 место)
- core/User.php (1 место)

---

### 2. 🔴 Дублирование создания View

**Проблема**: В 20+ местах создаётся `new View()`

**Текущий код:**
```php
$view = new View();
$view->render('template', $data);
```

**Решение 1**: Создать хелпер `view()`
```php
function view($template, $data = array()) {
    return (new View())->render($template, $data);
}

// Использование
view('template', $data);
```

**Решение 2**: Использовать метод Module::view() внутри модулей
```php
// Уже есть в Module!
$this->view('template', $data);
```

---

### 3. 🔴 Дублирование получения admin модуля

**Проблема**: В 10+ местах повторяется:
```php
$admin = app()->modules()->getModule('admin');
if ($admin && method_exists($admin, 'adminRoute')) {
    $admin->adminRoute(...);
}
```

**Решение**: Создать хелпер `admin()`
```php
function admin() {
    static $admin = null;
    if ($admin === null) {
        $admin = app()->modules()->getModule('admin');
    }
    return $admin;
}

// Использование
if (admin() && method_exists(admin(), 'adminRoute')) {
    admin()->adminRoute('GET', 'path', $callback);
}
```

**Или лучше**: Создать trait для admin-модулей
```php
trait AdminModuleTrait {
    protected function adminRoute($method, $pattern, $callback) {
        $admin = app()->modules()->getModule('admin');
        if ($admin && method_exists($admin, 'adminRoute')) {
            return $admin->adminRoute($method, $pattern, $callback);
        }
        return null;
    }
    
    protected function renderAdmin($title, $content, $extra = array()) {
        $admin = app()->modules()->getModule('admin');
        if ($admin && method_exists($admin, 'render')) {
            return $admin->render($title, $content, $extra);
        }
        http_response_code(500);
        echo 'Admin module not loaded';
    }
}
```

---

### 4. 🔴 Дублирование валидации имён модулей

**Проблема**: Одинаковая проверка в 2 местах:

**AdminSettingsModule:**
```php
private function isValidModuleName($name) {
    return ($name !== '' && preg_match('/^[a-z0-9_-]+$/', $name));
}
```

**ModuleManager:**
```php
private function assertValidModuleName($name, $context = null) {
    if ($name === '' || !preg_match('/^[a-z0-9_-]+$/', $name)) {
        throw new Exception("Invalid module name: '{$name}'");
    }
}
```

**Решение**: Создать статический метод в ModuleValidator
```php
class ModuleValidator {
    public static function isValidModuleId($id) {
        return is_string($id) && $id !== '' && preg_match('/^[a-z0-9-]+$/', $id);
    }
    
    public static function assertValidModuleId($id, $context = null) {
        if (!self::isValidModuleId($id)) {
            $message = "Invalid module ID: '{$id}'";
            if ($context) {
                $message .= " ({$context})";
            }
            throw new InvalidArgumentException($message);
        }
    }
}
```

---

### 5. 🔴 Дублирование CSRF проверок

**Проблема**: В каждом POST-обработчике повторяется:
```php
$token = (string)request()->post('csrf_token', '');
if (!auth()->verifyCsrfToken($token)) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    return;
}
```

**Решение 1**: Создать middleware
```php
class CsrfMiddleware {
    public function handle() {
        if (request()->method() === 'POST') {
            $token = request()->post('csrf_token', '');
            if (!auth()->verifyCsrfToken($token)) {
                http_response_code(403);
                echo 'Invalid CSRF token';
                return false;
            }
        }
        return true;
    }
}
```

**Решение 2**: Создать хелпер
```php
function verify_csrf() {
    $token = request()->post('csrf_token', '');
    if (!auth()->verifyCsrfToken($token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        return false;
    }
    return true;
}

// Использование
if (!verify_csrf()) return;
```

---

### 6. 🔴 Дублирование работы с JsonFile

**Проблема**: Повторяющийся паттерн try-catch:
```php
try {
    $manifest = JsonFile::read($path);
} catch (JsonFileException $e) {
    // Handle error
}
```

**Решение**: Добавить методы с fallback
```php
class JsonFile {
    public static function readSafe($path, $default = array()) {
        try {
            return self::read($path);
        } catch (JsonFileException $e) {
            logger()->warning('Failed to read JSON file', array(
                'path' => $path,
                'error' => $e->getMessage()
            ));
            return $default;
        }
    }
    
    public static function writeSafe($path, $data) {
        try {
            return self::write($path, $data);
        } catch (JsonFileException $e) {
            logger()->error('Failed to write JSON file', array(
                'path' => $path,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }
}
```

---

### 7. 🔴 Дублирование получения user

**Проблема**: Повторяется `auth()->user()` для передачи в view

**Текущий код:**
```php
return $admin->render('Title', $content, array(
    'user' => auth()->user(),
));
```

**Решение**: Сделать user доступным глобально в View
```php
class View {
    private function getGlobalData() {
        return array(
            'user' => auth()->user(),
            'locale' => translator()->getLocale(),
            'csrf_token' => auth()->generateCsrfToken(),
        );
    }
    
    public function render($template, $data = array()) {
        $data = array_merge($this->getGlobalData(), $data);
        // ... render logic
    }
}
```

---

### 8. 🟡 Дублирование паттерна admin-модулей

**Проблема**: Все admin-модули имеют одинаковую структуру:

```php
class AdminXxxModule extends Module {
    public function init() {
        // Sidebar
        app()->hooks()->register('admin.sidebar', function ($items) { ... });
        
        // Routes
        app()->hooks()->register('routes.register', function ($data) {
            $admin = app()->modules()->getModule('admin');
            $admin->adminRoute(...);
        });
    }
}
```

**Решение**: Создать базовый класс AdminModule
```php
abstract class AdminModule extends Module {
    protected function registerAdminRoute($method, $pattern, $callback) {
        app()->hooks()->register('routes.register', function ($data) use ($method, $pattern, $callback) {
            $admin = app()->modules()->getModule('admin');
            if ($admin && method_exists($admin, 'adminRoute')) {
                $admin->adminRoute($method, $pattern, $callback);
            }
            return $data;
        });
    }
    
    protected function registerSidebarItem($item) {
        app()->hooks()->register('admin.sidebar', function ($items) use ($item) {
            if (!is_array($items)) {
                $items = array();
            }
            $items[] = $item;
            return $items;
        });
    }
    
    protected function renderAdmin($title, $content, $extra = array()) {
        $admin = app()->modules()->getModule('admin');
        if ($admin && method_exists($admin, 'render')) {
            return $admin->render($title, $content, $extra);
        }
        http_response_code(500);
        echo 'Admin module not loaded';
    }
}
```

---

### 9. 🟡 Дублирование CRUD операций

**Проблема**: AdminPagesModule и AdminPostsModule имеют почти идентичный код

**Решение**: Создать базовый ContentAdminModule
```php
abstract class ContentAdminModule extends AdminModule {
    abstract protected function getContentType();
    abstract protected function getCollectionName();
    abstract protected function getSchema();
    
    public function listItems() {
        $items = db()->query($this->getCollectionName(), array(), array(
            'sort' => 'updated_at',
            'order' => 'desc'
        ));
        
        $content = $this->view($this->getId() . ':list', array(
            'items' => $items
        ));
        
        return $this->renderAdmin($this->getName(), $content);
    }
    
    public function createItem() {
        if (!verify_csrf()) return;
        
        $data = $this->extractFormData();
        $id = $this->generateId($data);
        
        db()->write($this->getCollectionName(), $id, $data);
        
        redirect(base_url('/admin/' . $this->getId()));
    }
    
    // ... и т.д.
}
```

---

### 10. 🟢 Хорошие практики (уже есть)

**✅ Singleton паттерн для сервисов:**
```php
function db() {
    static $db = null;
    if ($db === null) {
        $db = new Database();
    }
    return $db;
}
```

**✅ Централизованная конфигурация:**
```php
config('key.path', 'default');
```

**✅ Централизованное логирование:**
```php
logger()->info('message', $context);
```

**✅ Хуки для расширяемости:**
```php
$this->hook('hook.name', $callback);
$data = $this->fireHook('hook.name', $data);
```

---

## Приоритеты унификации

### 🔴 Высокий приоритет (быстрые wins):

1. **Заменить `new Database()` на `db()`** - 15+ мест, простая замена
2. **Создать хелпер `view()`** - 20+ мест, улучшит читаемость
3. **Создать хелпер `verify_csrf()`** - 10+ мест, улучшит безопасность
4. **Унифицировать валидацию модулей** - использовать ModuleValidator везде

### 🟡 Средний приоритет (рефакторинг):

5. **Создать AdminModule базовый класс** - упростит создание admin-модулей
6. **Создать ContentAdminModule** - устранит дублирование CRUD
7. **Добавить глобальные данные в View** - упростит передачу user/csrf

### 🟢 Низкий приоритет (оптимизация):

8. **Добавить JsonFile::readSafe/writeSafe** - улучшит обработку ошибок
9. **Создать хелпер `admin()`** - небольшое улучшение
10. **Оптимизировать кеширование** - производительность

---

## Метрики дублирования

| Паттерн | Количество дублирований | Потенциальная экономия строк |
|---------|------------------------|------------------------------|
| `new Database()` | 15+ | ~30 строк |
| `new View()` | 20+ | ~40 строк |
| `app()->modules()->getModule('admin')` | 10+ | ~50 строк |
| CSRF проверки | 10+ | ~60 строк |
| Валидация модулей | 5+ | ~25 строк |
| Admin CRUD | 2 модуля | ~200 строк |
| **ИТОГО** | **60+** | **~405 строк** |

---

## Рекомендации

### Немедленно:
1. Создать недостающие хелперы
2. Заменить `new Database()` на `db()`
3. Унифицировать валидацию модулей

### В ближайшее время:
4. Создать AdminModule базовый класс
5. Рефакторить admin-модули для использования базового класса
6. Создать ContentAdminModule для CRUD

### Долгосрочно:
7. Добавить middleware систему
8. Улучшить систему кеширования
9. Оптимизировать производительность

---

## Следующие шаги

1. Создать файл `core/AdminModule.php` с базовым классом
2. Создать файл `core/ContentAdminModule.php` для CRUD
3. Добавить хелперы в `core/helpers.php`
4. Обновить существующие модули для использования новых классов
5. Написать тесты для новых компонентов
6. Обновить документацию
