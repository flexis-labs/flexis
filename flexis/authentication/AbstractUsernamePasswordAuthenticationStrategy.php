<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication;

use Flexis\Authentication\Password\BCryptHandler;
use Flexis\Authentication\Password\HandlerInterface;

/**
 * Абстрактная стратегия аутентификации для аутентификации на основе имени пользователя и пароля.
 */
abstract class AbstractUsernamePasswordAuthenticationStrategy implements AuthenticationStrategyInterface {
    /**
     * Обработчик пароля для проверки пароля.
     *
     * @var    HandlerInterface
     */
    protected HandlerInterface $passwordHandler;

    /**
     * Последний статус аутентификации.
     *
     * @var    integer
     */
    protected int $status;

    /**
     * Конструктор.
     *
     * @param   HandlerInterface|null  $passwordHandler  Обработчик паролей.
     */
    public function __construct(?HandlerInterface $passwordHandler = null) {
        $this->passwordHandler = $passwordHandler ?: new BCryptHandler();
    }

    /**
     * Попытка аутентифицировать пару имени пользователя и пароля.
     *
     * @param   string  $username  Логин для аутентификации.
     * @param   string  $password  Пароль для попытки аутентификации.
     *
     * @return  string|boolean  Строка, содержащая логин, если аутентификация прошла успешно, иначе — false.
     */
    protected function doAuthenticate(string $username, string $password): string|bool {
        $hashedPassword = $this->getHashedPassword($username);

        if ($hashedPassword === false) {
            $this->status = Authentication::NO_SUCH_USER;

            return false;
        }

        if (!$this->verifyPassword($username, $password, $hashedPassword)) {
            $this->status = Authentication::INVALID_CREDENTIALS;

            return false;
        }

        $this->status = Authentication::SUCCESS;

        return $username;
    }

    /**
     * Возвращает хешированный пароль для указанного пользователя.
     *
     * @param   string  $username  Логин для поиска.
     *
     * @return  string|boolean  Хешированный пароль в случае успеха или логическое значение false в случае неудачи.
     */
    abstract protected function getHashedPassword(string $username): string|bool;

    /**
     * Возвращает статус последней попытки аутентификации.
     *
     * @return  integer  Результат константы класса аутентификации.
     */
    public function getResult(): int {
        return $this->status;
    }

    /**
     * Попытка проверить пару имени пользователя и пароля.
     *
     * @param   string  $username        Логин для аутентификации.
     * @param   string  $password        Пароль для попытки аутентификации.
     * @param   string  $hashedPassword  Хешированный пароль для попытки аутентификации.
     *
     * @return  boolean
     */
    protected function verifyPassword(string $username, string $password, string $hashedPassword): bool {
        return $this->passwordHandler->validatePassword($password, $hashedPassword);
    }
}
