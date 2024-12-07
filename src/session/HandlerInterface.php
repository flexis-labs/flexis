<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Интерфейс, определяющий обработчики сессии Flexis.
 */
interface HandlerInterface extends \SessionHandlerInterface {
    /**
     * Проверяет, доступен ли HandlerInterface.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public static function isSupported(): bool;
}
