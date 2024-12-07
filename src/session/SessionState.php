<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Класс, определяющий различные состояния сессии.
 */
final class SessionState {
    /**
     * Частный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}

    /**
     * Состояние, указывающее, что сессия активна.
     *
     * Экземпляр SessionInterface должен находиться в этом состоянии после начала сессии.
     *
     * @var    string
     */
    public const string ACTIVE = 'active';

    /**
     * Состояние, указывающее, что сессия закрыта.
     *
     * A Экземпляр SessionInterface должен находиться в этом состоянии после вызова метода close().
     *
     * @var    string
     */
    public const string CLOSED = 'closed';

    /**
     * Состояние, указывающее, что сессия уничтожена.
     *
     * Экземпляр SessionInterface должен находиться в этом состоянии после вызова метода destroy().
     *
     * @var    string
     */
    public const string DESTROYED = 'destroyed';

    /**
     * Состояние, указывающее, что сессия находится в состоянии ошибки.
     *
     * Экземпляр SessionInterface должен находиться в этом состоянии, если сессия не может быть проверена после запуска.
     *
     * @var    string
     */
    public const string ERROR = 'error';

    /**
     * Состояние, указывающее, что срок действия сессии истек.
     *
     * Экземпляр SessionInterface должен находиться в этом состоянии, если сессия прошла разрешенное время жизни.
     * Экземпляр SessionInterface может находиться в этом состоянии, если проверка токена сессии не удалась.
     *
     * @var    string
     */
    public const string EXPIRED = 'expired';

    /**
     * Состояние, указывающее, что сессия неактивна.
     *
     * Экземпляр SessionInterface должен начинаться в этом состоянии.
     *
     * @var    string
     */
    public const string INACTIVE = 'inactive';
}
