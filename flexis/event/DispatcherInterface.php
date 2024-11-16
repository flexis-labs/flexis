<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Интерфейс для диспетчеров событий.
 */
interface DispatcherInterface {
    /**
     * Прикрепляет прослушиватель к событию
     *
     * @param   string    $eventName  Событие, которое нужно прослушать.
     * @param   callable  $callback   Вызываемая функция.
     * @param   integer   $priority   Приоритет, с которым выполнялся $callback.
     *
     * @return  boolean
     */
    public function addListener(string $eventName, callable $callback, int $priority = 0): bool;

    /**
     * Очищает прослушиватели в этом диспетчере.
     *
     * Если указано событие, прослушиватели будут очищены только для этого события.
     *
     * @param string|null $event  Название события.
     *
     * @return  $this
     */
    public function clearListeners(string $event = null): static;

    /**
     * Подсчитывает количество зарегистрированных слушателей данного события.
     *
     * @param string $event  Название события.
     *
     * @return  integer
     */
    public function countListeners(string $event): int;

    /**
     * Регистрирует слушателей на данное событие.
     *
     * @param   string|null  $event  Событие для получения прослушивателей или значение null для получения всех прослушивателей.
     *
     * @return  callable[]  Массив зарегистрированных слушателей, отсортированный по их приоритетам.
     */
    public function getListeners(?string $event = null): array;

    /**
     * Сообщает, был ли добавлен данный прослушиватель.
     *
     * Если указано событие, оно сообщит, зарегистрирован ли прослушиватель для этого события.
     *
     * @param   callable     $callback   Вызываемый объект для проверки прослушивает событие.
     * @param   string|null  $eventName  Необязательное имя события для проверки прослушивателя подписано.
     *
     * @return  boolean  True, если прослушиватель зарегистрирован, иначе — false.
     */
    public function hasListener(callable $callback, ?string $eventName = null): bool;

    /**
     * Удаляет прослушиватель событий из указанного события.
     *
     * @param   string    $eventName  Событие, из которого нужно удалить прослушиватель.
     * @param   callable  $listener   Слушатель, который нужно удалить.
     *
     * @return  void
     */
    public function removeListener(string $eventName, callable $listener): void;

    /**
     * Добавляет подписчика на события.
     *
     * @param   SubscriberInterface  $subscriber  Подписчик.
     *
     * @return  void
     */
    public function addSubscriber(SubscriberInterface $subscriber): void;

    /**
     * Удаляет подписчика событий.
     *
     * @param   SubscriberInterface  $subscriber  Подписчик.
     *
     * @return  void
     */
    public function removeSubscriber(SubscriberInterface $subscriber): void;
}
