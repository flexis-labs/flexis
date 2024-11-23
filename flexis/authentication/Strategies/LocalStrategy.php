<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Strategies;

use Flexis\Authentication\AbstractUsernamePasswordAuthenticationStrategy;
use Flexis\Authentication\Authentication;
use Flexis\Authentication\Password\HandlerInterface;
use Flexis\Input\Input;

/**
 * Класс аутентификации локальной стратегии Flexis Framework.
 */
class LocalStrategy extends AbstractUsernamePasswordAuthenticationStrategy {
    /**
     * Хранилище учетных данных.
     *
     * @var    array
     */
    private array $credentialStore;

    /**
     * Объект ввода
     *
     * @var    Input
     */
    private Input $input;

    /**
     * Конструктор стратегий.
     *
     * @param   Input                   $input            Входной объект, из которого можно получить учетные данные запроса.
     * @param   array                   $credentialStore  Хэш имени пользователя и пары хешей.
     * @param   HandlerInterface|null   $passwordHandler  Обработчик паролей.
     */
    public function __construct(Input $input, array $credentialStore = [], ?HandlerInterface $passwordHandler = null) {
        parent::__construct($passwordHandler);

        $this->credentialStore = $credentialStore;
        $this->input           = $input;
    }

    /**
     * Попытка аутентифицировать пару имени пользователя и пароля.
     *
     * @return  string|boolean  Строка, содержащая логин, если аутентификация прошла успешно, иначе — false.
     */
    public function authenticate(): string|bool {
        $username = $this->input->get('username', false, 'username');
        $password = $this->input->get('password', false, 'raw');

        if (!$username || !$password) {
            $this->status = Authentication::NO_CREDENTIALS;

            return false;
        }

        return $this->doAuthenticate($username, $password);
    }

    /**
     * Возвращает хешированный пароль для указанного пользователя.
     *
     * @param   string  $username  Логин для поиска.
     *
     * @return  string|boolean  Хешированный пароль в случае успеха или логическое значение false в случае неудачи.
     */
    protected function getHashedPassword(string $username): string|bool {
        return $this->credentialStore[$username] ?? false;
    }
}
