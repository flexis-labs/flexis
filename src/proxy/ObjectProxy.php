<?php

/**
 * Часть пакета Flexis Proxy Framework.
 */

namespace Flexis\Proxy;

/**
 * Класс прокси объекта.
 */
class ObjectProxy implements ProxyInterface, \Iterator {
    /**
     * Источник данных.
     *
     * @var object
     */
    protected object $data;

    /**
     * Экземпляр итератора.
     *
     * @var \ArrayIterator
     */
    protected \ArrayIterator $iterator;

    /**
     * Конструктор класса.
     *
     * @param  object  $data  Объект для доступа через прокси.
     */
    public function __construct(object $data) {
        $this->data = $data;
    }

    /**
     * Реализация чтения из объекта
     *
     * @param mixed $key  Ключ для чтения.
     *
     * @return mixed
     */
    public function __get(mixed $key): mixed {
        return $this->data->$key ?? null;
    }

    /**
     * Реализация записи в объект.
     *
     * @param mixed $key    Ключ для записи.
     * @param mixed $value  Значение для записи.
     *
     * @return void
     */
    public function __set(mixed $key, mixed $value): void {
        $this->data->$key = $value;
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return mixed
     */
    public function current(): mixed {
        return $this->iterator->current();
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return int|null|string
     */
    public function key(): int|null|string {
        return $this->iterator->key();
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return void
     */
    public function next(): void {
        $this->iterator->next();
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return void
     */
    public function rewind(): void {
        $this->iterator = new \ArrayIterator($this->data);
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return boolean
     */
    public function valid(): bool {
        return $this->iterator->valid();
    }
}
