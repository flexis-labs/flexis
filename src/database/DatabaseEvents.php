<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Класс, определяющий события, отправляемые API базы данных.
 */
final class DatabaseEvents {
    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}

    /**
     * Событие базы данных, которое отправляется после открытия соединения с сервером базы данных.
     *
     * Слушатели этого события получают объект Flexis\Database\Event\ConnectionEvent.
     *
     * @var    string
     */
    public const string POST_CONNECT = 'onAfterConnect';

    /**
     * Событие базы данных, которое отправляется после закрытия соединения с сервером базы данных.
     *
     * Слушатели этого события получают объект Flexis\Database\Event\ConnectionEvent.
     *
     * @var    string
     */
    public const string POST_DISCONNECT = 'onAfterDisconnect';
}
