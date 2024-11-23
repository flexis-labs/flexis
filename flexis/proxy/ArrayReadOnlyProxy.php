<?php

/**
 * Часть пакета Flexis Proxy Framework.
 */

namespace Flexis\Proxy;

/**
 * Прокси-класс массива, доступный только для чтения.
 * Класс предоставляет функцию только для чтения для массива Array, включая его дочерние элементы.
 */
class ArrayReadOnlyProxy extends ArrayProxy implements ReadOnlyProxyInterface {
    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param mixed $offset Ключ для чтения.
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed {
        $value = $this->data[$offset] ?? null;

        if (\is_scalar($value) || $value === null) {
            return $value;
        }

        if (\is_array($value)) {
            $value = new static($value);
        } elseif (\is_object($value)) {
            $value = new ObjectReadOnlyProxy($value);
        }

        return $value;
    }

    /**
     * Реализация интерфейса ArrayAccess.
     *
     * @param  mixed   $offset Ключ для установки.
     * @param  mixed   $value  Значение для установки.
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        throw new \RuntimeException(sprintf('ArrayReadOnlyProxy: попытка изменить элемент, доступный только для чтения, ключ "%s"', $offset));
    }
}
