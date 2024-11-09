<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

/**
 * Интерфейс для событий.
 * Событие имеет имя и его распространение можно остановить.
 */
interface EventInterface {
    /**
     * Получает значение аргумента события.
     *
     * @param   string  $name     Имя аргумента.
     * @param   mixed   $default  Значение по умолчанию, если не найдено.
     *
     * @return  mixed  Значение аргумента или значение по умолчанию.
     */
    public function getArgument($name, $default = null);

    /**
     * Получает название события.
     *
     * @return  string  Название события.
     */
    public function getName();

    /**
     * Сообщает, остановлено ли распространение события.
     *
     * @return  boolean  True, если остановлено, и false в противном случае.
     */
    public function isStopped();

    /**
     * Останавливает распространение события среди дальнейших прослушивателей событий.
     *
     * @return  void
     */
    public function stopPropagation(): void;
}
