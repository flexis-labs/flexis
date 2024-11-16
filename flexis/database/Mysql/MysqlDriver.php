<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysql;

use Flexis\Database\Exception\ConnectionFailureException;
use Flexis\Database\Pdo\PdoDriver;
use Flexis\Database\UTF8MB4SupportInterface;
use stdClass;

/**
 * Драйвер базы данных MySQL, поддерживающий соединения на основе PDO
 *
 * @link   https://www.php.net/manual/en/ref.pdo-mysql.php
 */
class MysqlDriver extends PdoDriver implements UTF8MB4SupportInterface {
    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'mysql';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких как имена таблиц, имена полей и т. д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках,
     * иначе первый символ будет использоваться для открывающей кавычки,
     * а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = '`';

    /**
     * Нулевое или нулевое представление отметки времени для драйвера базы данных.
     *
     * @var    string|null
     */
    protected ?string $nullDate = '0000-00-00 00:00:00';

    /**
     * True, если ядро базы данных поддерживает много байтовую кодировку UTF-8 (utf8mb4).
     *
     * @var    boolean
     */
    protected bool $utf8mb4 = false;

    /**
     * True, если ядром базы данных является MariaDB.
     *
     * @var    boolean
     */
    protected bool $mariadb = false;

    /**
     * Минимальная поддерживаемая версия базы данных.
     *
     * @var    string|null
     */
    protected static ?string $dbMinimum = '5.6';

    /**
     * Минимальная поддерживаемая версия базы данных MariaDB.
     *
     * @var    string
     */
    protected static string $dbMinMariadb = '10.0';

    /**
     * Набор шифров по умолчанию для соединений TLS.
     *
     * @var    array
     */
    protected static array $defaultCipherSuite = [
        'AES128-GCM-SHA256',
        'AES256-GCM-SHA384',
        'AES128-CBC-SHA256',
        'AES256-CBC-SHA384',
        'DES-CBC3-SHA',
    ];

    /**
     * Кодировка по умолчанию.
     *
     * @var    string
     */
    public mixed $charset = 'utf8';

    /**
     * Конструктор.
     *
     * @param   array  $options  Массив параметров базы данных с ключами: хост, пользователь, пароль, база данных, выбор.
     *
     */
    public function __construct(array $options) {
        /**
         * sql_mode в MySql 5.7.8+ строгий режим по умолчанию минус ONLY_FULL_GROUP_BY
         *
         * @link https://dev.mysql.com/doc/relnotes/mysql/5.7/en/news-5-7-8.html#mysqld-5-7-8-sql-mode
         */
        $sqlModes = [
            'STRICT_TRANS_TABLES',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ENGINE_SUBSTITUTION',
        ];

        $options['driver']   = 'mysql';
        $options['charset']  = $options['charset'] ?? 'utf8';
        $options['sqlModes'] = isset($options['sqlModes']) ? (array) $options['sqlModes'] : $sqlModes;

        $this->charset = $options['charset'];
        $this->utf8mb4 = $options['charset'] === 'utf8mb4';

        parent::__construct($options);
    }

    /**
     * При необходимости подключается к базе данных.
     *
     * @return  void  Возвращает void, если база данных подключена успешно.
     *
     * @throws  \RuntimeException
     */
    public function connect(): void {
        if ($this->getConnection()) {
            return;
        }

        if ($this->options['ssl'] !== [] && $this->options['ssl']['enable'] === true) {
            $sslContextIsNull = true;

            foreach (['cipher', 'ca', 'capath', 'key', 'cert'] as $key => $value) {
                if ($this->options['ssl'][$value] !== null) {
                    $this->options['driverOptions'][constant('\PDO::MYSQL_ATTR_SSL_' . strtoupper($value))] = $this->options['ssl'][$value];
                    $sslContextIsNull                                                                       = false;
                }
            }

            if ($sslContextIsNull === true) {
                $this->options['driverOptions'][\PDO::MYSQL_ATTR_SSL_CIPHER] = implode(':', static::$defaultCipherSuite);
            }

            if ($this->options['ssl']['verify_server_cert'] !== null && defined('\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $this->options['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $this->options['ssl']['verify_server_cert'];
            }
        }

        try {
            parent::connect();
        } catch (ConnectionFailureException $e) {
            if (!$this->utf8mb4) {
                throw $e;
            }

            $this->utf8mb4            = false;
            $this->options['charset'] = 'utf8';

            parent::connect();
        }

        $serverVersion = $this->getVersion();

        $this->mariadb = stripos($serverVersion, 'mariadb') !== false;

        if ($this->utf8mb4) {
            $this->utf8mb4 = version_compare($serverVersion, '5.5.3', '>=');

            if ($this->mariadb && version_compare($serverVersion, '10.0.0', '<')) {
                $this->utf8mb4 = false;
            }

            if (!$this->utf8mb4) {
                parent::disconnect();
                $this->options['charset'] = 'utf8';
                parent::connect();
            }
        }

        if ($this->options['sqlModes'] !== []) {
            $this->connection->query('SET @@SESSION.sql_mode = \'' . implode(',', $this->options['sqlModes']) . '\';');
        }

        $this->setOption(\PDO::ATTR_EMULATE_PREPARES, true);
    }

    /**
     * Автоматически понизить версию запроса CREATE TABLE или ALTER TABLE с utf8mb4 (много байтовая UTF-8) до обычного utf8.
     *
     * Используется, когда сервер не поддерживает много байтовую кодировку UTF-8.
     *
     * @param string $query  Запрос на конвертацию.
     *
     * @return  string  Преобразованный запрос.
     */
    public function convertUtf8mb4QueryToUtf8(string $query): string {
        if ($this->hasUTF8mb4Support()) {
            return $query;
        }

        $beginningOfQuery = substr($query, 0, 12);
        $beginningOfQuery = strtoupper($beginningOfQuery);

        if (!\in_array($beginningOfQuery, ['ALTER TABLE ', 'CREATE TABLE'], true)) {
            return $query;
        }

        return str_replace('utf8mb4', 'utf8', $query);
    }

    /**
     * Проверяет, доступен ли соединитель MySQL.
     *
     * @return  boolean  True в случае успеха, иначе false.
     *
     */
    public static function isSupported(): bool {
        return class_exists('\\PDO') && \in_array('mysql', \PDO::getAvailableDrivers(), true);
    }

    /**
     * Выбирает базу данных для использования.
     *
     * @param string $database  Имя базы данных, которую необходимо выбрать для использования.
     *
     * @return  boolean
     *
     * @throws  \RuntimeException
     */
    public function select(string $database): bool {
        $this->connect();

        $this->setQuery('USE ' . $this->quoteName($database))
            ->execute();

        return true;
    }

    /**
     * Возвращает строку запроса, чтобы изменить набор символов базы данных.
     *
     * @param string $dbName  Имя базы данных.
     *
     * @return  string  Запрос, который изменяет строку запроса к базе данных.
     */
    public function getAlterDbCharacterSet(string $dbName): string {
        $charset = $this->utf8mb4 ? 'utf8mb4' : 'utf8';

        return 'ALTER DATABASE ' . $this->quoteName($dbName) . ' CHARACTER SET `' . $charset . '`';
    }

    /**
     * Метод получения используемых параметров сортировки базы данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые базой данных (string), или логическое значение false, если не поддерживается.
     *
     * @throws  \RuntimeException
     */
    public function getCollation(): bool|string {
        $this->connect();

        return $this->setQuery('SELECT @@collation_database;')->loadResult();
    }

    /**
     * Метод получения используемых параметров сортировки соединения с базой данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые соединением с базой данных (string), или логическое значение false, если не поддерживается.
     * @throws  \RuntimeException
     */
    public function getConnectionCollation(): string|bool {
        $this->connect();

        return $this->setQuery('SELECT @@collation_connection;')->loadResult();
    }

    /**
     * Метод для получения сведений об используемом шифровании базы данных (шифр и протокол).
     *
     * @return  string  Подробности шифрования базы данных.
     * @throws  \RuntimeException
     */
    public function getConnectionEncryption(): string {
        $this->connect();

        $variables = $this->setQuery('SHOW SESSION STATUS WHERE `Variable_name` IN (\'Ssl_version\', \'Ssl_cipher\')')
            ->loadObjectList('Variable_name');

        if (!empty($variables['Ssl_cipher']->Value)) {
            return $variables['Ssl_version']->Value . ' (' . $variables['Ssl_cipher']->Value . ')';
        }

        return '';
    }

    /**
     * Метод проверки того, поддерживается ли шифрование TLS-соединений базы данных.
     *
     * @return  boolean  Поддерживает ли база данных шифрование соединений TLS.
     */
    public function isConnectionEncryptionSupported(): bool {
        $this->connect();

        $variables = $this->setQuery('SHOW SESSION VARIABLES WHERE `Variable_name` IN (\'have_ssl\')')->loadObjectList('Variable_name');

        return !empty($variables['have_ssl']->Value) && $variables['have_ssl']->Value === 'YES';
    }

    /**
     * Возвращает строку запроса для создания новой базы данных.
     *
     * @param stdClass $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных. Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean  $utf      True, если база данных поддерживает набор символов UTF-8.
     *
     * @return  string  Запрос, создающий базу данных
     */
    protected function getCreateDatabaseQuery(stdClass $options, bool $utf): string {
        if ($utf) {
            $charset   = $this->utf8mb4 ? 'utf8mb4' : 'utf8';
            $collation = $charset . '_unicode_ci';

            return 'CREATE DATABASE ' . $this->quoteName($options->db_name) . ' CHARACTER SET `' . $charset . '` COLLATE `' . $collation . '`';
        }

        return 'CREATE DATABASE ' . $this->quoteName($options->db_name);
    }

    /**
     * Показывает оператор таблицы CREATE, создающий данные таблицы.
     *
     * @param mixed $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array  Список созданных SQL для таблиц.
     *
     * @throws  \RuntimeException
     */
    public function getTableCreate(mixed $tables): array {
        $this->connect();

        $result = [];
        $tables = (array) $tables;

        foreach ($tables as $table) {
            $row = $this->setQuery('SHOW CREATE TABLE ' . $this->quoteName($table))->loadRow();

            $result[$table] = $row[1];
        }

        return $result;
    }

    /**
     * Извлекает информацию о полях данной таблицы.
     *
     * @param string  $table     Имя таблицы базы данных.
     * @param boolean $typeOnly  Значение true, чтобы возвращать только типы полей.
     *
     * @return  array  Массив полей таблицы базы данных.
     *
     * @throws  \RuntimeException
     */
    public function getTableColumns(string $table, bool $typeOnly = true): array {
        $this->connect();

        $result = [];

        $fields = $this->setQuery('SHOW FULL COLUMNS FROM ' . $this->quoteName($table))->loadObjectList();

        if ($typeOnly) {
            foreach ($fields as $field) {
                $result[$field->Field] = preg_replace('/[(0-9)]/', '', $field->Type);
            }
        } else {
            foreach ($fields as $field) {
                $result[$field->Field] = $field;
            }
        }

        return $result;
    }

    /**
     * Возвращает подробный список ключей для таблицы.
     *
     * @param mixed $tables  Имя таблицы.
     *
     * @return  array  Массив спецификации столбца таблицы.
     *
     * @throws  \RuntimeException
     */
    public function getTableKeys(mixed $tables): array {
        $this->connect();

        return $this->setQuery('SHOW KEYS FROM ' . $this->quoteName($tables))->loadObjectList();
    }

    /**
     * Метод получения массива всех таблиц в базе данных.
     *
     * @return  array  Массив всех таблиц в базе данных.
     *
     * @throws  \RuntimeException
     */
    public function getTableList(): array {
        $this->connect();

        return $this->setQuery('SHOW FULL TABLES WHERE table_type="BASE TABLE"')->loadColumn();
    }

    /**
     * Возвращает версию соединителя базы данных.
     *
     * @return  string  Версия соединителя базы данных.
     */
    public function getVersion(): string {
        $this->connect();

        $version = $this->getOption(\PDO::ATTR_SERVER_VERSION);

        if (stripos($version, 'mariadb') !== false) {
            return preg_replace('/^5\.5\.5-/', '', $version);
        }

        return $version;
    }

    /**
     * Возвращает минимальную поддерживаемую версию базы данных.
     *
     * @return  string
     */
    public function getMinimum(): string {
        return $this->mariadb ? static::$dbMinMariadb : static::$dbMinimum;
    }

    /**
     * Возвращает нулевое или нулевое представление отметки времени для драйвера базы данных.
     *
     * @return  string
     */
    public function getNullDate(): string {
        if (\in_array('NO_ZERO_DATE', $this->options['sqlModes']) !== false) {
            $this->nullDate = '1000-01-01 00:00:00';
        }

        return $this->nullDate;
    }

    /**
     * Определите, поддерживает ли ядро базы данных много байтовую кодировку UTF-8 (utf8mb4).
     *
     * @return  boolean  True, если ядро базы данных поддерживает много байтовую кодировку UTF-8.
     */
    public function hasUTF8mb4Support(): bool {
        return $this->utf8mb4;
    }

    /**
     * Определите, является ли ядром базы данных MariaDB.
     *
     * @return  boolean
     */
    public function isMariaDb(): bool {
        $this->connect();

        return $this->mariadb;
    }

    /**
     * Блокирует таблицу в базе данных.
     *
     * @param string $tableName Имя таблицы, которую нужно блокировать.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function lockTable(string $tableName): static {
        $this->setQuery('LOCK TABLES ' . $this->quoteName($tableName) . ' WRITE')
            ->execute();

        return $this;
    }

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable    Имя таблицы, которую нужно переименовать.
     * @param string      $newTable    Новое имя таблицы.
     * @param string|null $backup      Не используется MySQL.
     * @param string|null $prefix      Not used by MySQL.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function renameTable(
        string $oldTable,
        string $newTable,
        string $backup = null,
        string $prefix = null
    ): static {

        $this->setQuery('RENAME TABLE ' . $this->quoteName($oldTable) . ' TO ' . $this->quoteName($newTable))
            ->execute();

        return $this;
    }

    /**
     * Вставляет строку в таблицу на основе свойств объекта.
     *
     * @param string      $table     Имя таблицы базы данных, в которую требуется вставить.
     * @param object      $object    Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param string|null $key       Имя первичного ключа. Если указано, свойство объекта обновляется.
     *
     * @return  boolean
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function insertObject(string $table, object &$object, string $key = null): bool {
        $fields       = [];
        $values       = [];
        $tableColumns = $this->getTableColumns($table);

        foreach (get_object_vars($object) as $k => $v) {
            if (!array_key_exists($k, $tableColumns)) {
                continue;
            }

            if (\is_array($v) || \is_object($v) || $v === null) {
                continue;
            }

            if ($k[0] === '_') {
                continue;
            }

            if ($tableColumns[$k] === 'datetime' && empty($v)) {
                continue;
            }

            if (stristr($tableColumns[$k], 'int') !== false && $v === '') {
                continue;
            }

            $fields[] = $this->quoteName($k);
            $values[] = $this->quote($v);
        }

        $query = $this->createQuery()
            ->insert($this->quoteName($table))
            ->columns($fields)
            ->values(implode(',', $values));
        $this->setQuery($query)->execute();

        $id = $this->insertid();

        if ($key && $id && \is_string($key)) {
            $object->$key = $id;
        }

        return true;
    }

    /**
     * Метод экранирования строки для использования в инструкции SQL.
     *
     * Экранирующая ссылка Oracle:
     * http://www.orafaq.com/wiki/SQL_FAQ#How_does_one_escape_special_characters_when_writing_SQL_queries.3F
     *
     * Примечания к экранированию SQLite:
     * http://www.sqlite.org/faq.html#q14
     *
     * Тело метода реализовано в Zend Framework.
     *
     * @note Использование объектов запроса со связанными переменными предпочтительнее, чем приведенное ниже.
     *
     * @param mixed   $text   Строка, которую нужно экранировать.
     * @param boolean $extra  Неиспользуемый необязательный параметр для обеспечения дополнительного экранирования.
     *
     * @return  string  Экранированная строка.
     *
     */
    public function escape(mixed $text, bool $extra = false): string {
        if (\is_int($text)) {
            return $text;
        }

        if (\is_float($text)) {
            return str_replace(',', '.', (string) $text);
        }

        $this->connect();

        $result = substr($this->connection->quote($text), 1, -1);

        if ($extra) {
            $result = addcslashes($result, '%_');
        }

        return $result;
    }

    /**
     * Разблокирует таблицы в базе данных.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function unlockTables(): static {
        $this->setQuery('UNLOCK TABLES')
            ->execute();

        return $this;
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

        if (!$toSavepoint || $this->transactionDepth <= 1) {
            parent::transactionCommit($toSavepoint);
        } else {
            $this->transactionDepth--;
        }
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

        if (!$toSavepoint || $this->transactionDepth <= 1) {
            parent::transactionRollback($toSavepoint);
        } else {
            $savepoint = 'SP_' . ($this->transactionDepth - 1);
            $this->setQuery('ROLLBACK TO SAVEPOINT ' . $this->quoteName($savepoint));

            if ($this->execute()) {
                $this->transactionDepth--;
            }
        }
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
            parent::transactionStart($asSavepoint);
        } else {
            $savepoint = 'SP_' . $this->transactionDepth;
            $this->setQuery('SAVEPOINT ' . $this->quoteName($savepoint));

            if ($this->execute()) {
                $this->transactionDepth++;
            }
        }
    }
}
