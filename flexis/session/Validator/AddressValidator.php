<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Validator;

use Flexis\Input\Input;
use Flexis\Session\Exception\InvalidSessionException;
use Flexis\Session\SessionInterface;
use Flexis\Session\ValidatorInterface;
use Flexis\Utilities\IpHelper;

/**
 * Интерфейс для проверки части сессии/
 */
class AddressValidator implements ValidatorInterface {
    /**
     * Объект ввода.
     *
     * @var    Input
     */
    private Input $input;

    /**
     * Объект сессии.
     *
     * @var    SessionInterface
     */
    private SessionInterface $session;

    /**
     * Конструктор.
     *
     * @param   Input             $input    The input object.
     * @param   SessionInterface  $session  DispatcherInterface for the session to use.
     */
    public function __construct(Input $input, SessionInterface $session) {
        $this->input   = $input;
        $this->session = $session;
    }

    /**
     * Подтверждает сессию.
     *
     * @param   boolean  $restart  Флаг, следует ли перезапустить сессию.
     *
     * @return  void
     * @throws  InvalidSessionException
     */
    public function validate(bool $restart = false): void {
        if ($restart) {
            $this->session->set('session.client.address', null);
        }

        $remoteAddr = IpHelper::getIp();

        if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false) {
            $ip = $this->session->get('session.client.address');

            if ($ip === null) {
                $this->session->set('session.client.address', $remoteAddr);
            } elseif ($remoteAddr !== $ip) {
                throw new InvalidSessionException('Invalid client IP');
            }
        }
    }
}
