<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Storage;

use Flexis\Session\HandlerInterface;
use Flexis\Session\StorageInterface;

/**
 * Базовый класс, предоставляющий хранилище сессии.
 */
class NativeStorage implements StorageInterface {
    /**
     * Флаг, если сессия активна.
     *
     * @var    boolean
     */
    private  bool $active = false;

    /**
     * Внутренний флаг, указывающий, была ли закрыта сессия.
     *
     * @var    boolean
     */
    private  bool $closed = false;

    /**
     * Обработчик сохранения сессии.
     *
     * @var    \SessionHandlerInterface|null
     */
    private ?\SessionHandlerInterface $handler = null;

    /**
     * Внутренний флаг, определяющий, запущена ли сессия.
     *
     * @var    boolean
     */
    private bool $started = false;

    /**
     * Конструктор.
     *
     * @param   \SessionHandlerInterface|null  $handler  Обработчик сохранения сессии.
     * @param   array                          $options  Параметры сессии.
     */
    public function __construct(?\SessionHandlerInterface $handler = null, array $options = []) {
        $options += [
            'use_cookies'   => 1,
            'use_trans_sid' => 0,
        ];

        if (!headers_sent()) {
            session_cache_limiter('none');
        }

        session_register_shutdown();

        $this->setOptions($options);
        $this->setHandler($handler);
    }

    /**
     * Извлекает все переменные из хранилища сессии.
     *
     * @return  array
     */
    public function all(): array {
        return $_SESSION;
    }

    /**
     * Очищает все переменные из хранилища сессии.
     *
     * @return  void
     */
    public function clear(): void {
        $_SESSION = [];
    }

    /**
     * Записывает данные сессии и завершает её.
     *
     * @return  void
     *
     * @see     session_write_close()
     */
    public function close(): void {
        session_write_close();

        $this->closed  = true;
        $this->started = false;
    }

    /**
     * Выполняет сбор мусора данных сессии.
     *
     * @return  integer|boolean  Количество удаленных сессий в случае успеха или логическое значение false в случае сбоя или если функция не поддерживается.
     *
     * @see     session_gc()
     */
    public function gc(): int|bool {
        if (!$this->isStarted()) {
            $this->start();
        }

        return session_gc();
    }

    /**
     * Прерывает текущую сессию.
     *
     * @return  boolean
     *
     * @see     session_abort()
     */
    public function abort(): bool {
        if (!$this->isStarted()) {
            return true;
        }

        return session_abort();
    }

    /**
     * Возвращает данные из хранилища сессии.
     *
     * @param   string  $name     Имя переменной.
     * @param   mixed   $default  Значение переменной по умолчанию, если оно не установлено.
     *
     * @return  mixed  Значение переменной.
     */
    public function get(string $name, mixed $default): mixed {
        if (!$this->isStarted()) {
            $this->start();
        }

        if (isset($_SESSION[$name])) {
            return $_SESSION[$name];
        }

        return $default;
    }

    /**
     * Возвращает экземпляр обработчика сохранения.
     *
     * @return  \SessionHandlerInterface|null
     */
    public function getHandler(): ?\SessionHandlerInterface {
        return $this->handler;
    }

    /**
     * Возвращает идентификатор сессии.
     *
     * @return  string  Идентификатор сессии.
     */
    public function getId(): string {
        return session_id();
    }

    /**
     * Возвращает имя сессии.
     *
     * @return  string  Имя сессии.
     */
    public function getName(): string {
        return session_name();
    }

    /**
     * Проверяет, существуют ли данные в хранилище сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  boolean
     */
    public function has(string $name): bool {
        if (!$this->isStarted()) {
            $this->start();
        }

        return isset($_SESSION[$name]);
    }

    /**
     * Проверяет, активна ли сессия.
     *
     * @return  boolean
     */
    public function isActive(): bool {
        return $this->active = session_status() === \PHP_SESSION_ACTIVE;
    }

    /**
     * Проверяет, запущена ли сессия.
     *
     * @return  boolean
     */
    public function isStarted(): bool {
        return $this->started;
    }

    /**
     * Удаляет переменную из хранилища сессий.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  mixed  Значение из сессии или NULL, если не установлено.
     */
    public function remove(string $name): mixed {
        if (!$this->isStarted()) {
            $this->start();
        }

        $old = $_SESSION[$name] ?? null;

        unset($_SESSION[$name]);

        return $old;
    }

    /**
     * Восстанавливает идентификатор сессии, представляющий это хранилище.
     *
     * Этот метод должен вызывать session_regenerate_id($destroy),
     * если только этот интерфейс не используется для объекта хранения,
     * предназначенного для модульного или функционального тестирования,
     * где реальная сессия PHP будет мешать тестированию.
     *
     * @param   boolean  $destroy  Уничтожить сессию при регенерации?
     *
     * @return  boolean
     *
     * @see     session_regenerate_id()
     */
    public function regenerate(bool $destroy = false): bool {
        if (headers_sent() || !$this->isActive()) {
            return false;
        }

        return session_regenerate_id($destroy);
    }

    /**
     * Устанавливает данные в хранилище сессии.
     *
     * @param   string  $name   Имя переменной.
     * @param   mixed   $value  Значение переменной.
     *
     * @return  mixed  Старое значение переменной.
     */
    public function set(string $name, mixed $value = null): mixed {
        if (!$this->isStarted()) {
            $this->start();
        }

        $old = $_SESSION[$name] ?? null;

        $_SESSION[$name] = $value;

        return $old;
    }

    /**
     * Регистрирует обработчик сохранения сессии как обработчик сессии PHP.
     *
     * @param   ?\SessionHandlerInterface  $handler  Используемый обработчик сохранения.
     *
     * @return  $this
     * @throws  \RuntimeException
     */
    public function setHandler(?\SessionHandlerInterface $handler = null): self {
        if ($handler instanceof HandlerInterface) {
            if (!$handler::isSupported()) {
                throw new \RuntimeException(
                    sprintf(
                        'Обработчик «%s» не поддерживается в этой среде.',
                        \get_class($handler)
                    )
                );
            }
        }

        $this->handler = $handler;

        if (!headers_sent() && !$this->isActive()) {
            session_set_save_handler($this->handler, false);
        }

        return $this;
    }

    /**
     * Устанавливает идентификатор сессии.
     *
     * @param   string  $id  Идентификатор сессии.
     *
     * @return  $this
     * @throws  \LogicException
     */
    public function setId(string $id): self {
        if ($this->isActive()) {
            throw new \LogicException('Невозможно изменить идентификатор активного сессии.');
        }

        session_id($id);

        return $this;
    }

    /**
     * Устанавливает имя сессии.
     *
     * @param   string  $name  Имя сессии.
     *
     * @return  $this
     * @throws  \LogicException
     */
    public function setName(string $name): self {
        if ($this->isActive()) {
            throw new \LogicException('Невозможно изменить имя активного сессии.');
        }

        session_name($name);

        return $this;
    }

    /**
     * Устанавливает переменные session.*ini.
     *
     * Для удобства мы опускаем «session».
     * Явно игнорируем другие ключи ini.
     *
     * @param   array  $options  Массив директив ini сессии (ключ => значение).
     *
     * @return  $this
     *
     * @see     http://php.net/session.configuration
     */
    public function setOptions(array $options): self {
        if (headers_sent() || $this->isActive()) {
            return $this;
        }

        $validOptions = array_flip(
            [
                'cache_limiter', 'cache_expire', 'cookie_domain', 'cookie_httponly', 'cookie_lifetime', 'cookie_path', 'cookie_secure', 'gc_divisor',
                'gc_maxlifetime', 'gc_probability', 'lazy_write', 'name', 'referer_check', 'serialize_handler', 'use_strict_mode', 'use_cookies',
                'use_only_cookies', 'use_trans_sid', 'upload_progress.enabled', 'upload_progress.cleanup', 'upload_progress.prefix',
                'upload_progress.name', 'upload_progress.freq', 'upload_progress.min-freq', 'url_rewriter.tags', 'sid_length',
                'sid_bits_per_character', 'trans_sid_hosts', 'trans_sid_tags',
            ]
        );

        foreach ($options as $key => $value) {
            if (isset($validOptions[$key])) {
                ini_set('session.' . $key, $value);
            }
        }

        return $this;
    }

    /**
     * Начать сессию.
     *
     * @return  void
     */
    public function start(): void {
        if ($this->isStarted()) {
            return;
        }

        if ($this->isActive()) {
            throw new \RuntimeException('Не удалось запустить сессию: PHP уже запущен.');
        }

        if (ini_get('session.use_cookies') && headers_sent($file, $line)) {
            throw new \RuntimeException(
                sprintf('Не удалось запустить сессию, поскольку заголовки уже отправлены «%s» в строке %d.', $file, $line)
            );
        }

        if (!session_start()) {
            throw new \RuntimeException('Не удалось начать сессию.');
        }

        $this->isActive();
        $this->closed  = false;
        $this->started = true;
    }
}
