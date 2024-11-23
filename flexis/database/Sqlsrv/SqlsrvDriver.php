<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlsrv;

use Flexis\Database\DatabaseDriver;
use Flexis\Database\DatabaseEvents;
use Flexis\Database\Event\ConnectionEvent;
use Flexis\Database\Exception\ConnectionFailureException;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Database\Exception\UnsupportedAdapterException;
use Flexis\Database\StatementInterface;

/**
 * Драйвер базы данных SQL Server
 *
 * @link   https://www.php.net/manual/ru/book.sqlsrv.php
 */
class SqlsrvDriver extends DatabaseDriver {
    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'sqlsrv';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких как имена таблиц, имена полей и т. д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках, иначе первый символ будет использоваться для открывающей кавычки, а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = '[]';

    /**
     * Нулевое или нулевое представление отметки времени для драйвера базы данных.
     *
     * @var    string|null
     */
    protected ?string $nullDate = '1900-01-01 00:00:00';

    /**
     * Минимальная поддерживаемая версия базы данных.
     *
     * @var    string|null
     */
    protected static ?string $dbMinimum = '11.0.2100.60';

    /**
     * Проверяет, доступен ли соединитель SQLSRV.
     *
     * @return  boolean
     *
     */
    public static function isSupported(): bool {
        return \function_exists('sqlsrv_connect');
    }

    /**
     * Конструктор.
     *
     * @param   array  $options  Список опций, используемых для настройки соединения.
     *
     */
    public function __construct(array $options) {
        $options['host']     = $options['host'] ?? 'localhost';
        $options['user']     = $options['user'] ?? '';
        $options['password'] = $options['password'] ?? '';
        $options['database'] = $options['database'] ?? '';
        $options['select']   = !isset($options['select']) || (bool)$options['select'];
        $options['encrypt']  = !isset($options['encrypt']) || (bool)$options['encrypt'];

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
        if ($this->connection) {
            return;
        }

        if (!static::isSupported()) {
            throw new UnsupportedAdapterException('Расширение PHP sqlsrv_connect недоступно.');
        }

        $config = [
            'Database'             => $this->options['database'],
            'uid'                  => $this->options['user'],
            'pwd'                  => $this->options['password'],
            'CharacterSet'         => 'UTF-8',
            'ReturnDatesAsStrings' => true,
            'Encrypt'              => $this->options['encrypt'],
        ];

        if (!($this->connection = @sqlsrv_connect($this->options['host'], $config))) {
            throw new ConnectionFailureException('Не удалось подключиться к SQL Server.');
        }

        sqlsrv_configure('WarningsReturnAsErrors', 0);

        if ($this->options['select'] && !empty($this->options['database'])) {
            $this->select($this->options['database']);
        }

        $this->dispatchEvent(new ConnectionEvent(DatabaseEvents::POST_CONNECT, $this));
    }

    /**
     * Отключает базу данных.
     *
     * @return  void
     *
     */
    public function disconnect(): void {
        if (\is_resource($this->connection)) {
            sqlsrv_close($this->connection);
        }

        parent::disconnect();
    }

    /**
     * Возвращает ограничения таблицы.
     *
     * @param string $tableName  Имя таблицы базы данных.
     *
     * @return  array  Любые ограничения, доступные для таблицы.
     *
     */
    protected function getTableConstraints(string $tableName): array {
        $this->connect();

        return $this->setQuery('SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_NAME = ' . $this->quote($tableName))
            ->loadColumn();
    }

    /**
     * Переименуйте ограничения.
     *
     * @param array       $constraints  Массив (строки) ограничений таблицы.
     * @param string|null $prefix       Строка.
     * @param string|null $backup       A string.
     *
     * @return  void
     *
     */
    protected function renameConstraints(array $constraints = [], string $prefix = null, string $backup = null): void {
        $this->connect();

        foreach ($constraints as $constraint) {
            $this->setQuery('sp_rename ' . $constraint . ',' . str_replace($prefix, $backup, $constraint))
                ->execute();
        }
    }

    /**
     * Метод экранирования строки для использования в инструкции SQL.
     *
     * Экранирование для MSSQL не обрабатывается в драйвере, хотя это было бы неплохо.  Из-за этого нам нужно самим справиться с побегом.
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
            return str_replace(',', '.', $text);
        }

        $result = str_replace("'", "''", $text);
        $result = str_replace("\0", "' + CHAR(0) + N'", $result);
        $result = str_replace(
            ["\\\n",     "\\\r",     "\\\\\r\r\n"],
            ["\\\\\n\n", "\\\\\r\r", "\\\\\r\n\r\n"],
            $result
        );

        if ($extra) {
            $result = str_replace(
                ['[',   '_',   '%'],
                ['[[]', '[_]', '[%]'],
                $result
            );
        }

        return $result;
    }

    /**
     * Заключает в кавычки и, при необходимости, экранирует строку, соответствующую требованиям базы данных для использования в запросах к базе данных.
     *
     * @param array|string $text    Строка или массив строк для цитирования.
     * @param boolean      $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  string  Входная строка в кавычках.
     */
    public function quote(array|string $text, bool $escape = true): string {
        if (\is_array($text)) {
            return parent::quote($text, $escape);
        }

        return 'N\'' . ($escape ? $this->escape($text) : $text) . '\'';
    }

    /**
     * Заключает в кавычки двоичную строку, соответствующую требованиям к базе данных, для использования в запросах к базе данных.
     *
     * @param string $data  Двоичная строка для цитирования.
     *
     * @return  string  Входная строка в двоичных кавычках.
     */
    public function quoteBinary(string $data): string {
        return '0x' . bin2hex($data);
    }

    /**
     * Определяет, активно ли соединение с сервером.
     *
     * @return  boolean  True, если оно подключено к ядру базы данных.
     *
     */
    public function connected(): bool {
        return true;
    }

    /**
     * Удаляет таблицу из базы данных.
     *
     * @param string  $table     Имя таблицы базы данных, которую необходимо удалить.
     * @param boolean $ifExists  При необходимости укажите, что таблица должна существовать до ее удаления.
     *
     * @return  $this
     *
     */
    public function dropTable(string $table, bool $ifExists = true): static {
        $this->connect();

        if ($ifExists) {
            $this->setQuery(
                'IF EXISTS(SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = '
                    . $this->quote($table) . ') DROP TABLE ' . $table
            );
        } else {
            $this->setQuery('DROP TABLE ' . $table);
        }

        $this->execute();

        return $this;
    }

    /**
     * Метод получения используемых параметров сортировки базы данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые базой данных, или логическое значение false, если не поддерживается.
     *
     */
    public function getCollation(): bool|string {
        return 'MSSQL UTF-8 (UCS2)';
    }

    /**
     * Метод получения используемых параметров сортировки соединения с базой данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые соединением с базой данных (строка), или логическое значение false, если не поддерживается.
     *
     * @throws  \RuntimeException
     */
    public function getConnectionCollation(): string|bool {
        return 'MSSQL UTF-8 (UCS2)';
    }

    /**
     * Метод для получения сведений об используемом шифровании базы данных (шифр и протокол).
     *
     * @return  string  Подробности шифрования базы данных.
     *
     * @throws  \RuntimeException
     */
    public function getConnectionEncryption(): string {
        // TODO: Not fake this
        return '';
    }

    /**
     * Метод проверки того, поддерживается ли шифрование TLS-соединений базы данных.
     *
     * @return  boolean  Поддерживает ли база данных шифрование соединений TLS.
     */
    public function isConnectionEncryptionSupported(): bool {
        return false;
    }

    /**
     * Извлекает информацию о полях данных таблиц.
     *
     * @param string  $table     Имя таблицы
     * @param boolean $typeOnly  Значение true, чтобы возвращать только типы полей.
     *
     * @return  array  Массив полей.
     *
     * @throws  \RuntimeException
     */
    public function getTableColumns(string $table, bool $typeOnly = true): array {
        $result = [];

        $table_temp = $this->replacePrefix((string) $table);

        $this->setQuery(
            'SELECT column_name as Field, data_type as Type, is_nullable as \'Null\', column_default as \'Default\'' .
            ' FROM information_schema.columns WHERE table_name = ' . $this->quote($table_temp)
        );
        $fields = $this->loadObjectList();

        if ($typeOnly) {
            foreach ($fields as $field) {
                $result[$field->Field] = preg_replace('/[(0-9)]/', '', $field->Type);
            }
        } else {
            foreach ($fields as $field) {
                $field->Default        = preg_replace("/(^(\(\(|\('|\(N'|\()|(('\)|(?<!\()\)\)|\))$))/i", '', $field->Default);
                $result[$field->Field] = $field;
            }
        }

        return $result;
    }

    /**
     * Показывает оператор таблицы CREATE, создающий данные таблицы.
     *
     * Это не поддерживается MSSQL.
     *
     * @param mixed $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array  Список созданных SQL для таблиц.
     *
     * @throws  \RuntimeException
     */
    public function getTableCreate(mixed $tables): array {
        $this->connect();

        return [];
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

        return [];
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

        return $this->setQuery('SELECT name FROM ' . $this->getDatabase() . '.sys.Tables WHERE type = \'U\';')->loadColumn();
    }

    /**
     * Возвращает версию соединителя базы данных.
     *
     * @return  string  Версия соединителя базы данных.
     *
     */
    public function getVersion(): string {
        $this->connect();

        $version = sqlsrv_server_info($this->connection);

        return $version['SQLServerVersion'];
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
     * @throws  \RuntimeException
     */
    public function insertObject(string $table, object &$object, string $key = null): bool {
        $fields       = [];
        $values       = [];
        $tableColumns = $this->getTableColumns($table);
        $statement    = 'INSERT INTO ' . $this->quoteName($table) . ' (%s) VALUES (%s)';

        foreach (get_object_vars($object) as $k => $v) {
            if (!\array_key_exists($k, $tableColumns)) {
                continue;
            }

            if (\is_array($v) || \is_object($v) || $v === null) {
                continue;
            }

            if (!$this->checkFieldExists($table, $k)) {
                continue;
            }

            if ($k[0] === '_') {
                continue;
            }

            if ($k === $key && $key == 0) {
                continue;
            }

            $fields[] = $this->quoteName($k);
            $values[] = $this->quote($v);
        }

        $this->setQuery(sprintf($statement, implode(',', $fields), implode(',', $values)))->execute();

        $id = $this->insertid();

        if ($key && $id) {
            $object->$key = $id;
        }

        return true;
    }

    /**
     * Метод для получения автоматически увеличивающегося значения из последнего оператора INSERT.
     *
     * @return  integer  Значение поля автоинкремента из последней вставленной строки.
     *
     */
    public function insertid(): int {
        $this->connect();
        $this->setQuery('SELECT @@IDENTITY');

        return (int) $this->loadResult();
    }

    /**
     * Выполняет оператор SQL.
     *
     * @return  boolean
     *
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
        $n   = \strlen($sql);

        while ($startPos < $n) {
            $ip = strpos($sql, $prefix, $startPos);

            if ($ip === false) {
                break;
            }

            $j = strpos($sql, "N'", $startPos);
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
     * Выбирает базу данных для использования.
     *
     * @param string $database  Имя базы данных, которую необходимо выбрать для использования.
     *
     * @return  boolean  True, если база данных была успешно выбрана.
     *
     * @throws  ConnectionFailureException
     */
    public function select(string $database): bool {
        $this->connect();

        if (!$database) {
            return false;
        }

        if (!sqlsrv_query($this->connection, 'USE [' . $database . ']', null, ['scrollable' => \SQLSRV_CURSOR_STATIC])) {
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
        return true;
    }

    /**
     * Метод фиксации транзакции.
     *
     * @param boolean $toSavepoint  Если true, сохраните последнюю точку сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function transactionCommit(bool $toSavepoint = false): void {
        $this->connect();

        if (!$toSavepoint || $this->transactionDepth <= 1) {
            $this->setQuery('COMMIT TRANSACTION')->execute();

            $this->transactionDepth = 0;

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
        $this->connect();

        if (!$toSavepoint || $this->transactionDepth <= 1) {
            $this->setQuery('ROLLBACK TRANSACTION')->execute();

            $this->transactionDepth = 0;

            return;
        }

        $savepoint = 'SP_' . ($this->transactionDepth - 1);
        $this->setQuery('ROLLBACK TRANSACTION ' . $this->quoteName($savepoint))->execute();

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
            $this->setQuery('BEGIN TRANSACTION')->execute();

            $this->transactionDepth = 1;

            return;
        }

        $savepoint = 'SP_' . $this->transactionDepth;
        $this->setQuery('BEGIN TRANSACTION ' . $this->quoteName($savepoint))->execute();

        $this->transactionDepth++;
    }

    /**
     * Метод проверки наличия поля в таблице.
     *
     * @param string $table  Таблица, в которой нужно проверить поле.
     * @param string $field  Поле для проверки.
     *
     * @return  boolean  True, если поле существует в таблице.
     *
     */
    protected function checkFieldExists(string $table, string $field): bool {
        $this->connect();

        $table = $this->replacePrefix((string) $table);
        $this->setQuery(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$table' AND COLUMN_NAME = '$field' ORDER BY ORDINAL_POSITION"
        );

        return (bool) $this->loadResult();
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
        return new SqlsrvStatement($this->connection, $query);
    }

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable  Имя таблицы, которую нужно переименовать.
     * @param string      $newTable  Новое имя таблицы.
     * @param string|null $backup    Префикс таблицы.
     * @param string|null $prefix    F или table — используется для переименования ограничений в базах данных, отличных от MySQL.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function renameTable(string $oldTable, string $newTable, ?string $backup = null, ?string $prefix = null): static {
        $constraints = [];

        if ($prefix !== null && $backup !== null) {
            $constraints = $this->getTableConstraints($oldTable);
        }

        if (!empty($constraints)) {
            $this->renameConstraints($constraints, $prefix, $backup);
        }

        $this->setQuery("sp_rename '" . $oldTable . "', '" . $newTable . "'");

        $this->execute();

        return $this;
    }

    /**
     * Блокирует таблицу в базе данных.
     *
     * @param string $tableName  Имя таблицы, которую нужно заблокировать.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function lockTable(string $tableName): static {
        return $this;
    }

    /**
     * Разблокирует таблицы в базе данных.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function unlockTables(): static {
        return $this;
    }
}
