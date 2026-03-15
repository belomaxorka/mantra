# Примеры использования системы логирования

## Базовое использование

```php
// Простое логирование
logger()->info('User registered', array('username' => 'john'));
logger()->error('Failed to save file', array('path' => '/some/path'));
logger()->debug('Processing data', array('items' => 10));
```

## Использование каналов

```php
// Создание логгера для конкретного канала
$securityLogger = logger('security');
$securityLogger->warning('Failed login attempt', array(
    'username' => 'admin',
    'ip' => $_SERVER['REMOTE_ADDR']
));

// Логи безопасности будут в файле: storage/logs/security-2026-03-15.log
```

## Логирование в модулях

```php
class MyModule extends Module {
    public function init() {
        logger('mymodule')->info('Module initialized');
        
        $this->hook('routes.register', array($this, 'registerRoutes'));
    }
    
    public function registerRoutes($data) {
        $router = $data['router'];
        
        $router->get('/my-route', function() {
            logger('mymodule')->debug('Route accessed');
            
            try {
                // Your code here
                $result = $this->doSomething();
                
                logger('mymodule')->info('Action completed', array(
                    'result' => $result
                ));
            } catch (Exception $e) {
                logger('mymodule')->error('Action failed', array(
                    'exception' => $e
                ));
                throw $e;
            }
        });
        
        return $data;
    }
}
```

## Логирование с контекстом

```php
// Контекст автоматически добавляется в JSON формате
logger()->info('User {username} performed {action}', array(
    'username' => 'john',
    'action' => 'delete',
    'item_id' => 123,
    'timestamp' => time()
));

// В логе будет:
// [2026-03-15 10:30:45] app.INFO: User john performed delete {"username":"john","action":"delete","item_id":123,"timestamp":1710500445}
```

## Логирование исключений

```php
try {
    $db->write('users', $id, $data);
} catch (Exception $e) {
    // Исключение автоматически форматируется с трассировкой
    logger()->error('Database write failed', array(
        'exception' => $e,
        'collection' => 'users',
        'id' => $id
    ));
}
```

## Условное логирование

```php
// Логирование только в debug режиме
log_debug('Detailed debug information', array('data' => $complexArray));

// Или проверка вручную
if (defined('MANTRA_DEBUG') && MANTRA_DEBUG) {
    logger()->debug('Debug info', array('memory' => memory_get_usage()));
}
```

## Настройка уровня логирования

В `content/settings/config.json`:

```json
{
    "debug": false,
    "log_level": "warning",
    "log_retention_days": 60
}
```

Доступные уровни (от высшего к низшему):
- `emergency` - Система неработоспособна
- `alert` - Требуется немедленное действие
- `critical` - Критические условия
- `error` - Ошибки выполнения
- `warning` - Предупреждения
- `notice` - Нормальные, но значимые события
- `info` - Информационные сообщения
- `debug` - Детальная отладочная информация

## Очистка старых логов

```php
// Вручную удалить логи старше 30 дней
$deleted = logger()->clearOldLogs(30);
logger()->info('Cleaned old logs', array('deleted' => $deleted));

// Автоматическая очистка происходит раз в день при запуске системы
// согласно настройке log_retention_days в конфиге
```

## Примеры для разных сценариев

### Аутентификация
```php
logger('auth')->info('Login attempt', array('username' => $username));
logger('auth')->warning('Invalid password', array('username' => $username));
logger('auth')->info('Login successful', array('user_id' => $userId));
logger('auth')->info('Logout', array('user_id' => $userId));
```

### База данных
```php
logger('database')->debug('Query executed', array(
    'collection' => 'posts',
    'filters' => $filters
));
logger('database')->error('Write failed', array(
    'collection' => 'posts',
    'id' => $id,
    'error' => $errorMessage
));
```

### Производительность
```php
$start = microtime(true);
// ... ваш код ...
$duration = microtime(true) - $start;

logger('performance')->info('Operation completed', array(
    'operation' => 'generate_report',
    'duration' => round($duration, 3),
    'memory' => memory_get_peak_usage(true)
));
```

### Безопасность
```php
logger('security')->warning('Suspicious activity', array(
    'ip' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
    'action' => 'multiple_failed_logins'
));

logger('security')->alert('Potential attack detected', array(
    'type' => 'sql_injection_attempt',
    'input' => $suspiciousInput
));
```
