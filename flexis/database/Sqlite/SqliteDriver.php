<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlite;

use Flexis\Database\Pdo\PdoDriver;

/**
 * Драйвер базы данных SQLite, поддерживающий соединения на основе PDO
 *
 * @link   https://www.php.net/manual/ru/ref.pdo-sqlite.php
 */
class SqliteDriver extends PdoDriver {
    /**
     * Имя драйвера базы данных.
     *
     * @var    string|null
     */
    public ?string $name = 'sqlite';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких, как имена таблиц, имена полей и т.д.
     *
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках,
     * иначе первый символ будет использоваться для открывающей кавычки, а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = '`';

    /**
     * Деструктор.
     */
    public function __destruct() {
        $this->disconnect();
    }

    /**
     * Изменить набор символов базы данных.
     *
     * @param string|null $dbName  Имя базы данных, которое будет изменено.
     *
     * @return  boolean|resource
     *
     * @throws  \RuntimeException
     */
    public function alterDbCharacterSet(?string $dbName = null): bool {
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

        parent::connect();

        $this->connection->sqliteCreateFunction(
            'ROW_NUMBER',
            function ($init = null) {
                static $rownum, $partition;

                if ($init !== null) {
                    $rownum    = $init;
                    $partition = null;

                    return $rownum;
                }

                $args = \func_get_args();
                array_shift($args);

                $partitionBy = $args ? implode(',', $args) : null;

                if ($partitionBy === null || $partitionBy === $partition) {
                    $rownum++;
                } else {
                    $rownum    = 1;
                    $partition = $partitionBy;
                }

                return $rownum;
            }
        );
    }

    /**
     * Создаёт новую базу данных, используя информацию из объекта $options.
     *
     * @param \stdClass|null  $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных. Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean         $utf      Истинно, если база данных поддерживает набор символов UTF-8.
     *
     * @return  boolean|resource
     *
     * @throws  \RuntimeException
     */
    public function createDatabase(?\stdClass $options = null, bool $utf = true): bool {
        return true;
    }

    /**
     * Метод экранирования строки для использования в инструкции SQLite.
     *
     * @note: Использование объектов запроса со связанными переменными предпочтительнее, чем приведенное ниже.
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
            return str_replace(',', '.', $text);
        }

        return \SQLite3::escapeString($text);
    }

    /**
     * Метод получения используемых параметров сортировки базы данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые базой данных, или логическое значение false, если не поддерживается.
     *
     */
    public function getCollation(): bool|string {
        return false;
    }

    /**
     * Метод получения используемых параметров сортировки соединения с базой данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые соединением с базой данных (строка), или логическое значение false, если не поддерживается.
     *
     * @throws  \RuntimeException
     */
    public function getConnectionCollation(): string|bool {
        return false;
    }

    /**
     * Метод для получения сведений об используемом шифровании базы данных (шифр и протокол).
     *
     * @return  string  Подробности шифрования базы данных.
     *
     * @throws  \RuntimeException
     */
    public function getConnectionEncryption(): string {
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
     * Показывает оператор таблицы CREATE, создающий данные таблицы.
     *
     * @note: Кажется, нет поддержки в SQLite
     *
     * @param mixed $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array  Список созданных SQL для таблиц.
     *
     * @throws  \RuntimeException
     */
    public function getTableCreate(mixed $tables): array {
        $this->connect();

        return (array) $tables;
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

        $columns = [];

        $fieldCasing = $this->getOption(\PDO::ATTR_CASE);

        $this->setOption(\PDO::ATTR_CASE, \PDO::CASE_UPPER);

        $table = strtoupper($table);

        $fields = $this->setQuery('pragma table_info(' . $table . ')')->loadObjectList();

        if ($typeOnly) {
            foreach ($fields as $field) {
                $columns[$field->NAME] = $field->TYPE;
            }
        } else {
            foreach ($fields as $field) {
                $columns[$field->NAME] = (object) [
                    'Field'   => $field->NAME,
                    'Type'    => $field->TYPE,
                    'Null'    => $field->NOTNULL == '1' ? 'NO' : 'YES',
                    'Default' => $field->DFLT_VALUE,
                    'Key'     => $field->PK != '0' ? 'PRI' : '',
                ];
            }
        }

        $this->setOption(\PDO::ATTR_CASE, $fieldCasing);

        return $columns;
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

        $keys = [];

        $fieldCasing = $this->getOption(\PDO::ATTR_CASE);

        $this->setOption(\PDO::ATTR_CASE, \PDO::CASE_UPPER);

        $tables = strtoupper($tables);

        $rows = $this->setQuery('pragma table_info( ' . $tables . ')')->loadObjectList();

        foreach ($rows as $column) {
            if ($column->PK == 1) {
                $keys[$column->NAME] = $column;
            }
        }

        $this->setOption(\PDO::ATTR_CASE, $fieldCasing);

        return $keys;
    }

    /**
     * Метод получения массива всех таблиц в базе данных (схема).
     *
     * @return  array   Массив всех таблиц в базе данных.
     *
     * @throws  \RuntimeException
     */
    public function getTableList(): array {
        $this->connect();

        $type = 'table';

        $query = $this->createQuery()
            ->select('name')
            ->from('sqlite_master')
            ->where('type = :type')
            ->bind(':type', $type)
            ->order('name');

        return $this->setQuery($query)->loadColumn();
    }

    /**
     * Возвращает версию соединителя базы данных.
     *
     * @return  string  Версия соединителя базы данных.
     *
     */
    public function getVersion(): string {
        $this->connect();

        return $this->setQuery('SELECT sqlite_version()')->loadResult();
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

        return true;
    }

    /**
     * Настраивает соединение на использование кодировки символов UTF-8.
     *
     * Автоматически возвращает false для драйвера Oracle, поскольку вы можете установить набор символов только при создании соединения.
     *
     * @return  boolean
     *
     */
    public function setUtf(): bool {
        $this->connect();

        return false;
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
        return $this;
    }

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable  Имя таблицы, которую нужно переименовать.
     * @param string      $newTable  Новое имя таблицы.
     * @param string|null $backup    Не используется Sqlite.
     * @param string|null $prefix    Not used by Sqlite.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function renameTable(string $oldTable, string $newTable, string $backup = null, string $prefix = null): static {
        $this->setQuery('ALTER TABLE ' . $oldTable . ' RENAME TO ' . $newTable)->execute();

        return $this;
    }

    /**
     * Метод удаления всех записей из таблицы.
     *
     * @param string $table  Таблица, которую нужно очистить.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public function truncateTable(string $table): void {
        $this->setQuery('DELETE FROM ' . $this->quoteName($table))
            ->execute();
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

    /**
     * Проверяет, доступен ли разъем PDO ODBC.
     *
     * @return  boolean
     *
     */
    public static function isSupported(): bool {
        return class_exists('\\PDO') && class_exists('\\SQLite3') && \in_array('sqlite', \PDO::getAvailableDrivers(), true);
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
            $this->setQuery('ROLLBACK TO ' . $this->quoteName($savepoint))->execute();

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
            parent::transactionStart($asSavepoint);
        } else {
            $savepoint = 'SP_' . $this->transactionDepth;
            $this->setQuery('SAVEPOINT ' . $this->quoteName($savepoint))->execute();

            $this->transactionDepth++;
        }
    }
}
