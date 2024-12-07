<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

use Flexis\Event\Event;

/**
 * Класс, представляющий событие сессии.
 */
class SessionEvent extends Event {
    /**
     * Объект SessionInterface для этого события.
     *
     * @var    SessionInterface
     */
    private SessionInterface $session;

    /**
     * Конструктор.
     *
     * @param   string            $name     Название события.
     * @param   SessionInterface  $session  Объект SessionInterface для этого события.
     */
    public function __construct(string $name, SessionInterface $session) {
        parent::__construct($name);

        $this->session = $session;
    }

    /**
     * Возвращает объект SessionInterface, прикрепленный к этому событию.
     *
     * @return  SessionInterface
     */
    public function getSession(): SessionInterface {
        return $this->session;
    }
}
