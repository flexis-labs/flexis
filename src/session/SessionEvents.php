<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Класс, определяющий события, отправляемые API сессии.
 */
final class SessionEvents {
    /**
     * Частный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}

    /**
     * Событие сессии, которое отправляется после запуска сессии.
     *
     * Слушатели этого события получают объект Flexis\Session\SessionEvent.
     *
     * @var    string
     */
    public const string START = 'session.start';

    /**
     * Событие сессии, которое отправляется после перезапуска сессии.
     *
     * Слушатели этого события получают объект Flexis\Session\SessionEvent.
     *
     * @var    string
     */
    public const string RESTART = 'session.restart';
}
