<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Validator;

use Flexis\Input\Input;
use Flexis\Session\SessionInterface;
use Flexis\Session\ValidatorInterface;

/**
 * Интерфейс для проверки части сессии.
 */
class ForwardedValidator implements ValidatorInterface {
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
     * @param   Input             $input    Входной объект.
     * @param   SessionInterface  $session  DispatcherInterface для используемого сессии.
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
     */
    public function validate(bool $restart = false): void {
        if ($restart) {
            $this->session->set('session.client.forwarded', null);
        }

        $xForwardedFor = $this->input->server->getString('HTTP_X_FORWARDED_FOR', '');

        if (!empty($xForwardedFor) && filter_var($xForwardedFor, FILTER_VALIDATE_IP) !== false) {
            $this->session->set('session.client.forwarded', $xForwardedFor);
        }
    }
}
