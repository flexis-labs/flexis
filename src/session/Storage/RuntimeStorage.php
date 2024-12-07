<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Storage;

use Flexis\Session\StorageInterface;

/**
 * Объект хранилища сессии, который хранит объекты в памяти времени выполнения.
 *
 * Он предназначен для использования в приложениях CLI,
 * включая приложения модульного тестирования в PHPUnit.
 */
class RuntimeStorage implements StorageInterface {
    /**
     * Флаг, если сессия активна.
     *
     * @var    boolean
     */
    private bool $active = false;

    /**
     * Внутренний флаг, указывающий, была ли закрыта сессия.
     *
     * @var    boolean
     */
    private bool $closed = false;

    /**
     * Внутреннее хранилище данных.
     *
     * @var    array
     */
    private array $data = [];

    /**
     * Идентификатор сессии.
     *
     * @var    string
     */
    private string $id = '';

    /**
     * Имя сессии.
     *
     * @var    string
     */
    private string $name = 'MockSession';

    /**
     * Внутренний флаг, определяющий, запущена ли сессия.
     *
     * @var    boolean
     */
    private bool $started = false;

    /**
     * Извлекает все переменные из хранилища сессии.
     *
     * @return  array
     */
    public function all(): array {
        return $this->data;
    }

    /**
     * Очищает все переменные из хранилища сессии.
     *
     * @return  void
     */
    public function clear(): void {
        $this->data = [];
    }

    /**
     * Записывает данные сессии и завершает её.
     *
     * @return  void
     *
     * @see     session_write_close()
     */
    public function close(): void {
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
        return 0;
    }

    /**
     * Прерывает текущую сессию.
     *
     * @return  boolean
     *
     * @see     session_abort()
     */
    public function abort(): bool {
        $this->closed  = true;
        $this->started = false;

        return true;
    }

    /**
     * Генерирует идентификатор сессии.
     *
     * @return  string
     */
    private function generateId(): string {
        return hash('sha256', uniqid(mt_rand()));
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

        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        return $default;
    }

    /**
     * Возвращает идентификатор сессии.
     *
     * @return  string  Идентификатор сессии.
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * Возвращает имя сессии.
     *
     * @return  string  Имя сессии.
     */
    public function getName(): string {
        return $this->name;
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

        return isset($this->data[$name]);
    }

    /**
     * Проверяет, активна ли сессия.
     *
     * @return  boolean
     */
    public function isActive(): bool {
        return $this->active = $this->started;
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
     * Удаляет переменную из хранилища сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  mixed  Значение из сессии или NULL, если не установлено.
     */
    public function remove(string $name): mixed {
        if (!$this->isStarted()) {
            $this->start();
        }

        $old = $this->data[$name] ?? null;

        unset($this->data[$name]);

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
        if (!$this->isActive()) {
            return false;
        }

        if ($destroy) {
            $this->id = $this->generateId();
        }

        return true;
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

        $old = $this->data[$name] ?? null;

        $this->data[$name] = $value;

        return $old;
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

        $this->id = $id;

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

        $this->name = $name;

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

        if (empty($this->id)) {
            $this->setId($this->generateId());
        }

        $this->closed  = false;
        $this->started = true;
        $this->isActive();
    }
}
