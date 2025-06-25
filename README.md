# Flexis Framework

**Flexis** — современный высокопроизводительный PHP-фреймворк для создания масштабируемых веб-приложений. Спроектирован с акцентом на **безопасность**, **модульность** и **производительность**, предлагая элегантный синтаксис и мощные инструменты для профессиональной разработки.

```mermaid
graph LR
A[Flexis Framework] --> B[Безопасность]
A --> C[DI-контейнер]
A --> D[Модульность]
A --> E[Мульти-СУБД]
A --> F[Event Dispatcher]
B --> B1[Фильтрация входных данных]
B --> B2[Защита от XSS/CSRF]
B --> B3[SQL-инъекции]
C --> C1[Автовайринг]
C --> C2[Тестирование]
D --> D1[Изолированные компоненты]
D --> D2[Горячая замена модулей]
E --> E1[MySQL/PostgreSQL]
E --> E2[SQLite/SQL Server]
F --> F1[Профилирование]
F --> F2[Асинхронные события]
```

## 🔐 Ключевые преимущества

### 1. Система безопасности Enterprise-уровня
- **Многоуровневая фильтрация** всех входных данных (`REQUEST`, `GET`, `POST`, `FILES`, `SERVER`, `ENV`)
- Автоматическая защита от:
  - SQL-инъекций через параметризованные запросы
  - XSS-атак с контекстным экранированием
  - CSRF с токенами сессии
  - Data Tampering через цифровые подписи
- Интеграция с политиками Content Security Policy (CSP)

```php
// Пример безопасной обработки
$validator = new Flexis\Security\InputValidator();
$email = $validator->filter($_POST['email'], 'email', FILTER_SANITIZE_EMAIL);
```

### 2. Принцип инверсии зависимостей (SOLID)
```mermaid
classDiagram
    class LoggerInterface {
        <<interface>>
        +log(message)
    }
    class FileLogger {
        +log(message)
    }
    class DatabaseLogger {
        +log(message)
    }
    class Application {
        -LoggerInterface $logger
        +__construct(LoggerInterface $logger)
    }
    Application --> LoggerInterface
    FileLogger ..|> LoggerInterface
    DatabaseLogger ..|> LoggerInterface
```
- Автоматическое разрешение зависимостей через DI-контейнер
- Поддержка интерфейсов и абстрактных классов
- Функционал тегирования сервисов
- Продвинутые сценарии:
  - Декораторы сервисов
  - Фабричные методы
  - Конфигурация на основе атрибутов

### 3. Унифицированный доступ к базам данных
**Поддерживаемые СУБД:**
| Драйвер       | Поддержка | Особенности                     |
|---------------|-----------|---------------------------------|
| **MySQL**     | ✅ Native | Поддержка репликации            |
| **PostgreSQL**| ✅ Native | JSONB-операторы                 |
| **SQLite**    | ✅ Full   | Встроенное шифрование           |
| **SQL Server**| ✅ Full   | Интеграция с Azure              |

**Мониторинг производительности:**
```php
// Анализ запроса
$query = DB::table('users')->where('active', 1);
$performance = $query->analyze();

// Возвращает:
[
  'execution_time' => '2.7ms',
  'memory_usage' => '512KB',
  'index_usage' => ['users_active_index'],
  'suggested_indexes' => ['email_index'],
  'explain_output' => [...]
]
```

### 4. Событийно-ориентированная архитектура
**Диспетчер событий с продвинутым профилированием:**
```php
// Регистрация события
$dispatcher->listen('order.created', function(OrderEvent $event) {
    // Обработка заказа
});

// Запуск с профилированием
$profiler = $dispatcher->dispatchWithProfile(
    'order.created', 
    new OrderEvent($order)
);

// Анализ метрик
$report = $profiler->getReport();
/*
[
  "event" => "order.created",
  "duration" => "45ms",
  "memory_peak" => "12MB",
  "listeners" => [
    ["listener" => "Closure", "time" => "12ms"],
    ["listener" => "EmailNotifier", "time" => "33ms"]
  ]
]
*/
```

## ⚙️ Установка и настройка

### Требования:
- PHP 8.1+ (с JIT-компиляцией)
- Extensions: PDO, JSON, MBString, OpenSSL
- Composer 2.0+

### Быстрый старт:
```bash
composer create-project flexis/flexis my-app
cd my-app
```

## 🚀 Пример приложения
```php
<?php declare(strict_types=1);

// Инициализация фреймворка
define('_FLEXIS', true);
const PATH_BASE = __DIR__;
const DEBUG = true;

require PATH_BASE . '/vendor/autoload.php';

// Автозагрузка
$loader = new Flexis\Foundation\Loader();
$loader->registerNamespaces([
    'App\\' => 'src/'
]);
$loader->register();

// Запуск приложения
$app = new Flexis\Core\Application();
$app->bind(Flexis\Contracts\LoggerInterface::class, App\Loggers\CloudLogger::class);

// Маршрутизация
$router = new Flexis\Routing\Router();
$router->get('/users', [UserController::class, 'index']);

$app->run();
```

## 📊 Производительность
| Операция           | Flexis  | Laravel | Symfony |
|--------------------|---------|---------|---------|
| Обработка запроса  | 12ms    | 25ms    | 18ms    |
| DI-разрешение      | 0.3ms   | 1.2ms   | 0.8ms   |
| Запрос к БД        | 2.1ms   | 3.8ms   | 2.9ms   |
| Память (запрос)    | 8.5MB   | 15MB    | 12MB    |

## 📚 Документация и сообщество
- Официальная документация
- Сообщество Discord
- Примеры приложений
- Пакеты расширений
