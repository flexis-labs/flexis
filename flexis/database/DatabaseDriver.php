<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Flexis\Database\Event\ConnectionEvent;
use Flexis\Database\Exception\ConnectionFailureException;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Event\DispatcherAwareInterface;
use Flexis\Event\DispatcherAwareTrait;
use Flexis\Event\EventInterface;

use DirectoryIterator;
use InvalidArgumentException;
use RuntimeException;
use stdClass;

use function array_key_exists;
use function in_array;
use function is_array;
use function strlen;

/**
 * Класс драйвера базы данных Flexis Framework.
 */
abstract class DatabaseDriver implements DatabaseInterface, DispatcherAwareInterface {
    use DispatcherAwareTrait;

    /**
     * Имя базы данных.
     *
     * @var    mixed
     */
    private mixed $database;

    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = null;

    /**
     * Тип семейства серверов баз данных, поддерживаемый этим драйвером.
     *
     * @var    string|null
     */
    public ?string $serverType = null;

    /**
     * Ресурс подключения к базе данных.
     *
     * @var    resource
     */
    protected $connection;

    /**
     * Содержит список доступных соединителей БД.
     *
     * @var    array
     */
    protected static array $connectors = [];

    /**
     * Количество операторов SQL, выполняемых драйвером базы данных.
     *
     * @var    integer
     */
    protected int $count = 0;

    /**
     * Курсор подключения к базе данных из последнего запроса.
     *
     * @var    resource
     */
    protected $cursor;

    /**
     * Содержит текущий статус выполнения запроса.
     *
     * @var    boolean
     */
    protected bool $executed = false;

    /**
     * Затрагиваемый предел строк для текущего оператора SQL.
     *
     * @var    integer
     */
    protected int $limit = 0;

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких, как имена таблиц, имена полей и т.д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках,
     * иначе первый символ будет использоваться для открывающей кавычки,
     * а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = null;

    /**
     * Пустое или нулевое представление отметки времени для драйвера базы данных.
     *
     * @var    string|null
     */
    protected ?string $nullDate = null;

    /**
     * Затронутое смещение строки, применяемое к текущему оператору SQL.
     *
     * @var    integer
     */
    protected int $offset = 0;

    /**
     * Передается при создании экземпляра и сохраняется.
     *
     * @var    array|null
     */
    protected ?array $options = null;

    /**
     * Текущая инструкция SQL для выполнения.
     *
     * @var    mixed
     */
    protected mixed $sql;

    /**
     * Подготовленное заявление.
     *
     * @var    StatementInterface|null
     */
    protected ?StatementInterface $statement = null;

    /**
     * Префикс общей таблицы базы данных.
     *
     * @var    string|null
     */
    protected ?string $tablePrefix = null;

    /**
     * True, если ядро базы данных поддерживает кодировку символов UTF-8.
     *
     * @var    boolean
     */
    protected bool $utf = true;

    /**
     * Номер ошибки базы данных.
     *
     * @var    integer
     */
    protected int $errorNum = 0;

    /**
     * Сообщение об ошибке базы данных.
     *
     * @var    string|null
     */
    protected ?string $errorMsg = null;

    /**
     * Минимальная поддерживаемая версия базы данных.
     *
     * @var    string|null
     */
    protected static ?string $dbMinimum = null;

    /**
     * Глубина текущей транзакции.
     *
     * @var    integer
     */
    protected int $transactionDepth = 0;

    /**
     * Объект DatabaseFactory
     *
     * @var    DatabaseFactory|null
     */
    protected ?DatabaseFactory $factory = null;

    /**
     * Объект монитора запросов
     *
     * @var    QueryMonitorInterface|null
     */
    protected ?QueryMonitorInterface $monitor = null;

    /**
     * Возвращает список доступных соединителей базы данных.
     *
     * Список будет заполнен только теми соединителями, для которых существует класс и среда поддерживает его использование.
     * Это дает нам возможность иметь множество классов соединителей, которые сами знают, можно ли их использовать в данной системе.
     *
     * @return  array  Массив доступных соединителей базы данных.
     *
     */
    public static function getConnectors(): array {
        if (empty(self::$connectors)) {
            $dir      = __DIR__;
            $iterator = new DirectoryIterator($dir);

            /** @var DirectoryIterator $file */
            foreach ($iterator as $file) {
                if (!$file->isDir()) {
                    continue;
                }

                $baseName = $file->getBasename();

                /** @var DatabaseDriver $class */
                $class = __NAMESPACE__ . '\\' . ucfirst(strtolower($baseName)) . '\\' . ucfirst(strtolower($baseName)) . 'Driver';

                if (!class_exists($class) || !$class::isSupported()) {
                    continue;
                }

                self::$connectors[] = $baseName;
            }
        }

        return self::$connectors;
    }

    /**
     * Разбивает строку из нескольких запросов на массив отдельных запросов.
     *
     * @param string $sql  Введите строку SQL, которую можно разделить на отдельные запросы.
     *
     * @return  array
     *
     */
    public static function splitSql(string $sql): array {
        $start     = 0;
        $open      = false;
        $comment   = false;
        $endString = '';
        $end       = strlen($sql);
        $queries   = [];
        $query     = '';

        for ($i = 0; $i < $end; $i++) {
            $current      = substr($sql, $i, 1);
            $current2     = substr($sql, $i, 2);
            $current3     = substr($sql, $i, 3);
            $lenEndString = strlen($endString);
            $testEnd      = substr($sql, $i, $lenEndString);

            if (
                $current === '"' || $current === "'" || $current2 === '--'
                || ($current2 === '/*' && $current3 !== '/*!' && $current3 !== '/*+')
                || ($current === '#' && $current3 !== '#__')
                || ($comment && $testEnd === $endString)
            ) {

                $n = 2;

                while (substr($sql, $i - $n + 1, 1) === '\\' && $n < $i) {
                    $n++;
                }

                if ($n % 2 === 0) {
                    if ($open) {
                        if ($testEnd === $endString) {
                            if ($comment) {
                                $comment = false;

                                if ($lenEndString > 1) {
                                    $i += ($lenEndString - 1);
                                    $current = substr($sql, $i, 1);
                                }

                                $start = $i + 1;
                            }

                            $open      = false;
                            $endString = '';
                        }
                    } else {
                        $open = true;

                        if ($current2 === '--') {
                            $endString = "\n";
                            $comment   = true;
                        } elseif ($current2 === '/*') {
                            $endString = '*/';
                            $comment   = true;
                        } elseif ($current === '#') {
                            $endString = "\n";
                            $comment   = true;
                        } else {
                            $endString = $current;
                        }

                        if ($comment && $start < $i) {
                            $query .= substr($sql, $start, $i - $start);
                        }
                    }
                }
            }

            if ($comment) {
                $start = $i + 1;
            }

            if (($current === ';' && !$open) || $i === $end - 1) {
                if ($start <= $i) {
                    $query .= substr($sql, $start, $i - $start + 1);
                }

                $query = trim($query);

                if ($query) {
                    if (($i === $end - 1) && ($current !== ';')) {
                        $query .= ';';
                    }

                    $queries[] = $query;
                }

                $query = '';
                $start = $i + 1;
            }

            $endComment = false;
        }

        return $queries;
    }

    /**
     * Конструктор.
     *
     * @param   array  $options  Список опций, используемых для настройки соединения
     *
     */
    public function __construct(array $options) {
        $this->database    = $options['database'] ?? '';
        $this->tablePrefix = $options['prefix'] ?? '';
        $this->count       = 0;
        $this->errorNum    = 0;
        $this->options     = $options;
        $this->factory     = $options['factory'] ?? new DatabaseFactory();
        $this->monitor     = $options['monitor'] ?? null;
    }

    /**
     * Деструктор.
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Изменить набор символов базы данных.
     *
     * @param string $dbName  Имя базы данных, которое будет изменено
     *
     * @return  boolean
     *
     * @throws  RuntimeException
     */
    public function alterDbCharacterSet(?string $dbName = null): bool {
        if ($dbName === null) {
            throw new RuntimeException('Имя базы данных не должно быть нулевым.');
        }

        $this->setQuery($this->getAlterDbCharacterSet($dbName));

        return $this->execute();
    }

    /**
     * Создаёт новую базу данных, используя информацию из объекта $options.
     *
     * @param stdClass $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных. Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean  $utf      True, если база данных поддерживает набор символов UTF-8.
     *
     * @return  boolean
     *
     * @throws  RuntimeException
     */
    public function createDatabase(?stdClass $options = null, bool $utf = true): bool {
        if ($options === null) {
            throw new RuntimeException('Объект $options не должен иметь значение null.');
        }

        if (empty($options->db_name)) {
            throw new RuntimeException('Для объекта $options должно быть установлено db_name.');
        }

        if (empty($options->db_user)) {
            throw new RuntimeException('Для объекта $options должен быть установлен db_user.');
        }

        $this->setQuery($this->getCreateDatabaseQuery($options, $utf));

        return $this->execute();
    }

    /**
    * Создаёт новый объект DatabaseQuery.
    *
    * @return  QueryInterface
    */
    public function createQuery(): QueryInterface {
        return $this->factory->getQuery($this->name, $this);
    }

    /**
    * Отключает базу данных.
    *
    * @return  void
    */
    public function disconnect(): void {
        $this->freeResult();
        $this->connection = null;

        $this->dispatchEvent(new ConnectionEvent(DatabaseEvents::POST_DISCONNECT, $this));
    }

    /**
     * Отправляет событие.
     *
     * @param   EventInterface  $event  Событие для отправки
     *
     * @return  void
     */
    protected function dispatchEvent(EventInterface $event): void {
        try {
            $this->getDispatcher()->dispatch($event);
        } catch (\UnexpectedValueException $exception) {
            // Не выводим ошибок, если диспетчер не установлен
        }
    }

    /**
     * Удаляет таблицу из базы данных.
     *
     * @param string  $table     Имя таблицы базы данных, которую необходимо удалить.
     * @param boolean $ifExists  При необходимости укажите, что таблица должна существовать до ее удаления.
     *
     * @return  $this
     * @throws  RuntimeException
     */
    public function dropTable(string $table, bool $ifExists = true): static {
        $this->connect();

        $this->setQuery('DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $this->quoteName($table))
            ->execute();

        return $this;
    }

    /**
     * Выполняет оператор SQL.
     *
     * @return  boolean
     * @throws  RuntimeException
     */
    public function execute(): bool {
        $this->connect();
        $this->count++;

        $bounded =& $this->sql->getBounded();

        if ($this->monitor) {
            $sql = $this->replacePrefix((string) $this->sql);
            $this->monitor->startQuery($sql, $bounded);
        }

        $this->executed = false;

        foreach ($bounded as $key => $obj) {
            $this->statement->bindParam($key, $obj->value, $obj->dataType);
        }

        try {
            $this->executed = $this->statement->execute();

            if ($this->monitor) {
                $this->monitor->stopQuery();
            }

            return true;
        } catch (ExecutionFailureException $exception) {

            if ($this->monitor) {
                $this->monitor->stopQuery();
            }

            if (!$this->connected()) {
                try {
                    $this->connection = null;
                    $this->connect();
                } catch (ConnectionFailureException $e) {
                    throw $exception;
                }

                return $this->execute();
            }

            throw $exception;
        }
    }

    /**
     * Метод извлечения строки из курсора результирующего набора в виде массива.
     *
     * @return  mixed  Либо следующая строка из результирующего набора, либо значение false, если строк больше нет.
     *
     */
    protected function fetchArray(): mixed {
        if ($this->statement) {
            return $this->statement->fetch(FetchMode::NUMERIC);
        }

        return false;
    }

    /**
     * Метод для извлечения строки из курсора набора результатов в виде ассоциативного массива.
     *
     * @return  mixed  Либо следующая строка из набора результатов, либо false, если строк больше нет.
     *
     */
    protected function fetchAssoc(): mixed {
        if ($this->statement) {
            return $this->statement->fetch(FetchMode::ASSOCIATIVE);
        }

        return false;
    }

    /**
     * Метод для извлечения строки из курсора набора результатов как объекта.
     *
     * Обратите внимание: режим выборки должен быть настроен перед вызовом этого метода с помощью StatementInterface::setFetchMode().
     *
     * @return  mixed   Либо следующая строка из набора результатов, либо false, если строк больше нет.
     *
     */
    protected function fetchObject(): mixed {
        if ($this->statement) {
            return $this->statement->fetch();
        }

        return false;
    }

    /**
     * Метод освобождения памяти, используемой для набора результатов.
     *
     * @return  void
     *
     */
    protected function freeResult(): void {
        $this->executed = false;
        $this->statement?->closeCursor();
    }

    /**
     * Возвращает количество затронутых строк для предыдущего выполненного оператора SQL.
     *
     * @return  integer  Количество затронутых строк в предыдущей операции.
     */
    public function getAffectedRows(): int {
        $this->connect();

        if ($this->statement) {
            return $this->statement->rowCount();
        }

        return 0;
    }

    /**
     * Метод, обеспечивающий доступ к базовому соединению с базой данных.
     *
     * @return  resource  Базовый ресурс подключения к базе данных.
     *
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Возвращает общее количество операторов SQL, выполненных драйвером базы данных.
     *
     * @return  integer
     *
     */
    public function getCount(): int {
        return $this->count;
    }

    /**
     * Возвращает строку запроса, чтобы изменить набор символов базы данных.
     *
     * @param string $dbName  Имя базы данных
     *
     * @return  string  Запрос, который изменяет строку запроса к базе данных
     */
    protected function getAlterDbCharacterSet(string $dbName): string {
        return 'ALTER DATABASE ' . $this->quoteName($dbName) . ' CHARACTER SET ' . $this->quote('UTF8');
    }

    /**
     * Возвращает строку запроса для создания новой базы данных.
     *
     * @param stdClass $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных.
     *                           Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean  $utf      Истинно, если база данных поддерживает набор символов UTF-8.
     *
     * @return  string  Запрос, создающий базу данных
     */
    protected function getCreateDatabaseQuery(stdClass $options, bool $utf): string {
        return 'CREATE DATABASE ' . $this->quoteName($options->db_name);
    }

    /**
     * Получает имя базы данных, используемой этим соединением.
     *
     * @return  string
     *
     */
    protected function getDatabase(): string {
        return $this->database;
    }

    /**
     * Возвращает формат даты, совместимый с функцией PHP date(), для драйвера базы данных.
     *
     * @return  string
     *
     */
    public function getDateFormat(): string {
        return 'Y-m-d H:i:s';
    }

    /**
     * Возвращает минимальную поддерживаемую версию базы данных.
     *
     * @return  string
     *
     */
    public function getMinimum(): string {
        return static::$dbMinimum;
    }

    /**
     * Возвращает имя драйвера базы данных.
     *
     * Если $this->name не установлено, он попытается угадать имя драйвера по имени класса.
     *
     * @return  string
     */
    public function getName(): string {
        if (empty($this->name)) {
            $reflect = new \ReflectionClass($this);
            $this->name = strtolower(str_replace('Driver', '', $reflect->getShortName()));
        }

        return $this->name;
    }

    /**
     * Возвращает количество возвращенных строк для предыдущего выполненного оператора SQL.
     *
     * @return  integer   Количество возвращаемых строк.
     */
    public function getNumRows(): int {
        $this->connect();

        if ($this->statement) {
            return $this->statement->rowCount();
        }

        return 0;
    }

    /**
     * Возвращает тип семейства серверов.
     *
     * Если $this->serverType не установлен, будет предпринята попытка угадать тип семейства серверов по имени драйвера.
     * Если это невозможно, вместо этого будет возвращено имя драйвера.
     *
     * @return  string
     */
    public function getServerType(): string {
        if (empty($this->serverType)) {
            $name = $this->getName();

            if (stristr($name, 'mysql') !== false) {
                $this->serverType = 'mysql';
            } elseif (stristr($name, 'postgre') !== false) {
                $this->serverType = 'postgresql';
            } elseif (stristr($name, 'pgsql') !== false) {
                $this->serverType = 'postgresql';
            } elseif (stristr($name, 'oracle') !== false) {
                $this->serverType = 'oracle';
            } elseif (stristr($name, 'sqlite') !== false) {
                $this->serverType = 'sqlite';
            } elseif (stristr($name, 'sqlsrv') !== false) {
                $this->serverType = 'mssql';
            } elseif (stristr($name, 'sqlazure') !== false) {
                $this->serverType = 'mssql';
            } elseif (stristr($name, 'mssql') !== false) {
                $this->serverType = 'mssql';
            } else {
                $this->serverType = $name;
            }
        }

        return $this->serverType;
    }

    /**
     * Возвращает нулевое или нулевое представление временной метки для драйвера базы данных.
     *
     * @return  string
     *
     */
    public function getNullDate(): string {
        return $this->nullDate;
    }

    /**
     * Возвращает общий префикс таблицы для драйвера базы данных.
     *
     * @return  string  Префикс общей таблицы базы данных.
     *
     */
    public function getPrefix(): string {
        return $this->tablePrefix;
    }

    /**
     * Получает объект класса экспортера.
     *
     * @return  DatabaseExporter  Объект-экспортер.
     *
     * @throws  RuntimeException
     */
    public function getExporter(): DatabaseExporter {
        return $this->factory->getExporter($this->name, $this);
    }

    /**
     * Получает объект класса импортера.
     *
     * @return  DatabaseImporter
     *
     */
    public function getImporter(): DatabaseImporter {
        return $this->factory->getImporter($this->name, $this);
    }

    /**
    * Возвращает текущий объект запроса или новый объект DatabaseQuery.
    *
    * @return  DatabaseQuery
    */
    public function getQuery(): QueryInterface {
        return $this->sql;
    }

    /**
     * Возвращает новый итератор для текущего запроса.
     *
     * @param string|null $column  Столбец параметров, который будет использоваться в качестве ключа итератора.
     * @param string      $class   Класс возвращаемого объекта.
     *
     * @return  DatabaseIterator
     *
     */
    public function getIterator(string $column = null, string $class = stdClass::class): DatabaseIterator {
        if (!$this->executed) {
            $this->execute();
        }

        /**
         * Вызов setQuery free — это оператор итератора, который сломает итератор.
         * Поэтому мы устанавливаем для оператора значение null, чтобы freeResult
         * на этот оператор не имел никакого влияния.
         * Если вы отключите объект итератора, это закроет курсор и освободит результат.
         */
        return $this->factory->getIterator($this->name, $this->statement, $column, $class);
    }

    /**
     * Показывает оператор таблицы CREATE, создающий данные таблицы.
     *
     * @param   mixed  $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array  Список созданных SQL для таблиц.
     *
     * @throws  RuntimeException
     */
    abstract public function getTableCreate(mixed $tables): array;

    /**
     * Определите, поддерживает ли ядро базы данных кодировку символов UTF-8.
     *
     * @return  boolean  True, если ядро базы данных поддерживает кодировку символов UTF-8.
     *
     */
    public function hasUtfSupport(): bool {
        return $this->utf;
    }

    /**
     * Вставляет строку в таблицу на основе свойств объекта.
     *
     * @param string      $table   Имя таблицы базы данных, в которую требуется вставить.
     * @param object      $object  Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param string|null $key     Имя первичного ключа. Если указано, свойство объекта обновляется.
     *
     * @return  boolean
     *
     * @throws  RuntimeException
     */
    public function insertObject(string $table, object &$object, ?string $key = null): bool {
        $fields       = [];
        $values       = [];
        $tableColumns = $this->getTableColumns($table);

        foreach (get_object_vars($object) as $k => $v) {
            if (!array_key_exists($k, $tableColumns)) continue;
            if (is_array($v) || \is_object($v) || $v === null) continue;
            if ($k[0] === '_') continue;

            $fields[] = $this->quoteName($k);
            $values[] = $this->quote($v);
        }

        $query = $this->createQuery()
            ->insert($this->quoteName($table))
            ->columns($fields)
            ->values(implode(',', $values));

        $this->setQuery($query)->execute();

        $id = $this->insertid();

        if ($key && $id) {
            $object->$key = $id;
        }

        return true;
    }

    /**
     * Метод проверки того, поддерживается ли установленная версия базы данных драйвером базы данных.
     *
     * @return  boolean  True, если версия базы данных поддерживается.
     *
     */
    public function isMinimumVersion(): bool {
        return version_compare($this->getVersion(), $this->getMinimum()) >= 0;
    }

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде ассоциативного массива ['field_name' => 'row_value'].
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadAssoc(): mixed {
        $this->connect();

        $ret = null;
        $this->execute();

        $array = $this->fetchAssoc();

        if ($array) {
            $ret = $array;
        }

        $this->freeResult();

        return $ret;
    }

    /**
     * Метод для получения массива строк набора результатов из запроса к базе данных,
     * где каждая строка представляет собой ассоциативный массив ['field_name' => 'row_value'].
     * Массив строк может быть дополнительно связан с именем поля,
     * но по умолчанию это последовательный числовой массив.
     *
     * ПРИМЕЧАНИЕ. Выбор ключа массива результатов с помощью неуникального имени поля может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string|null $key     Имя поля, в котором необходимо ввести массив результатов.
     * @param string|null $column  Необязательное имя столбца. Вместо всей строки будет только значение этого столбца.
     *
     * @return  array Массив результатов.
     *
     * @throws  RuntimeException
     */
    public function loadAssocList(string $key = null, string $column = null): array {
        $this->connect();

        $array = [];

        $this->execute();

        while ($row = $this->fetchAssoc()) {
            $value = $column ? ($row[$column] ?? $row) : $row;

            if ($key) {
                $array[$row[$key]] = $value;
            } else {
                $array[] = $value;
            }
        }

        $this->freeResult();

        return $array;
    }

    /**
     * Метод для получения массива значений из поля $offset в каждой строке набора результатов запроса к базе данных.
     *
     * @param integer $offset  Смещение строки, которое будет использоваться для построения массива результатов.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadColumn(int $offset = 0): mixed {
        $this->connect();

        $array = [];
        // Выполним запрос и получим курсор набора результатов.
        $this->execute();
        // Получим все строки из набора результатов в виде массивов.
        while ($row = $this->fetchArray()) {
            $array[] = $row[$offset];
        }

        // Освободим системные ресурсы.
        $this->freeResult();

        return $array;
    }

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде объекта.
     *
     * @param string $class  Имя класса, которое будет использоваться для возвращаемого объекта строки.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadObject(string $class = stdClass::class): mixed {
        $this->connect();

        $ret = null;

        if ($this->statement) {
            $fetchMode = $class === stdClass::class ? FetchMode::STANDARD_OBJECT : FetchMode::CUSTOM_OBJECT;

            if ($fetchMode === FetchMode::STANDARD_OBJECT) {
                $this->statement->setFetchMode($fetchMode);
            } else {
                $this->statement->setFetchMode($fetchMode, $class);
            }
        }

        $this->execute();

        $object = $this->fetchObject();

        if ($object) {
            $ret = $object;
        }

        $this->freeResult();

        return $ret;
    }

    /**
     * Метод для получения массива строк результирующего набора из запроса к базе данных,
     * где каждая строка является объектом.
     * Массив объектов может быть дополнительно связан с именем поля,
     * но по умолчанию это последовательный числовой массив.
     *
     * @note Выбор ключа массива результатов с помощью неуникального имени поля может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string $key    Имя поля, в котором необходимо ввести массив результатов.
     * @param string $class  Имя класса, которое будет использоваться для возвращаемых объектов строк.
     *
     * @return  array|null  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadObjectList(string $key = '', string $class = stdClass::class): array|null {
        $this->connect();

        $array = [];
        if ($this->statement) {
            $fetchMode = $class === stdClass::class ? FetchMode::STANDARD_OBJECT : FetchMode::CUSTOM_OBJECT;

            if ($fetchMode === FetchMode::STANDARD_OBJECT) {
                $this->statement->setFetchMode($fetchMode);
            } else {
                $this->statement->setFetchMode($fetchMode, $class);
            }
        }

        $this->execute();

        while ($row = $this->fetchObject()) {
            if ($key) {
                $array[$row->$key] = $row;
            } else {
                $array[] = $row;
            }
        }

        $this->freeResult();

        return $array;
    }

    /**
     * Метод для получения первого поля первой строки набора результатов из запроса к базе данных.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadResult(): mixed {
        $this->connect();

        $ret = null;

        $this->execute();

        $row = $this->fetchArray();

        if ($row) {
            $ret = $row[0];
        }

        $this->freeResult();

        return $ret;
    }

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде массива.
     *
     * Столбцы индексируются численно, поэтому первый столбец в наборе результатов будет доступен через <var>$row[0]</var> и т.д.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     *
     * @throws  RuntimeException
     */
    public function loadRow(): mixed {
        $this->connect();

        $ret = null;

        $this->execute();

        $row = $this->fetchArray();

        if ($row) {
            $ret = $row;
        }

        $this->freeResult();

        return $ret;
    }

    /**
     * Метод для получения массива строк результирующего набора из запроса к базе данных,
     * где каждая строка является массивом.
     * Массив объектов может быть дополнительно связан с помощью смещения поля,
     * но по умолчанию используется последовательный числовой массив.
     *
     * @note Выбор ключа массива результатов с помощью неуникального поля может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string|null $key  Имя поля, в котором необходимо ввести массив результатов.
     *
     * @return  array   Массив результатов.
     *
     * @throws  RuntimeException
     */
    public function loadRowList(string $key = null): array {
        $this->connect();

        $array = [];

        $this->execute();

        while ($row = $this->fetchArray()) {
            if ($key !== null) {
                $array[$row[$key]] = $row;
            } else {
                $array[] = $row;
            }
        }

        $this->freeResult();

        return $array;
    }

    /**
     * Подготавливает оператор SQL к выполнению
     *
     * @param   string  $query  SQL-запрос, который необходимо подготовить.
     *
     * @return  StatementInterface
     * @throws  PrepareStatementFailureException
     */
    abstract protected function prepareStatement(string $query): StatementInterface;

    /**
     * Псевдоним метода цитаты.
     *
     * @param array|string $text    Строка или массив строк для цитирования.
     * @param boolean      $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  string  Входная строка в кавычках.
     *
     */
    public function q(array|string $text, bool $escape = true): array|string {
        return $this->quote($text, $escape);
    }

    /**
     * Заключает в кавычки и, при необходимости, экранирует строку, соответствующую требованиям базы данных для использования в запросах к базе данных.
     *
     * @param array|string $text    Строка или массив строк для цитирования.
     * @param boolean      $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  array|string  Входная строка в кавычках.
     *
     */
    public function quote(array|string $text, bool $escape = true): array|string {
        if (is_array($text)) {
            foreach ($text as $k => $v) {
                $text[$k] = $this->quote($v, $escape);
            }

            return $text;
        }

        return '\'' . ($escape ? $this->escape($text) : $text) . '\'';
    }

    /**
     * Заключает в кавычки двоичную строку, соответствующую требованиям к базе данных, для использования в запросах к базе данных.
     *
     * @param string $data  Двоичная строка для цитирования.
     *
     * @return  string  Входная строка в двоичных кавычках.
     */
    public function quoteBinary(string $data): string {
        return "X'" . bin2hex($data) . "'";
    }

    /**
     * Заменяет специальный заполнитель, представляющий двоичное поле, исходной строкой.
     *
     * @param string $data  Закодированная строка или ресурс.
     *
     * @return  string  Исходная строка.
     */
    public function decodeBinary(string $data): string {
        return $data;
    }

    /**
     * Псевдоним для метода quoteName.
     *
     * @param array|string      $name  Имя идентификатора, заключаемое в кавычки, или массив имен идентификаторов, заключаемый в кавычки.
     *                                 Каждый тип поддерживает имя в виде точечной записи.
     * @param array|string|null $as    Часть запроса AS, связанная с $name. Это может быть строка или массив,
     *                                 в последнем случае длина должна быть такой же, как $name;
     *                                 если значение, равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     *
     */
    public function qn(array|string $name, array|string $as = null): array|string {
        return $this->quoteName($name, $as);
    }

    /**
     * Заключает имя идентификатора оператора SQL, такое, как имена столбцов,
     * таблиц или баз данных, в кавычки, чтобы предотвратить риски внедрения
     * и конфликты зарезервированных слов.
     *
     * @param array|string      $name  Имя идентификатора, заключаемое в кавычки, или массив имен идентификаторов, заключаемый в кавычки.
     *                                 Каждый тип поддерживает имя в виде точечной записи.
     * @param array|string|null $as    Часть запроса AS, связанная с $name. Это может быть строка или массив,
     *                                 в последнем случае длина должна быть такой же, как $name;
     *                                 если значение, равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     *
     */
    public function quoteName(array|string $name, array|string $as = null): array|string {
        if (\is_string($name)) {
            $name = $this->quoteNameString($name);

            if ($as !== null) {
                $name .= ' AS ' . $this->quoteNameString($as, true);
            }

            return $name;
        }

        $fin = [];

        if ($as === null) {
            foreach ($name as $str) {
                $fin[] = $this->quoteName($str);
            }
        } elseif (is_array($name) && (\count($name) === \count($as))) {
            $count = \count($name);

            for ($i = 0; $i < $count; $i++) {
                $fin[] = $this->quoteName($name[$i], $as[$i]);
            }
        }

        return $fin;
    }

    /**
     * Строка цитаты, полученная в результате вызова quoteName.
     *
     * @param string  $name          Имя идентификатора, которое будет заключено в кавычки.
     * @param boolean $asSinglePart  Рассматривайте имя как единую часть идентификатора.
     *
     * @return  string  Строка идентификатора в кавычках.
     */
    protected function quoteNameString(string $name, bool $asSinglePart = false): string {
        $q    = $this->nameQuote . $this->nameQuote;
        $name = str_replace($q[1], $q[1] . $q[1], $name);

        if ($asSinglePart) {
            return $q[0] . $name . $q[1];
        }

        return $q[0] . str_replace('.', "$q[1].$q[0]", $name) . $q[1];
    }

    /**
     * Эта функция заменяет строковый идентификатор настроенным префиксом таблицы.
     *
     * @param string $sql     Оператор SQL, который нужно подготовить.
     * @param string $prefix  Префикс таблицы.
     *
     * @return  string  Обработанный оператор SQL.
     *
     */
    public function replacePrefix(string $sql, string $prefix = '#__'): string {
        $escaped   = false;
        $startPos  = 0;
        $quoteChar = '';
        $literal   = '';

        $sql = trim($sql);
        $n   = strlen($sql);

        while ($startPos < $n) {
            $ip = strpos($sql, $prefix, $startPos);

            if ($ip === false) {
                break;
            }

            $j = strpos($sql, "'", $startPos);
            $k = strpos($sql, '"', $startPos);

            if (($k !== false) && (($k < $j) || ($j === false))) {
                $quoteChar = '"';
                $j         = $k;
            } else {
                $quoteChar = "'";
            }

            if ($j === false) {
                $j = $n;
            }

            $literal .= str_replace($prefix, $this->tablePrefix, substr($sql, $startPos, $j - $startPos));
            $startPos = $j;

            $j = $startPos + 1;

            if ($j >= $n) {
                break;
            }

            while (true) {
                $k       = strpos($sql, $quoteChar, $j);
                $escaped = false;

                if ($k === false) {
                    break;
                }

                $l = $k - 1;

                while ($l >= 0 && $sql[$l] === '\\') {
                    $l--;
                    $escaped = !$escaped;
                }

                if ($escaped) {
                    $j = $k + 1;

                    continue;
                }

                break;
            }

            if ($k === false) {
                // Ошибка в запросе – нет конечной кавычки; игнорировать
                break;
            }

            $literal .= substr($sql, $startPos, $k - $startPos + 1);
            $startPos = $k + 1;
        }

        if ($startPos < $n) {
            $literal .= substr($sql, $startPos, $n - $startPos);
        }

        return $literal;
    }

    /**
     * Возвращает монитор запросов.
     *
     * @return  QueryMonitorInterface|null  Монитор запросов или значение NULL, если не установлено.
     */
    public function getMonitor(): ?QueryMonitorInterface {
        return $this->monitor;
    }

    /**
     * Устанавливает монитор запросов.
     *
     * @param   QueryMonitorInterface|null  $monitor  Монитор запросов.
     *
     * @return  $this
     */
    public function setMonitor(?QueryMonitorInterface $monitor = null): static {
        $this->monitor = $monitor;
        return $this;
    }

    /**
     * Устанавливает строку инструкции SQL для последующего выполнения.
     *
     * @param mixed $query   Оператор SQL, который необходимо задать либо как объект Query, либо как строку.
     *
     * @return  $this
     *
     * @throws  InvalidArgumentException
     */
    public function setQuery(mixed $query): static {
        $this->connect();

        $this->freeResult();

        if (\is_string($query)) {
            $query = $this->createQuery()->setQuery($query);
        } elseif (!($query instanceof QueryInterface)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Запрос должен быть строкой или экземпляром %s, был задан %s.',
                    QueryInterface::class,
                    \gettype($query) === 'object' ? (\get_class($query) . ' instance') : \gettype($query)
                )
            );
        }

        $sql = $this->replacePrefix((string) $query);
        $this->statement = $this->prepareStatement($sql);
        $this->sql = $query;

        return $this;
    }

    /**
     * Настраивает соединение на использование кодировки символов UTF-8.
     *
     * @return  boolean
     *
     */
    abstract public function setUtf(): bool;

    /**
     * Метод удаления всех записей из таблицы.
     *
     * @param string $table  Таблица, которую нужно очистить
     *
     * @return  void
     *
     * @throws  RuntimeException
     */
    public function truncateTable(string $table): void {
        $this->setQuery('TRUNCATE TABLE ' . $this->quoteName($table))
            ->execute();
    }

    /**
     * Обновляет строку в таблице на основе свойств объекта.
     *
     * @param string              $table   Имя таблицы базы данных, которую необходимо обновить.
     * @param object              $object  Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param array|string|object $key     Имя первичного ключа.
     * @param boolean             $nulls   Значение true для обновления пустых полей или значение false для их игнорирования.
     *
     * @return  boolean
     *
     * @throws  RuntimeException
     */
    public function updateObject(
        string $table,
        object &$object,
        array|object|string $key,
        bool $nulls = false
    ): bool {

        $fields       = [];
        $where        = [];
        $tableColumns = $this->getTableColumns($table);

        if (\is_string($key)) {
            $key = [$key];
        }

        if (\is_object($key)) {
            $key = (array) $key;
        }

        $statement = 'UPDATE ' . $this->quoteName($table) . ' SET %s WHERE %s';

        foreach (get_object_vars($object) as $k => $v) {
            if (!array_key_exists($k, $tableColumns)) continue;
            if (is_array($v) || \is_object($v) || $k[0] === '_') continue;
            if (in_array($k, $key, true)) {
                $where[] = $this->quoteName($k) . ($v === null ? ' IS NULL' : ' = ' . $this->quote($v));

                continue;
            }

            if ($v === null) {
                if ($nulls) {
                    $val = 'NULL';
                } else {

                    continue;
                }
            } else {
                $val = $this->quote($v);
            }

            $fields[] = $this->quoteName($k) . '=' . $val;
        }

        if (empty($fields)) {
            return true;
        }

        $this->setQuery(sprintf($statement, implode(',', $fields), implode(' AND ', $where)))->execute();

        return true;
    }
}
