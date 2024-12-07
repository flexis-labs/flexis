<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pgsql;

use Flexis\Database\Pdo\PdoDriver;

/**
 * Драйвер базы данных PostgreSQL PDO
 */
class PgsqlDriver extends PdoDriver {
    /**
     * Имя драйвера базы данных
     *
     * @var    string|null
     */
    public ?string $name = 'pgsql';

    /**
     * Символ(ы), используемые для кавычек в именах операторов SQL, таких, как имена таблиц, имена полей и т.д.
     * Дочерние классы должны определять это по мере необходимости.
     * Если одна и та же строка символов используется для обеих сторон имени в кавычках,
     * иначе первый символ будет использоваться для открывающей кавычки, а второй — для закрывающей кавычки.
     *
     * @var    string|null
     */
    protected ?string $nameQuote = '"';

    /**
     * Нулевое или нулевое представление отметки времени для драйвера базы данных.
     * Это должно быть определено в дочерних классах, чтобы хранить подходящее значение для движка.
     *
     * @var    string|null
     */
    protected ?string $nullDate = '1970-01-01 00:00:00';

    /**
     * Минимальная поддерживаемая версия базы данных.
     *
     * @var    string|null
     */
    protected static ?string $dbMinimum = '9.4.0';

    /**
     * Оператор, используемый для конкатенации
     *
     * @var    string
     */
    protected string $concat_operator = '||';

    /**
     * Конструктор объектов базы данных
     *
     * @param array $options  Список опций, используемых для настройки соединения
     *
     */
    public function __construct(array $options) {
        $options['driver']   = 'pgsql';
        $options['host']     = $options['host'] ?? 'localhost';
        $options['user']     = $options['user'] ?? '';
        $options['password'] = $options['password'] ?? '';
        $options['database'] = $options['database'] ?? '';
        $options['port']     = $options['port'] ?? null;

        parent::__construct($options);
    }

    /**
     * При необходимости подключается к базе данных.
     *
     * @return  void
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function connect(): void {
        if ($this->getConnection()) {
            return;
        }

        parent::connect();

        $this->setQuery('SET standard_conforming_strings = off')->execute();
    }

    /**
     * Метод получения используемых параметров сортировки базы данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые базой данных, или логическое значение false, если не поддерживается.
     *
     * @throws  \RuntimeException
     */
    public function getCollation(): bool|string {
        $this->setQuery('SHOW LC_COLLATE');
        $array = $this->loadAssocList();

        return $array[0]['lc_collate'];
    }

    /**
     * Метод получения используемых параметров сортировки соединения с базой данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые соединением с базой данных (string), или логическое значение false, если не поддерживается.
     * @throws  \RuntimeException
     */
    public function getConnectionCollation(): string|bool {
        $this->setQuery('SHOW LC_COLLATE');
        $array = $this->loadAssocList();

        return $array[0]['lc_collate'];
    }

    /**
     * Метод для получения сведений об используемом шифровании базы данных (шифр и протокол).
     *
     * @return  string  Подробности шифрования базы данных.
     * @throws  \RuntimeException
     */
    public function getConnectionEncryption(): string {
        if (version_compare($this->getVersion(), '9.5', '<')) {
            return '';
        }

        $query = $this->createQuery()
            ->select($this->quoteName(['version', 'cipher']))
            ->from($this->quoteName('pg_stat_ssl'))
            ->where($this->quoteName('pid') . ' = pg_backend_pid()');

        $variables = $this->setQuery($query)->loadAssoc();

        if (!empty($variables['cipher'])) {
            return $variables['version'] . ' (' . $variables['cipher'] . ')';
        }

        return '';
    }

    /**
     * Метод проверки того, поддерживается ли шифрование TLS-соединений базы данных.
     *
     * @return  boolean  Поддерживает ли база данных шифрование соединений TLS.
     */
    public function isConnectionEncryptionSupported(): bool {
        $variables = $this->setQuery('SHOW "ssl"')->loadAssoc();

        return !empty($variables['ssl']) && $variables['ssl'] === 'on';
    }

    /**
     * Внутренняя функция для получения имени схемы по умолчанию для текущего соединения PostgreSQL.
     * Это схема, в которой таблицы создаются Flexis.
     *
     * @return  string
     */
    private function getDefaultSchema(): string {
        // Поддерживается начиная с PostgreSQL 7.3.
        $this->setQuery('SELECT (current_schemas(false))[1]');

        return $this->loadResult();
    }

    /**
     * Показывает оператор таблицы CREATE, создающий данные таблицы.
     *
     * Это не поддерживается PostgreSQL.
     *
     * @param mixed $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array  Пустой массив, поскольку эта функция не поддерживается PostgreSQL.
     *
     * @throws  \RuntimeException
     */
    public function getTableCreate(mixed $tables): array {
        return [];
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
     * @throws  \Exception
     */
    public function getTableColumns(string $table, bool $typeOnly = true): array {
        $this->connect();

        $result        = [];
        $tableSub      = $this->replacePrefix($table);
        $defaultSchema = $this->getDefaultSchema();

        $this->setQuery(
            '
			SELECT a.attname AS "column_name",
				pg_catalog.format_type(a.atttypid, a.atttypmod) as "type",
				CASE WHEN a.attnotnull IS TRUE
					THEN \'NO\'
					ELSE \'YES\'
				END AS "null",
				CASE WHEN pg_catalog.pg_get_expr(adef.adbin, adef.adrelid, true) IS NOT NULL
					THEN pg_catalog.pg_get_expr(adef.adbin, adef.adrelid, true)
				END as "Default",
				CASE WHEN pg_catalog.col_description(a.attrelid, a.attnum) IS NULL
				THEN \'\'
				ELSE pg_catalog.col_description(a.attrelid, a.attnum)
				END  AS "comments"
			FROM pg_catalog.pg_attribute a
			LEFT JOIN pg_catalog.pg_attrdef adef ON a.attrelid=adef.adrelid AND a.attnum=adef.adnum
			LEFT JOIN pg_catalog.pg_type t ON a.atttypid=t.oid
			WHERE a.attrelid =
				(SELECT oid FROM pg_catalog.pg_class WHERE relname=' . $this->quote($tableSub) . '
					AND relnamespace = (SELECT oid FROM pg_catalog.pg_namespace WHERE
					nspname = ' . $this->quote($defaultSchema) . ')
				)
			AND a.attnum > 0 AND NOT a.attisdropped
			ORDER BY a.attnum'
        );

        $fields = $this->loadObjectList();

        if ($typeOnly) {
            foreach ($fields as $field) {
                $result[$field->column_name] = preg_replace('/[(0-9)]/', '', $field->type);
            }
        } else {
            foreach ($fields as $field) {
                if ($field->Default !== null) {
                    if (preg_match('/^\'(.*)\'::.*/', $field->Default, $matches)) {
                        $field->Default = $matches[1];
                    }

                    // Заменим тип NULL::*Postgresql на нулевой тип PHP. Сделаем это в последнюю очередь, чтобы избежать проблем с типами PHP в PHP 8.1 и более поздних версиях.
                    if (preg_match('/^NULL::*/', $field->Default)) {
                        $field->Default = null;
                    }
                }

                $result[$field->column_name] = (object) [
                    'column_name' => $field->column_name,
                    'type'        => $field->type,
                    'null'        => $field->null,
                    'Default'     => $field->Default,
                    'comments'    => '',
                    'Field'       => $field->column_name,
                    'Type'        => $field->type,
                    'Null'        => $field->null,
                    // @todo: Улучшите запрос выше, чтобы он также возвращал информацию о первичном ключе.
                    // 'Key' => ($field->PK == '1' ? 'PRI' : '')
                ];
            }
        }

        return $result;
    }

    /**
     * Возвращает подробный список ключей для таблицы.
     *
     * @param mixed $tables Имя таблицы.
     *
     * @return  array  Массив спецификации столбца таблицы.
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function getTableKeys(mixed $tables): array {
        $this->connect();

        $tableList = $this->getTableList();
        $tableSub  = $this->replacePrefix($tables);

        if (\in_array($tableSub, $tableList, true)) {
            $this->setQuery(
                '
				SELECT indexname AS "idxName", indisprimary AS "isPrimary", indisunique  AS "isUnique", indkey AS "indKey",
					CASE WHEN indisprimary = true THEN
						( SELECT \'ALTER TABLE \' || tablename || \' ADD \' || pg_catalog.pg_get_constraintdef(const.oid, true)
							FROM pg_constraint AS const WHERE const.conname= pgClassFirst.relname )
					ELSE pg_catalog.pg_get_indexdef(indexrelid, 0, true)
					END AS "Query"
				FROM pg_indexes
				LEFT JOIN pg_class AS pgClassFirst ON indexname=pgClassFirst.relname
				LEFT JOIN pg_index AS pgIndex ON pgClassFirst.oid=pgIndex.indexrelid
				WHERE tablename=' . $this->quote($tableSub) . ' ORDER BY indkey'
            );

            return $this->loadObjectList();
        }

        return [];
    }

    /**
     * Возвращает список имен столбцов, которые индексирует этот индекс.
     *
     * @param   string  $table   Имя таблицы.
     * @param   string  $indKey  Список номеров столбцов таблицы.
     *
     * @return  string  Список имен столбцов таблицы.
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function getNamesKey(string $table, string $indKey): string {
        $this->connect();

        $tableSub = $this->replacePrefix($table);

        $tabInd   = explode(' ', $indKey);
        $colNames = [];

        foreach ($tabInd as $numCol) {
            $query = $this->createQuery()
                ->select('attname')
                ->from('pg_attribute')
                ->join('LEFT', 'pg_class ON pg_class.relname=' . $this->quote($tableSub))
                ->where('attnum=' . $numCol . ' AND attrelid=pg_class.oid');
            $this->setQuery($query);
            $colNames[] = $this->loadResult();
        }

        return implode(', ', $colNames);
    }

    /**
     * Метод получения массива всех таблиц в базе данных.
     *
     * @return  array  Массив всех таблиц в базе данных.
     *
     * @throws  \RuntimeException
     */
    public function getTableList(): array {
        $query = $this->createQuery()
            ->select('table_name')
            ->from('information_schema.tables')
            ->where('table_type = ' . $this->quote('BASE TABLE'))
            ->where('table_schema NOT IN (' . $this->quote('pg_catalog') . ', ' . $this->quote('information_schema') . ')')
            ->order('table_name ASC');

        $this->setQuery($query);

        return $this->loadColumn();
    }

    /**
     * Возвращает подробный список последовательностей для таблицы.
     *
     * @param   string  $table  Имя таблицы.
     *
     * @return  array  Массив спецификации последовательностей для таблицы.
     *
     * @throws  \RuntimeException
     */
    public function getTableSequences(string $table): array {
        $tableList = $this->getTableList();
        $tableSub  = $this->replacePrefix($table);

        if (\in_array($tableSub, $tableList, true)) {
            $name = [
                's.relname', 'n.nspname', 't.relname', 'a.attname', 'info.data_type',
                'info.minimum_value', 'info.maximum_value', 'info.increment', 'info.cycle_option', 'info.start_value',
            ];

            $as = [
                'sequence', 'schema', 'table', 'column', 'data_type', 'minimum_value', 'maximum_value', 'increment', 'cycle_option', 'start_value',
            ];

            $query = $this->createQuery()
                ->select($this->quoteName($name, $as))
                ->from('pg_class AS s')
                ->leftJoin("pg_depend d ON d.objid = s.oid AND d.classid = 'pg_class'::regclass AND d.refclassid = 'pg_class'::regclass")
                ->leftJoin('pg_class t ON t.oid = d.refobjid')
                ->leftJoin('pg_namespace n ON n.oid = t.relnamespace')
                ->leftJoin('pg_attribute a ON a.attrelid = t.oid AND a.attnum = d.refobjsubid')
                ->leftJoin('information_schema.sequences AS info ON info.sequence_name = s.relname')
                ->where('s.relkind = ' . $this->quote('S') . ' AND d.deptype = ' . $this->quote('a') . ' AND t.relname = ' . $this->quote($tableSub));
            $this->setQuery($query);

            return $this->loadObjectList();
        }

        return [];
    }

    /**
     * Метод получения последнего значения последовательности в базе данных.
     *
     * @param string $sequence Название последовательности.
     *
     * @return  integer  Последнее значение последовательности.
     *
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function getSequenceLastValue(string $sequence): int {
        $this->connect();

        $query = $this->createQuery()
            ->select($this->quoteName('last_value'))
            ->from($sequence);

        $this->setQuery($query);

        return $this->loadResult();
    }

    /**
     * Метод для получения атрибута is_called последовательности.
     *
     * @param string $sequence Название последовательности.
     *
     * @return  boolean  Атрибут is_called последовательности.
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function getSequenceIsCalled(string $sequence): bool {
        $this->connect();

        $query = $this->createQuery()
            ->select($this->quoteName('is_called'))
            ->from($sequence);

        $this->setQuery($query);

        return $this->loadResult();
    }

    /**
     * Блокирует таблицу в базе данных.
     *
     * @param string $tableName  Имя таблицы, которую нужно блокировать.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function lockTable(string $tableName): static {
        $this->transactionStart();
        $this->setQuery('LOCK TABLE ' . $this->quoteName($tableName) . ' IN ACCESS EXCLUSIVE MODE')->execute();

        return $this;
    }

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable    Имя таблицы, которую нужно переименовать.
     * @param string      $newTable    Новое имя таблицы.
     * @param string|null $backup      Не используется PostgreSQL.
     * @param string|null $prefix      Не используется PostgreSQL.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function renameTable(
        string $oldTable,
        string $newTable,
        ?string $backup = null,
        ?string $prefix = null
    ): static {

        $this->connect();

        $oldTable = $this->replacePrefix($oldTable);
        $newTable = $this->replacePrefix($newTable);

        $tableList = $this->getTableList();

        if (!\in_array($oldTable, $tableList, true)) {
            throw new \RuntimeException('Таблица не найдена в базе данных Postgresql.');
        }

        $subQuery = $this->createQuery()
            ->select('indexrelid')
            ->from('pg_index, pg_class')
            ->where('pg_class.relname = ' . $this->quote($oldTable))
            ->where('pg_class.oid = pg_index.indrelid');

        $this->setQuery(
            $this->createQuery()
                ->select('relname')
                ->from('pg_class')
                ->where('oid IN (' . (string) $subQuery . ')')
        );

        $oldIndexes = $this->loadColumn();

        foreach ($oldIndexes as $oldIndex) {
            $changedIdxName = str_replace($oldTable, $newTable, $oldIndex);
            $this->setQuery('ALTER INDEX ' . $this->escape($oldIndex) . ' RENAME TO ' . $this->escape($changedIdxName))->execute();
        }

        $subQuery = $this->createQuery()
            ->select('oid')
            ->from('pg_namespace')
            ->where('nspname NOT LIKE ' . $this->quote('pg_%'))
            ->where('nspname != ' . $this->quote('information_schema'));

        $this->setQuery(
            $this->createQuery()
                ->select('relname')
                ->from('pg_class')
                ->where('relkind = ' . $this->quote('S'))
                ->where('relnamespace IN (' . (string) $subQuery . ')')
                ->where('relname LIKE ' . $this->quote("%$oldTable%"))
        );

        $oldSequences = $this->loadColumn();

        foreach ($oldSequences as $oldSequence) {
            $changedSequenceName = str_replace($oldTable, $newTable, $oldSequence);
            $this->setQuery('ALTER SEQUENCE ' . $this->escape($oldSequence) . ' RENAME TO ' . $this->escape($changedSequenceName))->execute();
        }

        $this->setQuery('ALTER TABLE ' . $this->escape($oldTable) . ' RENAME TO ' . $this->escape($newTable))->execute();

        return $this;
    }

    /**
     * Эта функция возвращает значение поля в виде подготовленной строки для использования в инструкции SQL.
     *
     * @param   array            $columns     Массив столбца таблицы, возвращаемый ::getTableColumns.
     * @param   string           $fieldName   Имя поля таблицы.
     * @param   string|bool|int  $fieldValue  Значение переменной для цитирования и возврата.
     *
     * @return  string  Цитируемая строка.
     *
     */
    public function sqlValue(array $columns, string $fieldName, string|bool|int $fieldValue): string {
        switch ($columns[$fieldName]) {
            case 'boolean':
                $val = 'NULL';

                if ($fieldValue === 't' || $fieldValue === true || $fieldValue === 1 || $fieldValue === '1') {
                    $val = 'TRUE';
                } elseif ($fieldValue === 'f' || $fieldValue === false || $fieldValue === 0 || $fieldValue === '0') {
                    $val = 'FALSE';
                }

                break;

            case 'bigint':
            case 'bigserial':
            case 'integer':
            case 'money':
            case 'numeric':
            case 'real':
            case 'smallint':
            case 'serial':
            //case 'numeric,':
                $val = $fieldValue === '' ? 'NULL' : $fieldValue;

                break;

            case 'timestamp without time zone':
            case 'date':
                if (empty($fieldValue)) {
                    $fieldValue = $this->getNullDate();
                }

                $val = $this->quote($fieldValue);

                break;

            default:
                $val = $this->quote($fieldValue);

                break;
        }

        return $val;
    }

    /**
     * Метод фиксации транзакции.
     *
     * @param boolean $toSavepoint Если это правда, сохраните последнюю точку сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     * @throws  \Exception
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
     * @param boolean $toSavepoint Если это правда, откат к последней точке сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     * @throws  \Exception
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
                $this->setQuery('RELEASE SAVEPOINT ' . $this->quoteName($savepoint))->execute();
            }
        }
    }

    /**
     * Метод инициализации транзакции.
     *
     * @param boolean $asSavepoint Если это правда и транзакция уже активна, будет создана точка сохранения.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     * @throws  \Exception
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

    /**
     * Вставляет строку в таблицу на основе свойств объекта.
     *
     * @param string      $table     Имя таблицы базы данных, в которую требуется вставить.
     * @param object      $object    Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param string|null $key       Имя первичного ключа. Если указано, свойство объекта обновляется.
     *
     * @return  boolean
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function insertObject(string $table, object &$object, string $key = null): bool {
        $columns = $this->getTableColumns($table);

        $fields = [];
        $values = [];

        foreach (get_object_vars($object) as $k => $v) {
            if (!\array_key_exists($k, $columns)) {
                continue;
            }

            if (\is_array($v) || \is_object($v) || $v === null) {
                continue;
            }

            if (($k[0] === '_') || ($k == $key && (($v === 0) || ($v === '0')))) {
                continue;
            }

            if ($columns[$k] === 'timestamp without time zone' && empty($v)) {
                continue;
            }

            $fields[] = $this->quoteName($k);
            $values[] = $this->sqlValue($columns, $k, $v);
        }

        $query = $this->createQuery();

        $query->insert($this->quoteName($table))
            ->columns($fields)
            ->values(implode(',', $values));

        if ($key) {
            $query->returning($key);

            $object->$key = $this->setQuery($query)->loadResult();
        } else {
            $this->setQuery($query)->execute();
        }

        return true;
    }

    /**
     * Проверяет, доступен ли соединитель PostgreSQL.
     *
     * @return  boolean  True в случае успеха, иначе false.
     *
     */
    public static function isSupported(): bool {
        return class_exists('\\PDO') && \in_array('pgsql', \PDO::getAvailableDrivers(), true);
    }

    /**
     * Возвращает массив, содержащий список таблиц базы данных.
     *
     * @return  array  Список таблиц базы данных.
     *
     */
    public function showTables(): array {
        $query = $this->createQuery()
            ->select('table_name')
            ->from('information_schema.tables')
            ->where('table_type=' . $this->quote('BASE TABLE'))
            ->where('table_schema NOT IN (' . $this->quote('pg_catalog') . ', ' . $this->quote('information_schema') . ' )');
        $this->setQuery($query);

        return $this->loadColumn();
    }

    /**
     * Возвращает позицию подстроки внутри строки.
     *
     * @param   string  $substring  Искомая строка.
     * @param   string  $string     Искомая строка/столбец.
     *
     * @return  integer  Позиция $substring в $string.
     *
     */
    public function getStringPositionSql(string $substring, string $string): int {
        $this->setQuery("SELECT POSITION($substring IN $string)");
        $position = $this->loadRow();

        return $position['position'];
    }

    /**
     * Генерирует случайное значение.
     *
     * @return  float  Случайно сгенерированное число.
     *
     */
    public function getRandom(): float {
        $this->setQuery('SELECT RANDOM()');
        $random = $this->loadAssoc();

        return $random['random'];
    }

    /**
     * Возвращает строку запроса, чтобы изменить набор символов базы данных.
     *
     * @param string $dbName  Имя базы данных.
     *
     * @return  string  Запрос, который изменяет строку запроса к базе данных.
     *
     */
    public function getAlterDbCharacterSet(string $dbName): string {
        return 'ALTER DATABASE ' . $this->quoteName($dbName) . ' SET CLIENT_ENCODING TO ' . $this->quote('UTF8');
    }

    /**
     * Возвращает строку запроса для создания новой базы данных с правильным синтаксисом PostgreSQL.
     *
     * @param     object         $options     Объект, полученный из функции «initialise», для передачи имени пользователя и базы данных драйверу базы данных.
     * @param     boolean|null   $utf         True, если база данных поддерживает набор символов UTF-8, который не используется в запросе PostgreSQL «CREATE DATABASE».
     *
     * @return  string  Запрос, создающий базу данных, принадлежащую $options['user'].
     */
    public function getCreateDbQuery(object $options, ?bool $utf): string {
        $query = 'CREATE DATABASE ' . $this->quoteName($options->db_name) . ' OWNER ' . $this->quoteName($options->db_user);

        if ($utf) {
            $query .= ' ENCODING ' . $this->quote('UTF-8');
        }

        return $query;
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
        $sql = trim($sql);

        if (strpos($sql, '\'')) {
            if (strpos($sql, 'currval')) {
                $sql = explode('currval', $sql);

                for ($nIndex = 1, $nIndexMax = \count($sql); $nIndex < $nIndexMax; $nIndex += 2) {
                    $sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
                }

                $sql = implode('currval', $sql);
            }

            if (strpos($sql, 'nextval')) {
                $sql = explode('nextval', $sql);

                for ($nIndex = 1, $nIndexMax = \count($sql); $nIndex < $nIndexMax; $nIndex += 2) {
                    $sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
                }

                $sql = implode('nextval', $sql);
            }

            if (strpos($sql, 'setval')) {
                $sql = explode('setval', $sql);

                for ($nIndex = 1, $nIndexMax = \count($sql); $nIndex < $nIndexMax; $nIndex += 2) {
                    $sql[$nIndex] = str_replace($prefix, $this->tablePrefix, $sql[$nIndex]);
                }

                $sql = implode('setval', $sql);
            }

            $explodedQuery = explode('\'', $sql);

            for ($nIndex = 0, $nIndexMax = \count($explodedQuery); $nIndex < $nIndexMax; $nIndex += 2) {
                if (strpos($explodedQuery[$nIndex], $prefix)) {
                    $explodedQuery[$nIndex] = str_replace($prefix, $this->tablePrefix, $explodedQuery[$nIndex]);
                }
            }

            $replacedQuery = implode('\'', $explodedQuery);
        } else {
            $replacedQuery = str_replace($prefix, $this->tablePrefix, $sql);
        }

        return $replacedQuery;
    }

    /**
     * Разблокирует таблицы в базе данных, в PostgreSQL этой команды нет, она выполняется автоматически при фиксации или откате.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     * @throws  \Exception
     */
    public function unlockTables(): static {
        $this->transactionCommit();

        return $this;
    }

    /**
     * Обновляет строку в таблице на основе свойств объекта.
     *
     * @param string              $table    Имя таблицы базы данных, которую необходимо обновить.
     * @param object              $object   Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param array|string|object $key      Имя первичного ключа.
     * @param boolean             $nulls    Значение true для обновления пустых полей или значение false для их игнорирования.
     *
     * @return  boolean
     *
     * @throws \Exception
     */
    public function updateObject(string $table, object &$object, array|string|object $key, bool $nulls = false): bool {
        $columns = $this->getTableColumns($table);
        $fields  = [];
        $where   = [];

        if (\is_string($key)) {
            $key = [$key];
        }

        if (\is_object($key)) {
            $key = (array) $key;
        }

        $statement = 'UPDATE ' . $this->quoteName($table) . ' SET %s WHERE %s';

        foreach (get_object_vars($object) as $k => $v) {
            if (!\array_key_exists($k, $columns)) {
                continue;
            }

            if (\is_array($v) || \is_object($v) || $k[0] === '_') {
                continue;
            }

            if (\in_array($k, $key, true)) {
                $key_val = $this->sqlValue($columns, $k, $v);
                $where[] = $this->quoteName($k) . '=' . $key_val;

                continue;
            }

            if ($v === null) {
                if (!$nulls) {
                    continue;
                }

                $val = 'NULL';
            } else {
                $val = $this->sqlValue($columns, $k, $v);
            }

            $fields[] = $this->quoteName($k) . '=' . $val;
        }

        if (empty($fields)) {
            return true;
        }

        $this->setQuery(sprintf($statement, implode(',', $fields), implode(' AND ', $where)));

        return $this->execute();
    }

    /**
     * Заключает в кавычки двоичную строку, соответствующую требованиям к базе данных, для использования в запросах к базе данных.
     *
     * @param string $data  Двоичная строка для цитирования.
     *
     * @return  string  Входная строка в двоичных кавычках.
     */
    public function quoteBinary(string $data): string {
        return "decode('" . bin2hex($data) . "', 'hex')";
    }

    /**
     * Заменяет специальный заполнитель, представляющий двоичное поле, исходной строкой.
     *
     * @param string $data  Закодированная строка или ресурс.
     *
     * @return  string  Исходная строка.
     */
    public function decodeBinary(string $data): string {
        if (\is_resource($data)) {
            return stream_get_contents($data);
        }

        return $data;
    }
}
