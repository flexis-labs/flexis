<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * Класс, содержащий очередь приоритетов внутренних прослушивателей, которую можно повторять несколько раз.
 * @internal
 */
final class ListenersPriorityQueue implements IteratorAggregate, Countable {
    /**
     * Слушатели события.
     *
     * @var    array
     */
    private array $listeners = [];

    /**
     * Добавляет прослушиватель с заданным приоритетом, только если он ещё не присутствует.
     *
     * @param   callable  $callback  Вызываемая функция, действующая как прослушиватель событий.
     * @param   integer   $priority  Приоритет слушателя.
     *
     * @return  $this
     */
    public function add(callable $callback, int $priority): self {
        $this->listeners[$priority][] = $callback;

        return $this;
    }

    /**
     * Удаляет прослушиватель из очереди.
     *
     * @param   callable  $callback  Вызываемая функция, действующая как прослушиватель событий.
     *
     * @return  $this
     */
    public function remove(callable $callback): self {
        foreach ($this->listeners as $priority => $listeners) {
            if (($key = array_search($callback, $listeners, true)) !== false) {
                unset($this->listeners[$priority][$key]);
            }
        }

        return $this;
    }

    /**
     * Сообщает, существует ли прослушиватель в очереди.
     *
     * @param   callable  $callback  Вызываемая функция, действующая как прослушиватель событий.
     *
     * @return  boolean  True, если оно существует, иначе false.
     */
    public function has(callable $callback): bool {
        foreach ($this->listeners as $priority => $listeners) {
            if (in_array($callback, $listeners, true) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает приоритет данного слушателя.
     *
     * @param   callable  $callback  Вызываемая функция, действующая как прослушиватель событий.
     * @param mixed|null  $default   Значение по умолчанию, которое возвращается, если прослушиватель не существует.
     *
     * @return  mixed  Приоритет прослушивателя, если он существует, или указанное значение по умолчанию.
     */
    public function getPriority(callable $callback, mixed $default = null): mixed {
        foreach ($this->listeners as $priority => $listeners) {
            if (in_array($callback, $listeners, true) !== false) {
                return $priority;
            }
        }

        return $default;
    }

    /**
     * Возвращает все прослушиватели, содержащиеся в этой очереди, отсортированные по их приоритету.
     *
     * @return  callable[]  Массив слушателей.
     */
    public function getAll(): array {
        if (empty($this->listeners)) {
            return [];
        }

        krsort($this->listeners);

        return \call_user_func_array('array_merge', $this->listeners);
    }

    /**
     * Возвращает приоритетную очередь.
     *
     * @return  ArrayIterator
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->getAll());
    }

    /**
     * Подсчитывает количество слушателей в очереди.
     *
     * @return  integer  Количество слушателей в очереди.
     */
    #[\ReturnTypeWillChange]
    public function count(): int {
        $count = 0;

        foreach ($this->listeners as $priority) {
            $count += \count($priority);
        }

        return $count;
    }
}
