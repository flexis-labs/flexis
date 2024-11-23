<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysqli;

use Flexis\Database\DatabaseDriver;
use Flexis\Database\DatabaseEvents;
use Flexis\Database\Event\ConnectionEvent;
use Flexis\Database\Exception\ConnectionFailureException;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Database\Exception\UnsupportedAdapterException;
use Flexis\Database\StatementInterface;
use Flexis\Database\UTF8MB4SupportInterface;
use stdClass;

/**
 * Драйвер базы данных MySQLi
 *
 * @link   https://www.php.net/manual/ru/book.mysqli.php
 */
class MysqliDriver extends DatabaseDriver implements UTF8MB4SupportInterface {
    /**
     * Ресурс подключения к базе данных.
     *
     * @var    \mysqli
     */
    protected $connection;

    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'mysqli';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких как имена таблиц, имена полей и т. д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках, иначе первый символ будет использоваться для открывающей кавычки, а второй — для закрывающей кавычки.
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
     * Минимальная поддерживаемая версия базы данных MySQL.
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
     * Конструктор.
     *
     * @param   array  $options  Список опций, используемых для настройки соединения
     *
     */
    public function __construct(array $options) {
        $sqlModes = [
            'STRICT_TRANS_TABLES',
            'ERROR_FOR_DIVISION_BY_ZERO',
            'NO_ENGINE_SUBSTITUTION',
        ];

        $options['host']     = $options['host'] ?? 'localhost';
        $options['user']     = $options['user'] ?? 'root';
        $options['password'] = $options['password'] ?? '';
        $options['database'] = $options['database'] ?? '';
        $options['select']   = !isset($options['select']) || (bool)$options['select'];
        $options['port']     = isset($options['port']) ? (int) $options['port'] : null;
        $options['socket']   = $options['socket'] ?? null;
        $options['utf8mb4']  = isset($options['utf8mb4']) && (bool)$options['utf8mb4'];
        $options['sqlModes'] = isset($options['sqlModes']) ? (array) $options['sqlModes'] : $sqlModes;
        $options['ssl']      = $options['ssl'] ?? [];

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
     * Проверяет, отвечает ли сервер базы данных.
     *
     * @param   string  $host                   Имя хоста или IP-адрес.
     * @param   int     $port                   The port number. Optional; default is the MySQL default.
     * @param   int     $initialWaitInSeconds   Количество секунд ожидания перед пингом сервера. Необязательный; по умолчанию — 0 секунд.
     * @param   int     $intervalWaitInSeconds  Количество секунд ожидания между пингами сервера. Необязательный; по умолчанию — 3 секунды.
     * @param   int     $timeoutInSeconds       Таймаут в секундах для ответа сервера. Необязательный; по умолчанию — 1 секунда.
     * @param   int     $retries                Количество повторов. Необязательный; по умолчанию 3.
     *
     * @return boolean
     * @todo  Возможно, это следует переместить в родительский класс.
     *
     */
    public function healthCheck(
        string $host,
        int $port = 3306,
        int $initialWaitInSeconds = 0,
        int $intervalWaitInSeconds = 3,
        int $timeoutInSeconds = 1,
        int $retries = 3
    ): bool {
        sleep($initialWaitInSeconds);

        for ($i = 0; $i < $retries; $i++) {
            $file = @fsockopen($host, $port, $errno, $errstr, $timeoutInSeconds);

            if ($file) {
                fclose($file);

                return true;
            }

            sleep($intervalWaitInSeconds);
        }

        return false;
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
            throw new UnsupportedAdapterException('Расширение MySQLi недоступно.');
        }

        $port = $this->options['port'] ?? 3306;

        if (preg_match('/^unix:(?P<socket>[^:]+)$/', $this->options['host'], $matches)) {
            // UNIX socket URI, e.g. 'unix:/path/to/unix/socket.sock'
            $this->options['host']   = null;
            $this->options['socket'] = $matches['socket'];
            $this->options['port']   = null;
        } elseif (
            preg_match(
                '/^(?P<host>((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))(:(?P<port>.+))?$/',
                $this->options['host'],
                $matches
            )
        ) {
            $this->options['host'] = $matches['host'];

            if (!empty($matches['port'])) {
                $port = $matches['port'];
            }
        } elseif (preg_match('/^(?P<host>\[.*\])(:(?P<port>.+))?$/', $this->options['host'], $matches)) {
            $this->options['host'] = $matches['host'];

            if (!empty($matches['port'])) {
                $port = $matches['port'];
            }
        } elseif (preg_match('/^(?P<host>(\w+:\/{2,3})?[a-z0-9\.\-]+)(:(?P<port>[^:]+))?$/i', $this->options['host'], $matches)) {
            $this->options['host'] = $matches['host'];

            if (!empty($matches['port'])) {
                $port = $matches['port'];
            }
        } elseif (preg_match('/^:(?P<port>[^:]+)$/', $this->options['host'], $matches)) {
            $this->options['host'] = 'localhost';
            $port                  = $matches['port'];
        }

        if (is_numeric($port)) {
            $this->options['port'] = (int) $port;
        } else {
            $this->options['socket'] = $port;
        }

        $this->connection = mysqli_init();

        $connectionFlags = 0;

        if ($this->options['ssl'] !== [] && $this->options['ssl']['enable'] === true) {
            $connectionFlags += MYSQLI_CLIENT_SSL;

            if (isset($this->options['ssl']['verify_server_cert'])) {
                if ($this->options['ssl']['verify_server_cert'] === true && defined('MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT')) {
                    $connectionFlags += MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT;
                } elseif ($this->options['ssl']['verify_server_cert'] === false && defined('MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT')) {
                    $connectionFlags += MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT;
                } elseif (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
                    $this->connection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $this->options['ssl']['verify_server_cert']);
                }
            }

            $this->connection->ssl_set(
                $this->options['ssl']['key'],
                $this->options['ssl']['cert'],
                $this->options['ssl']['ca'],
                $this->options['ssl']['capath'],
                $this->options['ssl']['cipher']
            );
        }

        $connected = @$this->connection->real_connect(
            $this->options['host'],
            $this->options['user'],
            $this->options['password'],
            null,
            $this->options['port'],
            $this->options['socket'],
            $connectionFlags
        );

        if (!$connected) {
            throw new ConnectionFailureException(
                'Не удалось подключиться к базе данных: ' . $this->connection->connect_error,
                $this->connection->connect_errno
            );
        }

        if ($this->options['sqlModes'] !== []) {
            $this->connection->query('SET @@SESSION.sql_mode = \'' . implode(',', $this->options['sqlModes']) . '\';');
        }

        $this->options['sqlModes'] = explode(',', $this->setQuery('SELECT @@SESSION.sql_mode;')->loadResult());

        if ($this->options['select'] && !empty($this->options['database'])) {
            $this->select($this->options['database']);
        }

        $this->mariadb = stripos($this->connection->server_info, 'mariadb') !== false;
        $this->utf8mb4 = $this->serverClaimsUtf8mb4Support();
        $this->utf = $this->setUtf();

        $this->dispatchEvent(new ConnectionEvent(DatabaseEvents::POST_CONNECT, $this));
    }

    /**
     * Автоматически понизить версию запроса CREATE TABLE или ALTER TABLE с utf8mb4 (много байтовая UTF-8) до обычного utf8.
     *
     * Используется, когда сервер не поддерживает много байтовую кодировку UTF-8.
     *
     * @param string $query  Запрос на конвертацию
     *
     * @return  string  Преобразованный запрос
     */
    public function convertUtf8mb4QueryToUtf8(string $query): string {
        if ($this->utf8mb4) {
            return $query;
        }

        if (!preg_match('/^(ALTER|CREATE)\s+TABLE\s+/i', $query)) {
            return $query;
        }

        if (stripos($query, 'utf8mb4') === false) {
            return $query;
        }

        return preg_replace('/[`"\'][^`"\']*[`"\'](*SKIP)(*FAIL)|utf8mb4/i', 'utf8', $query);
    }

    /**
     * Отключает базу данных.
     *
     * @return  void
     *
     */
    public function disconnect(): void {
        if (\is_callable([$this->connection, 'close'])) {
            $this->connection->close();
        }

        parent::disconnect();
    }

    /**
     * Метод экранирования строки для использования в инструкции SQL.
     *
     * @param mixed   $text   Строка, которую нужно экранировать.
     * @param boolean $extra  Необязательный параметр для обеспечения дополнительного экранирования.
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

        $result = $this->connection->real_escape_string((string) $text);

        if ($extra) {
            $result = addcslashes($result, '%_');
        }

        return $result;
    }

    /**
     * Проверяет, доступен ли соединитель MySQLi.
     *
     * @return  boolean  True в случае успеха, иначе false.
     *
     */
    public static function isSupported(): bool {
        return \extension_loaded('mysqli');
    }

    /**
     * Определяет, активно ли соединение с сервером.
     *
     * @return  boolean  True, если оно подключено к ядру базы данных.
     *
     */
    public function connected(): bool {
        if (\is_object($this->connection)) {
            return $this->connection->ping();
        }

        return false;
    }

    /**
     * Возвращает строку запроса, чтобы изменить набор символов базы данных.
     *
     * @param string $dbName  Имя базы данных
     *
     * @return  string  Запрос, который изменяет строку запроса к базе данных
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
     * @return  string|boolean  Параметры сортировки, используемые соединением с базой данных (string),
     *                          или логическое значение false, если не поддерживается.
     *
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
     *
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
     * @return  boolean Поддерживает ли база данных шифрование соединений TLS.
     */
    public function isConnectionEncryptionSupported(): bool {
        $this->connect();

        $variables = $this->setQuery('SHOW SESSION VARIABLES WHERE `Variable_name` IN (\'have_ssl\')')->loadObjectList('Variable_name');

        return !empty($variables['have_ssl']->Value) && $variables['have_ssl']->Value === 'YES';
    }

    /**
     * Возвращает строку запроса для создания новой базы данных.
     *
     * @param stdClass     $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных. Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean      $utf      True, если база данных поддерживает набор символов UTF-8.
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
            $row = $this->setQuery('SHOW CREATE TABLE ' . $this->quoteName($this->escape($table)))->loadRow();

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
        $fields = $this->setQuery('SHOW FULL COLUMNS FROM ' . $this->quoteName($this->escape($table)))->loadObjectList();

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
     *
     */
    public function getVersion(): string {
        $this->connect();

        if ($this->mariadb) {
            return preg_replace('/^5\.5\.5-/', '', $this->connection->server_info);
        }

        return $this->connection->server_info;
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
     * Определяет, поддерживает ли ядро базы данных много байтовую кодировку UTF-8 (utf8mb4).
     *
     * @return  boolean  True, если ядро базы данных поддерживает много байтовую кодировку UTF-8.
     */
    public function hasUTF8mb4Support(): bool {
        return $this->utf8mb4;
    }

    /**
     * Определяет, является ли ядром базы данных MariaDB.
     *
     * @return  boolean
     */
    public function isMariaDb(): bool {
        $this->connect();

        return $this->mariadb;
    }

    /**
     * Метод для получения автоматически увеличивающегося значения из последнего оператора INSERT.
     *
     * @return  mixed  Значение поля автоинкремента из последней вставленной строки.
     *                 Если значение больше максимального значения int, оно вернет строку.
     *
     */
    public function insertid(): mixed {
        $this->connect();

        return $this->connection->insert_id;
    }

    /**
     * Вставляет строку в таблицу на основе свойств объекта.
     *
     * @param string      $table   Имя таблицы базы данных, в которую требуется вставить.
     * @param object      $object  Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param string|null $key     Имя первичного ключа. Если указано, свойство объекта обновляется.
     *
     * @return  boolean
     * @throws  \RuntimeException
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
     * Блокирует таблицу в базе данных.
     *
     * @param string $tableName  Имя таблицы, которую нужно блокировать.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function lockTable(string $tableName): static {
        $this->executeUnpreparedQuery($this->replacePrefix('LOCK TABLES ' . $this->quoteName($tableName) . ' WRITE'));

        return $this;
    }

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable  Имя таблицы, которую нужно переименовать.
     * @param string      $newTable  Новое имя таблицы.
     * @param string|null $backup    Не используется MySQL.
     * @param string|null $prefix    Не используется MySQL.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function renameTable(
        string $oldTable,
        string $newTable,
        string $backup = null,
        string $prefix = null
    ): static {
        $this->setQuery('RENAME TABLE ' . $oldTable . ' TO ' . $newTable)->execute();

        return $this;
    }

    /**
     * Выбирает базу данных для использования.
     *
     * @param string $database  Имя базы данных, которую необходимо выбрать для использования.
     *
     * @return  boolean  True, если база данных была успешно выбрана.
     *
     * @throws  \RuntimeException
     */
    public function select(string $database): bool {
        $this->connect();

        if (!$database) {
            return false;
        }

        if (!$this->connection->select_db($database)) {
            throw new ConnectionFailureException('Не удалось подключиться к базе данных.');
        }

        return true;
    }

    /**
     * Настраивает соединение на использование кодировки символов UTF-8.
     *
     * @return  boolean
     *
     */
    public function setUtf(): bool {
        if (!$this->utf) {
            return false;
        }

        $this->connect();

        $charset = $this->utf8mb4 && $this->options['utf8mb4'] ? 'utf8mb4' : 'utf8';
        $result  = @$this->connection->set_charset($charset);

        if (!$result && $this->utf8mb4 && $this->options['utf8mb4']) {
            $this->utf8mb4 = false;
            $result        = @$this->connection->set_charset('utf8');
        }

        return $result;
    }

    /**
     * Метод фиксации транзакции.
     *
     * @param boolean $toSavepoint  Если true, сохраняет последнюю точку сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function transactionCommit(bool $toSavepoint = false): void {
        if (!$toSavepoint || $this->transactionDepth <= 1) {
            $this->connect();

            if ($this->connection->commit()) {
                $this->transactionDepth = 0;
            }

            return;
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
        if (!$toSavepoint || $this->transactionDepth <= 1) {
            $this->connect();

            if ($this->connection->rollback()) {
                $this->transactionDepth = 0;
            }

            return;
        }

        $savepoint = 'SP_' . ($this->transactionDepth - 1);

        if ($this->executeUnpreparedQuery('ROLLBACK TO SAVEPOINT ' . $this->quoteName($savepoint))) {
            $this->transactionDepth--;
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
            if ($this->connection->begin_transaction()) {
                $this->transactionDepth = 1;
            }

            return;
        }

        $savepoint = 'SP_' . $this->transactionDepth;

        if ($this->connection->savepoint($savepoint)) {
            $this->transactionDepth++;
        }
    }

    /**
     * Внутренний метод для выполнения запросов, которые нельзя выполнить как подготовленные операторы.
     *
     * @param string $sql  Оператор SQL для выполнения.
     *
     * @return  boolean
     *
     */
    protected function executeUnpreparedQuery(string $sql): bool {
        $this->connect();

        $cursor = $this->connection->query($sql);

        if (!$cursor) {
            $errorNum = (int) $this->connection->errno;
            $errorMsg = (string) $this->connection->error;

            if (!$this->connected()) {
                try {
                    $this->connection = null;
                    $this->connect();
                } catch (ConnectionFailureException $e) {
                    throw new ExecutionFailureException($sql, $errorMsg, $errorNum);
                }

                return $this->executeUnpreparedQuery($sql);
            }

            throw new ExecutionFailureException($sql, $errorMsg, $errorNum);
        }

        $this->freeResult();

        if ($cursor instanceof \mysqli_result) {
            $cursor->free_result();
        }

        return true;
    }

    /**
     * Подготавливает оператор SQL к выполнению.
     *
     * @param   string  $query  SQL-запрос, который необходимо подготовить.
     *
     * @return  StatementInterface
     * @throws  PrepareStatementFailureException
     */
    protected function prepareStatement(string $query): StatementInterface {
        return new MysqliStatement($this->connection, $query);
    }

    /**
     * Разблокирует таблицы в базе данных.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function unlockTables(): static {
        $this->executeUnpreparedQuery('UNLOCK TABLES');

        return $this;
    }

    /**
     * Заявляет ли сервер базы данных о поддержке многобайтовой сортировки UTF-8 (utf8mb4)?
     *
     * * libmysql - поддерживает utf8mb4 начиная с версии 5.5.3 (та же версия, что и сервер MySQL).
     * * mysqlnd - поддерживает utf8mb4 с версии 5.0.9.
     *
     * @return  boolean
     */
    private function serverClaimsUtf8mb4Support(): bool {
        $client_version = mysqli_get_client_info();
        $server_version = $this->getVersion();

        if (version_compare($server_version, '5.5.3', '<')) {
            return false;
        }

        if ($this->mariadb && version_compare($server_version, '10.0.0', '<')) {
            return false;
        }

        if (\str_contains($client_version, 'mysqlnd')) {
            $client_version = preg_replace('/^\D+([\d.]+).*/', '$1', $client_version);

            return version_compare($client_version, '5.0.9', '>=');
        }

        return version_compare($client_version, '5.5.3', '>=');
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
}
