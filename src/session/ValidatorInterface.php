<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Интерфейс для проверки части сессии.
 */
interface ValidatorInterface {
    /**
     * Подтверждает сессию.
     *
     * @param   boolean  $restart  Флаг, следует ли перезапустить сессию.
     *
     * @return  void
     * @throws  Exception\InvalidSessionException
     */
    public function validate(bool $restart = false): void;
}
