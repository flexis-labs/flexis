<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Session\SessionInterface;

/**
 * Субинтерфейс приложения, определяющий класс веб-приложения, поддерживающий сеансы.
 */
interface SessionAwareWebApplicationInterface extends WebApplicationInterface {
    /**
     * Метод для получения объекта сеанса приложения.
     *
     * @return  SessionInterface  Объект сеанса.
     */
    public function getSession(): SessionInterface;

    /**
     * При необходимости устанавливает сеанс, который будет использовать приложение.
     *
     * @param   SessionInterface  $session  Объект сеанса.
     *
     * @return  $this
     */
    public function setSession(SessionInterface $session): self;

    /**
     * Проверяет наличие токена формы в запросе.
     *
     * @param   string  $method  Метод запроса, в котором нужно искать ключ токена.
     *
     * @return  boolean
     */
    public function checkToken(string $method = 'post'): bool;

    /**
     * Метод определения хеша для имен переменных защиты от подделки.
     *
     * @param   boolean  $forceNew  Если true, принудительно создать новый токен.
     *
     * @return  string  Хэшированное имя переменной.
     */
    public function getFormToken(bool $forceNew = false): string;
}
