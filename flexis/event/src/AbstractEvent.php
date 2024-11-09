<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

use ArrayAccess;
use Countable;
use Serializable;

/**
 * Реализация EventInterface.
 */
abstract class AbstractEvent implements EventInterface, ArrayAccess, Serializable, Countable {
    /**
     * Название события.
     *
     * @var    string
     */
    protected string $name;

    /**
     * Аргументы события.
     *
     * @var    array
     */
    protected array $arguments;

    /**
     * Флаг, позволяющий узнать, остановлено ли распространение события.
     *
     * @var    boolean
     */
    protected bool $stopped = false;

    /**
     * Конструктор.
     *
     * @param string $name       Название события.
     * @param   array   $arguments  Аргументы события.
     */
    public function __construct(string $name, array $arguments = []) {
        $this->name      = $name;
        $this->arguments = $arguments;
    }

    /**
     * Получает название события.
     *
     * @return  string  Название события.
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Получает значение аргумента события.
     *
     * @param   string  $name     Имя аргумента.
     * @param   mixed   $default  Значение по умолчанию, если не найдено.
     *
     * @return  mixed  Значение аргумента или значение по умолчанию.
     */
    public function getArgument($name, $default = null): mixed {
        if (isset($this->arguments[$name])) {
            return $this->arguments[$name];
        }

        return $default;
    }

    /**
     * Сообщает, существует ли данный аргумент события.
     *
     * @param string $name  Имя аргумента.
     *
     * @return  boolean  True, если оно существует, иначе false.
     */
    public function hasArgument(string $name): bool {
        return isset($this->arguments[$name]);
    }

    /**
     * Получает все аргументы события.
     *
     * @return  array  Ассоциативный массив имен аргументов как ключей и их значений как значений.
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    /**
     * Сообщает, остановлено ли распространение события.
     *
     * @return  boolean  True, если остановлено, и false в противном случае.
     */
    public function isStopped(): bool {
        return $this->stopped === true;
    }

    /**
     * Останавливает распространение события среди дальнейших прослушивателей событий.
     *
     * @return  void
     */
    public function stopPropagation(): void {
        $this->stopped = true;
    }

    /**
     * Посчитывает количество аргументов.
     *
     * @return  integer  Количество аргументов.
     */
    #[\ReturnTypeWillChange]
    public function count(): int {
        return \count($this->arguments);
    }

    /**
     * Сериализует событие.
     *
     * @return  string  Сериализованное событие.
     */
    public function serialize(): string {
        return serialize($this->__serialize());
    }

    /**
     * Сериализует событие.
     *
     * @return  array  Данные, подлежащие сериализации
     */
    public function __serialize() {
        return [
            'name'      => $this->name,
            'arguments' => $this->arguments,
            'stopped'   => $this->stopped,
        ];
    }

    /**
     * Десериализовать событие.
     *
     * @param   string  $serialized  Сериализованное событие.
     *
     * @return  void
     */
    public function unserialize(string $serialized): void {
        $this->__unserialize(unserialize($serialized));
    }

    /**
     * Десериализовать событие.
     *
     * @param   array  $data  Сериализованное событие.
     *
     * @return  void
     */
    public function __unserialize(array $data): void {
        $this->name      = $data['name'];
        $this->arguments = $data['arguments'];
        $this->stopped   = $data['stopped'];
    }

    /**
     * Сообщает, существует ли данный аргумент события.
     *
     * @param mixed $offset  Имя аргумента.
     *
     * @return  boolean  True, если оно существует, иначе false.
     */
    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool {
        return $this->hasArgument($offset);
    }

    /**
     * Получает значение аргумента события.
     *
     * @param   string  $offset  Имя аргумента.
     *
     * @return  mixed  Значение аргумента или значение NULL, если оно не существует.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): mixed {
        return $this->getArgument($offset);
    }
}
