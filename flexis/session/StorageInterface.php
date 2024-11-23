<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Интерфейс, определяющий объект хранения сессии Flexis.
 */
interface StorageInterface {
    /**
     * Возвращает имя сессии.
     *
     * @return  string  Имя сессии.
     */
    public function getName(): string;

    /**
     * Устанавливает имя сессии.
     *
     * @param   string  $name  Имя сессии.
     *
     * @return  $this
     */
    public function setName(string $name): self;

    /**
     * Возвращает идентификатор сессии.
     *
     * @return  string  Идентификатор сессии.
     */
    public function getId(): string;

    /**
     * Устанавливает идентификатор сессии.
     *
     * @param   string  $id  Идентификатор сессии.
     *
     * @return  $this
     */
    public function setId(string $id): self;

    /**
     * Проверяет, активна ли сессия.
     *
     * @return  boolean
     */
    public function isActive(): bool;

    /**
     * Проверяет, запущена ли сессия.
     *
     * @return  boolean
     */
    public function isStarted(): bool;

    /**
     * Возвращает данные из хранилища сессии.
     *
     * @param   string  $name     Имя переменной.
     * @param   mixed   $default  Значение переменной по умолчанию, если оно не установлено.
     *
     * @return  mixed  Значение переменной.
     */
    public function get(string $name, mixed $default): mixed;

    /**
     * Устанавливает данные в хранилище сессии.
     *
     * @param   string  $name   Имя переменной.
     * @param   mixed   $value  Значение переменной.
     *
     * @return  mixed  Старое значение переменной.
     */
    public function set(string $name, mixed $value): mixed;

    /**
     * Проверяет, существуют ли данные в хранилище сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  boolean
     */
    public function has(string $name): bool;

    /**
     * Удалите переменную из хранилища сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  mixed   Значение из сессии или NULL, если не установлено.
     */
    public function remove(string $name): mixed;

    /**
     * Очищает все переменные из хранилища сессии.
     *
     * @return  void
     */
    public function clear(): void;

    /**
     * Извлекает все переменные из хранилища сессии.
     *
     * @return  array
     */
    public function all(): array;

    /**
     * Начать сессию.
     *
     * @return  void
     */
    public function start(): void;

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
    public function regenerate(bool $destroy = false): bool;

    /**
     * Записывает данные сессии и завершает её.
     *
     * @return  void
     *
     * @see     session_write_close()
     */
    public function close(): void;

    /**
     * Выполняет сбор мусора данных сессии.
     *
     * @return  integer|boolean  Количество удаленных сессий в случае успеха или логическое значение false в случае сбоя или если функция не поддерживается.
     *
     * @see     session_gc()
     */
    public function gc(): int|bool;

    /**
     * Прерывает текущую сессию.
     *
     * @return  boolean
     *
     * @see     session_abort()
     */
    public function abort(): bool;
}
