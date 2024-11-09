<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

use InvalidArgumentException;

/**
 * Класс события по умолчанию.
 */
class Event extends AbstractEvent {
    /**
     * Добавляет аргумент события, только если он не существует.
     *
     * @param   string  $name   Имя аргумента.
     * @param   mixed   $value  Значение аргумента.
     *
     * @return  $this
     */
    public function addArgument(string $name, mixed $value): static {
        if (!isset($this->arguments[$name])) {
            $this->arguments[$name] = $value;
        }

        return $this;
    }

    /**
     * Добавляет аргумент в событие.
     *
     * @param   string  $name   Имя аргумента.
     * @param   mixed   $value  Значение.
     *
     * @return  $this
     */
    public function setArgument(string $name, mixed $value): static {
        $this->arguments[$name] = $value;

        return $this;
    }

    /**
     * Удаляет аргумент события.
     *
     * @param string $name  Имя аргумента.
     *
     * @return  mixed  Старое значение аргумента или null, если оно не существует.
     */
    public function removeArgument(string $name): mixed {
        $return = null;

        if (isset($this->arguments[$name])) {
            $return = $this->arguments[$name];
            unset($this->arguments[$name]);
        }

        return $return;
    }

    /**
     * Очищает все аргументы события.
     *
     * @return  array  Старые аргументы.
     */
    public function clearArguments(): array {
        $arguments       = $this->arguments;
        $this->arguments = [];

        return $arguments;
    }

    /**
     * Устанавливает значение аргумента события.
     *
     * @param   string  $offset   Имя аргумента.
     * @param   mixed   $value    Значение аргумента.
     *
     * @return  void
     * @throws  InvalidArgumentException  Если имя аргумента равно null.
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, mixed $value): void {
        if ($offset === null) {
            throw new InvalidArgumentException('Имя аргумента не может быть null.');
        }

        $this->setArgument($offset, $value);
    }

    /**
     * Удаляет аргумент события.
     *
     * @param   string  $offset  Имя аргумента.
     *
     * @return  void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset): void {
        $this->removeArgument($offset);
    }
}
