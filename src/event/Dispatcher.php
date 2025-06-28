<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Реализация DispatcherInterface, поддерживающая прослушиватели с приоритетом.
 */
class Dispatcher implements DispatcherInterface {
    /**
     * Массив очередей ListenersPriorityQueue, проиндексированных по именам событий.
     *
     * @var    ListenersPriorityQueue[]
     */
    protected array $listeners = [];

    /**
     * Прикрепляет прослушиватель к событию.
     *
     * @param   string    $eventName  Событие, которое нужно прослушать.
     * @param   callable  $callback   Вызываемая функция
     * @param   integer   $priority   Приоритет выполнения $callback
     *
     * @return  boolean
     */
    public function addListener(string $eventName, $callback, int $priority = 0): bool {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = new ListenersPriorityQueue();
        }

        $this->listeners[$eventName]->add($callback, $priority);

        return true;
    }

    /**
     * Возвращает приоритет данного прослушивателя для данного события.
     *
     * @param string $eventName  Событие, которое нужно прослушать.
     * @param   callable  $callback   Вызываемая функция
     *
     * @return  mixed  Приоритет прослушивателя или значение NULL, если прослушиватель не существует.
     */
    public function getListenerPriority(string $eventName, $callback): mixed {
        if (isset($this->listeners[$eventName])) {
            return $this->listeners[$eventName]->getPriority($callback);
        }

        return null;
    }

    /**
     * Регистрирует слушателей на данное событие.
     *
     * @param   string|null  $event  Событие для получения прослушивателей или значение null для получения всех прослушивателей.
     *
     * @return  callable[]  Массив зарегистрированных слушателей, отсортированный по их приоритетам.
     */
    public function getListeners(?string $event = null): array {
        if ($event !== null) {
            if (isset($this->listeners[$event])) {
                return $this->listeners[$event]->getAll();
            }

            return [];
        }

        $dispatcherListeners = [];

        foreach ($this->listeners as $registeredEvent => $listeners) {
            $dispatcherListeners[$registeredEvent] = $listeners->getAll();
        }

        return $dispatcherListeners;
    }

    /**
     * Сообщает, был ли добавлен данный прослушиватель.
     *
     * Если указано событие, оно сообщит, зарегистрирован ли прослушиватель для этого события.
     *
     * @param   callable  $callback   Вызываемый объект для проверки прослушивает событие.
     * @param   ?string   $eventName  Необязательное имя события для проверки прослушивателя подписано.
     *
     * @return  boolean  True, если прослушиватель зарегистрирован, иначе — false.
     */
    public function hasListener(callable $callback, ?string $eventName = null): bool {
        if ($eventName) {
            if (isset($this->listeners[$eventName])) {
                return $this->listeners[$eventName]->has($callback);
            }
        } else {
            foreach ($this->listeners as $queue) {
                if ($queue->has($callback)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Удаляет прослушиватель событий из указанного события.
     *
     * @param   string    $eventName  Событие, из которого нужно удалить прослушиватель.
     * @param   callable  $listener   Слушатель, который нужно удалить.
     *
     * @return  void
     */
    public function removeListener(string $eventName, callable $listener): void {
        if (isset($this->listeners[$eventName])) {
            $this->listeners[$eventName]->remove($listener);
        }
    }

    /**
     * Очищает прослушиватели в этом диспетчере.
     *
     * Если указано событие, прослушиватели будут очищены только для этого события.
     *
     * @param string|null $event  Название события.
     *
     * @return  $this
     */
    public function clearListeners(?string $event = null): static {
        if ($event) {
            if (isset($this->listeners[$event])) {
                unset($this->listeners[$event]);
            }
        } else {
            $this->listeners = [];
        }

        return $this;
    }

    /**
     * Подсчитывает количество зарегистрированных слушателей данного события.
     *
     * @param string $event  Название события.
     *
     * @return  integer
     */
    public function countListeners(string $event): int {
        return isset($this->listeners[$event]) ? \count($this->listeners[$event]) : 0;
    }

    /**
     * Добавляет подписчика на события.
     *
     * @param   SubscriberInterface  $subscriber  Подписчик.
     *
     * @return  void
     */
    public function addSubscriber(SubscriberInterface $subscriber): void {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (\is_array($params)) {
                $this->addListener($eventName, [$subscriber, $params[0]], $params[1] ?? Priority::NORMAL);
            } else {
                $this->addListener($eventName, [$subscriber, $params]);
            }
        }
    }

    /**
     * Удаляет подписчика событий.
     *
     * @param   SubscriberInterface  $subscriber  Подписчик.
     *
     * @return  void
     */
    public function removeSubscriber(SubscriberInterface $subscriber): void {
        foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
            if (\is_array($params)) {
                $this->removeListener($eventName, [$subscriber, $params[0]]);
            } else {
                $this->removeListener($eventName, [$subscriber, $params]);
            }
        }
    }

    /**
     * Отправляет событие всем зарегистрированным прослушивателям.
     *
     * @param   EventInterface|string   $event  Событие, которое передается обработчикам/прослушивателям событий.
     *
     * @return  EventInterface
     */
    public function dispatch(EventInterface $event): EventInterface {
        if (isset($this->listeners[$event->getName()])) {
            foreach ($this->listeners[$event->getName()] as $listener) {
                if ($event->isStopped()) {
                    return $event;
                }

                $listener($event);
            }
        }

        return $event;
    }
}
