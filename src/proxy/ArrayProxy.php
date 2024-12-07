<?php

/**
 * Часть пакета Flexis Proxy Framework.
 */

namespace Flexis\Proxy;

/**
 * Прокси-класс массива.
 */
class ArrayProxy implements ProxyInterface, \Countable, \ArrayAccess, \Iterator {
    /**
     * Источник данных.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Конструктор класса.
     *
     * @param  array  $data  Массив для доступа через прокси.
     */
    public function __construct(array &$data) {
        $this->data = &$data;
    }

    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param  mixed   $offset  Ключ для проверки.
     *
     * @return boolean
     */
    public function offsetExists(mixed $offset): bool {
        return \array_key_exists($offset, $this->data);
    }

    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param mixed $offset Ключ для чтения.
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->data[$offset] ?? null;
    }

    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param  mixed   $offset Ключ для записи.
     * @param  mixed   $value  Значение для записи.
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->data[$offset] = $value;
    }

    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param  mixed   $offset  Ключ для удаления.
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void {
        unset($this->data[$offset]);
    }

    /**
     * Реализация интерфейса Countable.
     *
     * @return int
     */
    public function count(): int {
        return \count($this->data);
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return mixed
     */
    public function current(): mixed {
        $key = key($this->data);

        return $this->offsetGet($key);
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return int|null|string
     */
    public function key():  int|null|string {
        return key($this->data);
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return void
     */
    public function next(): void {
        next($this->data);
    }

    /**
     *Реализация интерфейса Iterator.
     *
     * @return void
     */
    public function rewind(): void {
        reset($this->data);
    }

    /**
     * Реализация интерфейса Iterator.
     *
     * @return boolean
     */
    public function valid(): bool {
        return key($this->data) !== null;
    }
}
