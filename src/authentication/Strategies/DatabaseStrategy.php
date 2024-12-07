<?php

/**
 * Часть пакета Flexis Authentication Framework.
 */

namespace Flexis\Authentication\Strategies;

use Flexis\Authentication\AbstractUsernamePasswordAuthenticationStrategy;
use Flexis\Authentication\Authentication;
use Flexis\Authentication\Password\HandlerInterface;
use Flexis\Database\DatabaseInterface;
use Flexis\Input\Input;

/**
 * Класс аутентификации стратегии базы данных Flexis Framework
 */
class DatabaseStrategy extends AbstractUsernamePasswordAuthenticationStrategy {
    /**
     * Объект DatabaseInterface
     *
     * @var    DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Варианты подключения к базе данных
     *
     * @var    array
     */
    private array $dbOptions;

    /**
     * Объект ввода
     *
     * @var    Input
     */
    private Input $input;

    /**
     * Конструктор стратегий
     *
     * @param   Input                   $input            Входной объект, из которого можно получить учетные данные запроса.
     * @param   DatabaseInterface       $database         DatabaseDriver для получения учетных данных пользователя.
     * @param   array                   $options          Дополнительный массив параметров для настройки подключения к хранилищу учетных данных.
     * @param   HandlerInterface|null   $passwordHandler  Обработчик паролей.
     */
    public function __construct(
        Input $input,
        DatabaseInterface $database,
        array $options = [],
        ?HandlerInterface $passwordHandler = null
    ) {
        parent::__construct($passwordHandler);

        $this->input = $input;
        $this->db    = $database;

        $options['database_table']  = $options['database_table'] ?? '#__users';
        $options['username_column'] = $options['username_column'] ?? 'username';
        $options['password_column'] = $options['password_column'] ?? 'password';

        $this->dbOptions = $options;
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
        try {
            $password = $this->db->setQuery(
                $this->db->createQuery()
                    ->select($this->db->quoteName($this->dbOptions['password_column']))
                    ->from($this->db->quoteName($this->dbOptions['database_table']))
                    ->where($this->db->quoteName($this->dbOptions['username_column']) . ' = ?')
                    ->bind(1, $username)
            )->loadResult();
        } catch (\RuntimeException $exception) {
            return false;
        }

        if (!$password) {
            return false;
        }

        return $password;
    }

    /**
     * Определяет тип ввода: логин, email или телефон.
     *
     * @param string $input Входная строка для анализа.
     *
     * @return string   Возвращает тип ввода: 'username', 'email', 'phone'.
     */
    protected function determineInputType(string $input): string {
        // Удалим пробелы в начале и конце строки
        $input = trim($input);

        // Проверим, является ли это email
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        // Проверим, является ли это телефон (считаем телефонный номер, если он содержит только цифры, пробелы, тире, скобки и символ +)
        if (preg_match('/^\+?[0-9\s\-\(\)]+$/', $input)) {
            return 'phone';
        }

        // Если не email и не телефон, то считаем, что это логин
        return 'username';
    }
}
