<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pdo;

use Flexis\Database\DatabaseDriver;
use Flexis\Database\DatabaseEvents;
use Flexis\Database\Event\ConnectionEvent;
use Flexis\Database\Exception\ConnectionFailureException;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Database\Exception\UnsupportedAdapterException;
use Flexis\Database\StatementInterface;
use function str_contains;
use function str_replace;

/**
 * Класс драйвера базы данных Flexis Framework PDO
 *
 * @link   https://www.php.net/pdo
 */
abstract class PdoDriver extends DatabaseDriver {
    /**
     * Ресурс подключения к базе данных.
     *
     * @var    \PDO
     */
    protected $connection;

    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'pdo';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких, как имена таблиц, имена полей и т. д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках, иначе первый символ будет использоваться для открывающей кавычки, а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = "'";

    /**
     * Нулевое или нулевое представление отметки времени для драйвера базы данных.
     *
     * @var    string|null
     */
    protected ?string $nullDate = '0000-00-00 00:00:00';

    /**
     * Конструктор.
     *
     * @param   array  $options  Список опций, используемых для настройки соединения.
     *
     */
    public function __construct(array $options) {
        $options['driver']        = $options['driver'] ?? 'odbc';
        $options['dsn']           = $options['dsn'] ?? '';
        $options['host']          = $options['host'] ?? 'localhost';
        $options['database']      = $options['database'] ?? '';
        $options['user']          = $options['user'] ?? '';
        $options['port']          = isset($options['port']) ? (int) $options['port'] : null;
        $options['password']      = $options['password'] ?? '';
        $options['driverOptions'] = $options['driverOptions'] ?? [];
        $options['ssl']           = $options['ssl'] ?? [];
        $options['socket']        = str_contains($options['host'], 'unix:') ? str_replace('unix:', '', $options['host']) : null;

        if ($options['ssl'] !== []) {
            $options['ssl']['enable']             = $options['ssl']['enable'] ?? false;
            $options['ssl']['cipher']             = $options['ssl']['cipher'] ?? null;
            $options['ssl']['ca']                 = $options['ssl']['ca'] ?? null;
            $options['ssl']['capath']             = $options['ssl']['capath'] ?? null;
            $options['ssl']['key']                = $options['ssl']['key'] ?? null;
            $options['ssl']['cert']               = $options['ssl']['cert'] ?? null;
            $options['ssl']['verify_server_cert'] = $options['ssl']['verify_server_cert'] ?? null;
        }

        parent::__construct($options);
    }

    /**
     * Деструктор.
     *
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * При необходимости подключается к базе данных.
     *
     * @return  void  Возвращает void, если база данных подключена успешно.
     *
     * @throws  \RuntimeException
     */
    public function connect(): void {
        if ($this->connection) {
            return;
        }

        if (!static::isSupported()) {
            throw new UnsupportedAdapterException('Расширение PDO недоступно.', 1);
        }

        switch ($this->options['driver']) {
            case 'cubrid':
                $this->options['port'] = $this->options['port'] ?? 33000;

                $format = 'cubrid:host=#HOST#;port=#PORT#;dbname=#DBNAME#';

                $replace = ['#HOST#', '#PORT#', '#DBNAME#'];
                $with    = [$this->options['host'], $this->options['port'], $this->options['database']];

                break;

            case 'dblib':
                $this->options['port'] = $this->options['port'] ?? 1433;

                $format = 'dblib:host=#HOST#;port=#PORT#;dbname=#DBNAME#';

                $replace = ['#HOST#', '#PORT#', '#DBNAME#'];
                $with    = [$this->options['host'], $this->options['port'], $this->options['database']];

                break;

            case 'firebird':
                $this->options['port'] = $this->options['port'] ?? 3050;

                $format = 'firebird:dbname=#DBNAME#';

                $replace = ['#DBNAME#'];
                $with    = [$this->options['database']];

                break;

            case 'ibm':
                $this->options['port'] = $this->options['port'] ?? 56789;

                if (!empty($this->options['dsn'])) {
                    $format = 'ibm:DSN=#DSN#';

                    $replace = ['#DSN#'];
                    $with    = [$this->options['dsn']];
                } else {
                    $format = 'ibm:hostname=#HOST#;port=#PORT#;database=#DBNAME#';

                    $replace = ['#HOST#', '#PORT#', '#DBNAME#'];
                    $with    = [$this->options['host'], $this->options['port'], $this->options['database']];
                }

                break;

            case 'informix':
                $this->options['port']     = $this->options['port'] ?? 1526;
                $this->options['protocol'] = $this->options['protocol'] ?? 'onsoctcp';

                if (!empty($this->options['dsn'])) {
                    $format = 'informix:DSN=#DSN#';

                    $replace = ['#DSN#'];
                    $with    = [$this->options['dsn']];
                } else {
                    $format = 'informix:host=#HOST#;service=#PORT#;database=#DBNAME#;server=#SERVER#;protocol=#PROTOCOL#';

                    $replace = ['#HOST#', '#PORT#', '#DBNAME#', '#SERVER#', '#PROTOCOL#'];
                    $with    = [
                        $this->options['host'],
                        $this->options['port'],
                        $this->options['database'],
                        $this->options['server'],
                        $this->options['protocol'],
                    ];
                }

                break;

            case 'sybase':
            case 'mssql':
                $this->options['port'] = $this->options['port'] ?? 1433;

                $format = 'mssql:host=#HOST#;port=#PORT#;dbname=#DBNAME#';

                $replace = ['#HOST#', '#PORT#', '#DBNAME#'];
                $with    = [$this->options['host'], $this->options['port'], $this->options['database']];

                break;

            case 'mysql':
                $this->options['port'] = $this->options['port'] ?? 3306;

                if ($this->options['socket'] !== null) {
                    $format = 'mysql:unix_socket=#SOCKET#;dbname=#DBNAME#;charset=#CHARSET#';
                } else {
                    $format = 'mysql:host=#HOST#;port=#PORT#;dbname=#DBNAME#;charset=#CHARSET#';
                }

                $replace = ['#HOST#', '#PORT#', '#SOCKET#', '#DBNAME#', '#CHARSET#'];
                $with    = [
                    $this->options['host'],
                    $this->options['port'],
                    $this->options['socket'],
                    $this->options['database'],
                    $this->options['charset'],
                ];

                break;

            case 'oci':
                $this->options['port']    = $this->options['port'] ?? 1521;
                $this->options['charset'] = $this->options['charset'] ?? 'AL32UTF8';

                if (!empty($this->options['dsn'])) {
                    $format = 'oci:dbname=#DSN#';

                    $replace = ['#DSN#'];
                    $with    = [$this->options['dsn']];
                } else {
                    $format = 'oci:dbname=//#HOST#:#PORT#/#DBNAME#';

                    $replace = ['#HOST#', '#PORT#', '#DBNAME#'];
                    $with    = [$this->options['host'], $this->options['port'], $this->options['database']];
                }

                $format .= ';charset=' . $this->options['charset'];

                break;

            case 'odbc':
                $format = 'odbc:DSN=#DSN#;UID:#USER#;PWD=#PASSWORD#';

                $replace = ['#DSN#', '#USER#', '#PASSWORD#'];
                $with    = [$this->options['dsn'], $this->options['user'], $this->options['password']];

                break;

            case 'pgsql':
                $this->options['port'] = $this->options['port'] ?? 5432;

                if ($this->options['socket'] !== null) {
                    $format = 'pgsql:host=#SOCKET#;dbname=#DBNAME#';
                } else {
                    $format = 'pgsql:host=#HOST#;port=#PORT#;dbname=#DBNAME#';
                }

                $replace = ['#HOST#', '#PORT#', '#SOCKET#', '#DBNAME#'];
                $with    = [$this->options['host'], $this->options['port'], $this->options['socket'], $this->options['database']];

                if ($this->options['ssl'] !== [] && $this->options['ssl']['enable'] === true) {
                    if (isset($this->options['ssl']['verify_server_cert']) && $this->options['ssl']['verify_server_cert'] === true) {
                        $format .= ';sslmode=verify-full';
                    } else {
                        $format .= ';sslmode=require';
                    }

                    $sslKeysMapping = [
                        'cipher' => null,
                        'ca'     => 'sslrootcert',
                        'capath' => null,
                        'key'    => 'sslkey',
                        'cert'   => 'sslcert',
                    ];

                    foreach ($sslKeysMapping as $key => $value) {
                        if ($value !== null && $this->options['ssl'][$key] !== null) {
                            $format .= ';' . $value . '=' . $this->options['ssl'][$key];
                        }
                    }
                }

                break;

            case 'sqlite':
                if (isset($this->options['version']) && $this->options['version'] == 2) {
                    $format = 'sqlite2:#DBNAME#';
                } else {
                    $format = 'sqlite:#DBNAME#';
                }

                $replace = ['#DBNAME#'];
                $with    = [$this->options['database']];

                break;

            default:
                throw new UnsupportedAdapterException('Драйвер ' . $this->options['driver'] . ' не поддерживается.');
        }

        $connectionString = str_replace($replace, $with, $format);

        try {
            $this->connection = new \PDO(
                $connectionString,
                $this->options['user'],
                $this->options['password'],
                $this->options['driverOptions']
            );
        } catch (\PDOException $e) {
            throw new ConnectionFailureException('Не удалось подключиться к PDO: ' . $e->getMessage(), $e->getCode(), $e);
        }

        $this->setOption(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->dispatchEvent(new ConnectionEvent(DatabaseEvents::POST_CONNECT, $this));
    }

    /**
     * Метод экранирования строки для использования в инструкции SQL.
     *
     * @link http://www.orafaq.com/wiki/SQL_FAQ#How_does_one_escape_special_characters_when_writing_SQL_queries.3F Экранирующая ссылка Oracle
     * @link http://www.sqlite.org/faq.html#q14 Примечания к экранированию SQLite
     *
     * Тело метода реализовано в Zend Framework.
     *
     * @note Использование объектов запроса со связанными переменными предпочтительнее, чем приведенное ниже.
     *
     * @param mixed   $text  Строка, которую нужно экранировать.
     * @param boolean $extra Неиспользуемый необязательный параметр для обеспечения дополнительного экранирования.
     *
     * @return  string  Экранированная строка.
     */
    public function escape(mixed $text, bool $extra = false): string {
        if (\is_int($text)) {
            return $text;
        }

        if (\is_float($text)) {
            return str_replace(',', '.', (string) $text);
        }

        $text = str_replace("'", "''", (string) $text);

        return addcslashes($text, "\000\n\r\\\032");
    }

    /**
     * Выполняет оператор SQL.
     *
     * @return  boolean
     *
     * @throws  \Exception
     * @throws  \RuntimeException
     */
    public function execute(): bool {
        $this->connect();

        $sql = $this->replacePrefix((string) $this->sql);

        $this->count++;

        $bounded =& $this->sql->getBounded();

        if ($this->monitor) {
            $this->monitor->startQuery($sql, $bounded);
        }

        $this->executed = false;

        foreach ($bounded as $key => $obj) {
            $this->statement->bindParam($key, $obj->value, $obj->dataType, $obj->length, $obj->driverOptions);
        }

        try {
            $this->executed = $this->statement->execute();

            if ($this->monitor) {
                $this->monitor->stopQuery();
            }

            return true;
        } catch (\PDOException $exception) {
            if ($this->monitor) {
                $this->monitor->stopQuery();
            }

            $errorNum = (int) $this->statement->errorCode();
            $errorMsg = (string) implode(', ', $this->statement->errorInfo());

            try {
                if (!$this->connected()) {
                    try {
                        $this->connection = null;
                        $this->connect();
                    } catch (ConnectionFailureException $e) {
                        throw new ExecutionFailureException($sql, $errorMsg, $errorNum);
                    }

                    return $this->execute();
                }
            } catch (\LogicException $e) {
                throw new ExecutionFailureException($sql, $errorMsg, $errorNum, $e);
            }

            throw new ExecutionFailureException($sql, $errorMsg, $errorNum);
        }
    }

    /**
     * Возвращает атрибут подключения к базе данных PDO
     * https://www.php.net/manual/en/pdo.getattribute.php
     *
     * <pre>
     * Использование:
     * $db->getOption(PDO::ATTR_CASE);
     * </pre>
     *
     * @param   mixed  $key  Одна из констант PDO::ATTR_*.
     *
     * @return  mixed
     *
     */
    public function getOption(mixed $key): mixed {
        $this->connect();

        return $this->connection->getAttribute($key);
    }

    /**
     * Возвращает версию соединителя базы данных.
     *
     * @return  string  Версия соединителя базы данных.
     *
     */
    public function getVersion(): string {
        $this->connect();

        return $this->getOption(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Возвращает запрос для запуска и убедитесь, что база данных работает.
     *
     * @return  string  Запрос на проверку работоспособности БД.
     *
     */
    public function getConnectedQuery(): string {
        return 'SELECT 1';
    }

    /**
     * Устанавливает атрибут дескриптора базы данных PDO.
     * https://www.php.net/manual/en/pdo.setattribute.php
     *
     * <pre>
     * Использование:
     * $db->setOption(PDO::ATTR_CASE, PDO::CASE_UPPER);
     * </pre>
     *
     * @param   integer  $key    Одна из констант PDO::ATTR_*.
     * @param   mixed    $value  Одна из связанных констант PDO, относящаяся к конкретному ключу атрибута.
     *
     * @return boolean
     *
     */
    public function setOption(int $key, mixed $value): bool {
        $this->connect();

        return $this->connection->setAttribute($key, $value);
    }

    /**
     * Проверяет, доступно ли расширение PDO.
     * При необходимости переопределите, чтобы проверить наличие конкретных драйверов PDO.
     *
     * @return  boolean  True в случае успеха, иначе false.
     *
     */
    public static function isSupported(): bool {
        return \defined('\\PDO::ATTR_DRIVER_NAME');
    }

    /**
     * Определяет, активно ли соединение с сервером.
     *
     * @return  boolean  True, если оно подключено к ядру базы данных.
     *
     * @throws  \LogicException
     */
    public function connected(): bool {
        static $checkingConnected = false;

        if ($checkingConnected) {
            $checkingConnected = false;

            throw new \LogicException('Рекурсия пытается проверить наличие соединения.');
        }

        $sql       = $this->sql;
        $limit     = $this->limit;
        $offset    = $this->offset;
        $statement = $this->statement;

        try {
            $checkingConnected = true;

            $this->setQuery($this->getConnectedQuery());
            $status = (bool) $this->loadResult();
        } catch (\Exception $e) {
            $status = false;
        }

        $this->sql         = $sql;
        $this->limit       = $limit;
        $this->offset      = $offset;
        $this->statement   = $statement;
        $checkingConnected = false;

        return $status;
    }

    /**
     * Метод для получения автоматически увеличивающегося значения из последнего оператора INSERT.
     *
     * @return  boolean|string  Значение поля автоинкремента из последней вставленной строки.
     *
     */
    public function insertid(): bool|string {
        $this->connect();

        return @$this->connection->lastInsertId();
    }

    /**
     * Выбирает базу данных для использования.
     *
     * @param string $database  Имя базы данных, которую необходимо выбрать для использования.
     *
     * @return  boolean  True если база данных была успешно выбрана.
     *
     * @throws  \RuntimeException
     */
    public function select(string $database): bool {
        $this->connect();

        return true;
    }

    /**
     * Настраивает соединение на использование кодировки символов UTF-8.
     *
     * @return  boolean
     *
     */
    public function setUtf(): bool {
        return false;
    }

    /**
     * Метод фиксации транзакции.
     *
     * @param boolean $toSavepoint  Если true, сохранит последнюю точку сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function transactionCommit(bool $toSavepoint = false): void {
        $this->connect();

        if (!$toSavepoint || $this->transactionDepth === 1) {
            $this->connection->commit();
        }

        $this->transactionDepth--;
    }

    /**
     * Метод для отката транзакции.
     *
     * @param boolean $toSavepoint  Если true, откат к последней точке сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function transactionRollback(bool $toSavepoint = false): void {
        $this->connect();

        if (!$toSavepoint || $this->transactionDepth === 1) {
            $this->connection->rollBack();
        }

        $this->transactionDepth--;
    }

    /**
     * Метод инициализации транзакции.
     *
     * @param boolean $asSavepoint  Если true и транзакция уже активна, будет создана точка сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function transactionStart(bool $asSavepoint = false): void {
        $this->connect();

        if (!$asSavepoint || !$this->transactionDepth) {
            $this->connection->beginTransaction();
        }

        $this->transactionDepth++;
    }

    /**
     * Подготавливает оператор SQL к выполнению
     *
     * @param   string  $query  SQL-запрос, который необходимо подготовить.
     *
     * @return  StatementInterface
     * @throws  PrepareStatementFailureException
     */
    protected function prepareStatement(string $query): StatementInterface {
        try {
            return new PdoStatement($this->connection->prepare($query, $this->options['driverOptions']));
        } catch (\PDOException $exception) {
            throw new PrepareStatementFailureException($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * PDO не поддерживает сериализацию
     *
     * @return  array
     *
     */
    public function __sleep() {
        $serializedProperties = [];

        $reflect = new \ReflectionClass($this);

        $properties = $reflect->getProperties();

        foreach ($properties as $property) {
            if ($property->isStatic() === false && !($this->{$property->name} instanceof \PDO)) {
                $serializedProperties[] = $property->name;
            }
        }

        return $serializedProperties;
    }

    /**
     * Проснуться после сериализации
     *
     * @return  void
     *
     */
    public function __wakeup() {
        $this->__construct($this->options);
    }
}
