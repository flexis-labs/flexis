<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Input\Input;
use Flexis\Session\SessionInterface;

/**
 * Трейт, который помогает реализовать Flexis\Application\SessionAwareWebApplicationInterface в классе веб-приложения.
 */
trait SessionAwareWebApplicationTrait {
    /**
     * Объект сеанса приложения.
     *
     * @var    SessionInterface|null
     */
    protected ?SessionInterface $session = null;

    /**
     * Метод для получения объекта ввода приложения.
     *
     * @return  Input
     */
    abstract public function getInput(): Input;

    /**
     * Метод для получения объекта сеанса приложения.
     *
     * @return  SessionInterface  Объект сеанса.
     */
    public function getSession(): SessionInterface {
        if ($this->session === null) {
            throw new \RuntimeException(\sprintf('Объект %s не установлен.', SessionInterface::class));
        }

        return $this->session;
    }

    /**
     * При необходимости устанавливает сеанс, который будет использовать приложение.
     *
     * @param SessionInterface $session Объект сеанса.
     *
     * @return SessionAwareWebApplicationTrait|WebApplication
     */
    public function setSession(SessionInterface $session): self {
        $this->session = $session;

        return $this;
    }

    /**
     * Проверяет наличие токена формы в запросе.
     *
     * @param   string  $method  Метод запроса, в котором нужно искать ключ токена.
     *
     * @return  boolean
     */
    public function checkToken(string $method = 'post'): bool {
        $token = $this->getFormToken();
        $requestToken = $this->getInput()->server->get(
            'HTTP_X_CSRF_TOKEN',
            $this->getInput()->$method->get($token, '', 'alnum'),
            'alnum'
        );

        if (!$requestToken) {
            return false;
        }

        return $this->getSession()->hasToken($token);
    }

    /**
     * Метод определения хеша для имен переменных защиты от подделки.
     *
     * @param   boolean  $forceNew  Если true, принудительно создать новый токен.
     *
     * @return  string  Хэшированное имя переменной.
     */
    public function getFormToken(bool $forceNew = false): string {
        return $this->getSession()->getToken($forceNew);
    }
}
