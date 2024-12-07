<?php

/**
 * Часть пакета Flexis Proxy Framework.
 */

namespace Flexis\Proxy;

/**
 * Объектный прокси-класс только для чтения.
 * Класс предоставляет функцию «только для чтения» для объекта, включая его дочерние элементы.
 */
class ObjectReadOnlyProxy extends ObjectProxy implements ReadOnlyProxyInterface {
    /**
     * Реализация чтения из объекта.
     *
     * @param mixed $key  Ключ для чтения.
     *
     * @return mixed
     */
    public function __get(mixed $key): mixed {
        $value = $this->data->$key ?? null;

        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if (\is_object($value)) {
            return new static($value);
        }

        if (\is_array($value)) {
            return new ArrayReadOnlyProxy($value);
        }

        return $value;
    }

    /**
     * Реализация записи в объект
     *
     * @param mixed $key    Ключ для записи.
     * @param mixed $value  Значение для записи.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function __set(mixed $key, mixed $value): void {
        throw new \RuntimeException(sprintf('ObjectReadOnlyProxy: попытка изменить элемент, доступный только для чтения, ключ "%s"', $key));
    }

    /**
     * Реализация интерфейса Iterator
     *
     * @return mixed
     */
    public function current(): mixed {
        $value = $this->iterator->current();

        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if (\is_object($value)) {
            return new static($value);
        }

        if (\is_array($value)) {
            return new ArrayReadOnlyProxy($value);
        }

        return $value;
    }
}
