<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication;

/**
 * Интерфейс стратегии аутентификации Flexis Framework.
 */
interface AuthenticationStrategyInterface {
    /**
     * Попытка аутентификации.
     *
     * @return  string|boolean  Строка, содержащая логин, если аутентификация прошла успешно, иначе — false.
     */
    public function authenticate(): string|bool;

    /**
     * Возвращает последний результат аутентификации.
     *
     * @return  integer  Целое число из констант класса аутентификации с результатом аутентификации.
     */
    public function getResult(): int;
}
