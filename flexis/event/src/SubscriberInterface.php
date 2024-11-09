<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Интерфейс для подписчиков событий.
 */
interface SubscriberInterface {
    /**
     * Возвращает массив событий, которые будут прослушивать этот подписчик.
     *
     * Ключи массива — это имена событий, а значение может быть:
     *
     *  - Имя метода для вызова (приоритет по умолчанию равен 0)
     *  - Массив, состоящий из имени метода для вызова и приоритета.
     *
     * Например:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *
     * @return array
     */
    public static function getSubscribedEvents(): array;
}
