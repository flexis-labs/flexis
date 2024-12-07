<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication;

/**
 * Класс аутентификации Flexis Framework
 */
class Authentication {
    /**
     * Аутентификация прошла успешно.
     *
     * @var integer
     */
    public const int SUCCESS = 1;

    /**
     * Учетные данные были предоставлены, но они оказались недействительными.
     *
     * @var integer
     */
    public const int INVALID_CREDENTIALS = 2;

    /**
     * Учетные данные были предоставлены, но пользователь не существовал в хранилище учетных данных.
     *
     * @var integer
     */
    public const int NO_SUCH_USER = 3;

    /**
     * Никаких учетных данных не обнаружено.
     *
     * @var integer
     */
    public const int NO_CREDENTIALS = 4;

    /**
     * Были найдены частичные учетные данные, но они не были полными.
     *
     * @var integer
     */
    public const int INCOMPLETE_CREDENTIALS = 5;

    /**
     * Массив стратегий.
     *
     * @var    AuthenticationStrategyInterface[]
     */
    private array $strategies = [];

    /**
     * Массив результатов.
     *
     * @var    integer[]
     */
    private array $results = [];

    /**
     * Зарегистрируйте новую стратегию
     *
     * @param   string                           $strategyName  Имя, которое будет использоваться для стратегии.
     * @param   AuthenticationStrategyInterface  $strategy      Добавляемый объект стратегии аутентификации.
     *
     * @return  void
     */
    public function addStrategy(string $strategyName, AuthenticationStrategyInterface $strategy): void {
        $this->strategies[$strategyName] = $strategy;
    }

    /**
     * Выполнить аутентификацию
     *
     * @param   string[]  $strategies  Массив стратегий, которые стоит попробовать — пуст, чтобы попробовать все стратегии.
     *
     * @return  string|boolean  Строка, содержащая логин, если аутентификация прошла успешно, иначе — false.
     *
     * @throws  \RuntimeException
     */
    public function authenticate(array $strategies = []): string|bool {
        if (empty($strategies)) {
            $strategyObjects = $this->strategies;
        } else {
            $strategyObjects = [];

            foreach ($strategies as $strategy) {
                if (!isset($this->strategies[$strategy])) {
                    throw new \RuntimeException('Стратегия аутентификации не найдена');
                }

                $strategyObjects[$strategy] = $this->strategies[$strategy];
            }
        }

        if (empty($strategyObjects)) {
            throw new \RuntimeException('Стратегии не установлены');
        }

        /** @var AuthenticationStrategyInterface $strategyObject */
        foreach ($strategyObjects as $strategy => $strategyObject) {
            $username = $strategyObject->authenticate();

            $this->results[$strategy] = $strategyObject->getResult();

            if (\is_string($username)) {
                return $username;
            }
        }

        return false;
    }

    /**
     * Возвращает результаты аутентификации.
     *
     * Используйте это, если хотите получить более подробную информацию о результатах попыток аутентификации.
     *
     * @return  integer[]  Массив, содержащий результаты аутентификации.
     */
    public function getResults(): array {
        return $this->results;
    }
}
