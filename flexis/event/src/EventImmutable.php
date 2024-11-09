<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

use BadMethodCallException;
use ReturnTypeWillChange;

/**
 * Реализация неизменяемого события.
 * Неизменяемое событие не может быть изменено после создания экземпляра:
 *
 * - его распространение не может быть остановлено
 * - его аргументы не могут быть изменены
 *
 * Вы можете захотеть использовать это событие, если хотите убедиться, что
 * слушатели не будут манипулировать им.
 */
final class EventImmutable extends AbstractEvent {
    /**
     * Флаг, показывающий, был ли уже вызван конструктор.
     *
     * @var  boolean
     */
    private bool $constructed = false;

    /**
     * Конструктор.
     *
     * @param   string  $name       Название события.
     * @param   array   $arguments  Аргументы события.
     *
     * @throws  BadMethodCallException
     */
    public function __construct(string $name, array $arguments = []) {
        if ($this->constructed) {
            throw new BadMethodCallException(
                sprintf('Не удается восстановить неизменяемый %s события.', $this->name)
            );
        }

        $this->constructed = true;

        parent::__construct($name, $arguments);
    }

    /**
     * Устанавливает значение аргумента event.
     *
     * @param   string  $offset   Имя аргумента.
     * @param   mixed   $value  Значение аргумента.
     *
     * @return  void
     * @throws  BadMethodCallException
     */
    #[ReturnTypeWillChange]
    public function offsetSet($offset, mixed $value): void {
        throw new BadMethodCallException(
            sprintf(
                'Не удается задать аргумент %s для неизменяемого события %s.',
                $offset,
                $this->name
            )
        );
    }

    /**
     * Удаляет аргумент события.
     *
     * @param   string  $offset  Имя аргумента.
     *
     * @return  void
     *
     * @throws  BadMethodCallException
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($offset): void {
        throw new BadMethodCallException(
            sprintf(
                'Не удается удалить аргумент %s из неизменяемого события %s.',
                $offset,
                $this->name
            )
        );
    }
}
