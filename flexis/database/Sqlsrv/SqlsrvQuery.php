<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlsrv;

use Flexis\Database\DatabaseInterface;
use Flexis\Database\DatabaseQuery;
use Flexis\Database\Query\QueryElement;

/**
 * Класс построения запросов SQL Server.
 */
class SqlsrvQuery extends DatabaseQuery {
    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    array
     */
    protected array $nullDatetimeList = ['1900-01-01 00:00:00'];

    /**
     * Магический метод для преобразования запроса в строку.
     *
     * @return  string  Завершенный запрос.
     *
     */
    public function __toString() {
        if ($this->sql) {
            return $this->sql;
        }

        $query = '';

        switch ($this->type) {
            case 'select':
                $columns = $this->fixSelectAliases();

                $query = (string) $this->select;

                if ($this->group) {
                    $this->fixGroupColumns($columns);
                }

                $query .= (string) $this->from;

                if ($this->join) {
                    foreach ($this->join as $join) {
                        $query .= (string) $join;
                    }
                }

                if ($this->where) {
                    $query .= (string) $this->where;
                }

                if ($this->selectRowNumber === null) {
                    if ($this->group) {
                        $query .= (string) $this->group;
                    }

                    if ($this->having) {
                        $query .= (string) $this->having;
                    }

                    if ($this->merge) {
                        foreach ($this->merge as $idx => $element) {
                            $query .= (string) $element . ' AS merge_' . (int) ($idx + 1);
                        }
                    }
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                } else {
                    $query .= PHP_EOL . '/*ORDER BY (SELECT 0)*/';
                }

                $query = $this->processLimit($query, $this->limit, $this->offset);

                if ($this->alias !== null) {
                    $query = '(' . $query . ') AS ' . $this->alias;
                }

                break;

            case 'querySet':
                $query = $this->querySet;

                if ($query->order || $query->limit || $query->offset) {
                    $query = PHP_EOL . "SELECT * FROM ($query) AS merge_0";
                }

                if ($this->merge) {
                    foreach ($this->merge as $idx => $element) {
                        $query .= (string) $element . ' AS merge_' . (int) ($idx + 1);
                    }
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                }

                $query = $this->processLimit($query, $this->limit, $this->offset);

                break;

            case 'insert':
                $query .= (string) $this->insert;

                if ($this->set) {
                    $query .= (string) $this->set;
                } elseif ($this->values) {
                    if ($this->columns) {
                        $query .= (string) $this->columns;
                    }

                    $elements  = $this->insert->getElements();
                    $tableName = array_shift($elements);

                    $query .= 'VALUES ';
                    $query .= (string) $this->values;

                    if ($this->autoIncrementField) {
                        $query = 'SET IDENTITY_INSERT ' . $tableName . ' ON;' . $query . 'SET IDENTITY_INSERT ' . $tableName . ' OFF;';
                    }

                    if ($this->where) {
                        $query .= (string) $this->where;
                    }
                }

                break;

            case 'delete':
                $query .= (string) $this->delete;
                $query .= (string) $this->from;

                if ($this->join) {
                    foreach ($this->join as $join) {
                        $query .= (string) $join;
                    }
                }

                if ($this->where) {
                    $query .= (string) $this->where;
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                }

                break;

            case 'update':
                if ($this->join) {
                    $tmpUpdate    = $this->update;
                    $tmpFrom      = $this->from;
                    $this->update = null;
                    $this->from   = null;

                    $updateElem  = $tmpUpdate->getElements();
                    $updateArray = explode(' ', $updateElem[0]);

                    $this->update(end($updateArray));
                    $this->from($updateElem[0]);

                    $query .= (string) $this->update;
                    $query .= (string) $this->set;
                    $query .= (string) $this->from;

                    $this->update = $tmpUpdate;
                    $this->from   = $tmpFrom;

                    foreach ($this->join as $join) {
                        $query .= (string) $join;
                    }
                } else {
                    $query .= (string) $this->update;
                    $query .= (string) $this->set;
                }

                if ($this->where) {
                    $query .= (string) $this->where;
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                }

                break;

            default:
                $query = parent::__toString();

                break;
        }

        return $query;
    }

    /**
     * Приводит значение к символу.
     *
     * Перед передачей методу убедитесь, что значение заключено в правильные кавычки.
     *
     * Использование:
     * $query->select($query->castAs('CHAR', 'a'));
     *
     * @param   string   $type    Тип строки для преобразования.
     * @param   string   $value   Значение для преобразования в виде символа.
     * @param   ?string  $length  Значение для преобразования в виде символа.
     *
     * @return  string  Оператор SQL для приведения значения к типу char.
     *
     */
    public function castAs(string $type, string $value, ?string $length = null): string {
        switch (strtoupper($type)) {
            case 'CHAR':
                if (!$length) {
                    $length = '10';
                }

                return 'CAST(' . $value . ' as NVARCHAR(' . $length . '))';

            case 'INT':
                return 'CAST(' . $value . ' AS INT)';
        }

        return parent::castAs($type, $value, $length);
    }

    /**
     * Получает функцию для определения длины строки символов.
     *
     * @param string      $field      Значение.
     * @param string|null $operator   Оператор сравнения целочисленного значения charLength и $condition.
     * @param string|null $condition  Целочисленное значение для сравнения charLength.
     *
     * @return  string  Требуемый вызов длины символов.
     *
     */
    public function charLength(string $field, string $operator = null, string $condition = null): string {
        $statement = 'DATALENGTH(' . $field . ')';

        if ($operator !== null && $condition !== null) {
            $statement .= ' ' . $operator . ' ' . $condition;
        }

        return $statement;
    }

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * @param string[]    $values     Массив значений для объединения.
     * @param string|null $separator  В качестве разделителя между каждым значением.
     *
     * @return  string  Объединенные значения.
     *
     */
    public function concatenate(array $values, string $separator = null): string {
        if ($separator !== null) {
            return '(' . implode('+' . $this->quote($separator) . '+', $values) . ')';
        }

        return '(' . implode('+', $values) . ')';
    }

    /**
     * Получает текущую дату и время.
     *
     * @return  string
     *
     */
    public function currentTimestamp(): string {
        return 'GETDATE()';
    }

    /**
     * Возвращает длину строки в байтах.
     *
     * @param string $value  Строка для измерения.
     *
     * @return  string
     *
     */
    public function length(string $value): string {
        return 'LEN(' . $value . ')';
    }

    /**
     * Добавляет столбец группировки в предложение GROUP запроса.
     *
     * Использование:
     * $query->group('id');
     *
     * @param array|string $columns  Строка или массив столбцов упорядочения.
     *
     * @return  SqlsrvQuery  Возвращает этот объект, чтобы разрешить цепочку.
     *
     */
    public function group(array|string $columns): static {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException('Ошибка базы данных. Недопустимый объект базы данных.');
        }

        \is_string($columns) && $columns = explode(',', str_replace(' ', '', $columns));

        $fromStr = str_replace(['[', ']'], '', str_replace('#__', $this->db->getPrefix(), str_replace('FROM ', '', (string) $this->from)));

        list($table, $alias) = preg_split("/\sAS\s/i", $fromStr);

        $tmpCols = $this->db->getTableColumns(trim($table));
        $cols    = [];

        foreach ($tmpCols as $name => $type) {
            $cols[] = $alias . '.' . $name;
        }

        foreach ($this->join as $join) {
            $joinTbl = str_replace(
                '#__',
                $this->db->getPrefix(),
                str_replace(
                    ']',
                    '',
                    preg_replace("/.*(#.+\sAS\s[^\s]*).*/i", '$1', (string) $join)
                )
            );

            list($table, $alias) = preg_split("/\sAS\s/i", $joinTbl);

            $tmpCols = $this->db->getTableColumns(trim($table));

            foreach ($tmpCols as $name => $tmpColType) {
                $cols[] = $alias . '.' . $name;
            }
        }

        $selectStr = str_replace('SELECT ', '', (string) $this->select);

        $selectCols = preg_replace("/([^,]*\([^\)]*\)[^,]*,?)/", '', $selectStr);
        $selectCols = preg_replace("/(\sas\s[^,]*)/i", '', $selectCols);
        $selectCols = preg_replace('/,{2,}/', ',', $selectCols);
        $selectCols = trim(str_replace(' ', '', preg_replace('/,?$/', '', $selectCols)));
        $selectCols = explode(',', $selectCols);

        foreach ($selectCols as $key => $aliasColName) {
            if (preg_match("/.+\*/", $aliasColName, $match)) {
                $aliasStar = preg_replace("/(.+)\.\*/", '$1', $aliasColName);

                unset($selectCols[$key]);

                $tableColumns = preg_grep("/{$aliasStar}\.+/", $cols);
                $columns      = array_merge($columns, $tableColumns);
            }
        }

        $columns = array_unique(array_merge($columns, $selectCols));
        $columns = implode(',', $columns);

        $this->group = new QueryElement('GROUP BY', $columns);

        return $this;
    }

    /**
     * Агрегатная функция для получения входных значений, объединенных в строку, разделенную разделителем.
     *
     * Использование:
     * $query->groupConcat('id', ',');
     *
     * @param string $expression  Выражение, к которому применяется объединение. Это может быть имя столбца или сложный оператор SQL.
     * @param string $separator   Разделитель каждого объединенного значения
     *
     * @return  string  Входные значения объединены в строку, разделенную разделителем.
     */
    public function groupConcat(string $expression, string $separator = ','): string {
        return 'string_agg(' . $expression . ', ' . $this->quote($separator) . ')';
    }

    /**
     * Возвращает функцию, возвращающую случайное значение с плавающей запятой.
     *
     * Использование:
     * $query->rand();
     *
     * @return  string
     *
     */
    public function rand(): string {
        return ' NEWID() ';
    }

    /**
     * Поиск значения в varchar, используемом как набор.
     *
     * Перед передачей методу убедитесь, что значение является целым числом.
     *
     * Использование:
     * $query->findInSet((int) $parent->id, 'a.assigned_cat_ids')
     *
     * @param string $value  Значение для поиска.
     * @param string $set    Список значений разделенных запятыми.
     *
     * @return  string  Представление функции MySQL find_in_set() для драйвера.
     *
     */
    public function findInSet(string $value, string $set): string {
        return "CHARINDEX(',$value,', ',' + $set + ',') > 0";
    }

    /**
     * Добавляет необходимые псевдонимы в столбцы для оператора выбора в подзапросе.
     *
     * @return  array[]  Массив столбцов с добавленными отсутствующими псевдонимами.
     *
     */
    protected function fixSelectAliases(): array {
        $operators = [
            '+' => '',
            '-' => '',
            '*' => '',
            '/' => '',
            '%' => '',
            '&' => '',
            '|' => '',
            '~' => '',
            '^' => '',
        ];

        $columns = $this->splitSqlExpression(implode(',', $this->select->getElements()));

        foreach ($columns as $i => $column) {
            $size = \count($column);

            if ($size == 0) {
                continue;
            }

            if ($size > 2 && strcasecmp($column[$size - 2], 'AS') === 0) {
                $columns[$i][$size - 2] = 'AS';

                continue;
            }

            if ($i == 0 && stripos(' DISTINCT ALL ', " $column[0] ") !== false) {
                array_shift($column);
                $size--;
            }

            $lastWord = strtoupper($column[$size - 1]);
            $length   = \strlen($lastWord);
            $lastChar = $lastWord[$length - 1];

            if ($lastChar == '*') {
                continue;
            }

            if (
                $lastChar == ')'
                || ($size == 1 && $lastChar == "'")
                || $lastWord[0] == '@'
                || $lastWord == 'NULL'
                || $lastWord == 'END'
                || is_numeric($lastWord)
            ) {
                $columns[$i][] = 'AS';
                $columns[$i][] = $this->quoteName('columnAlias' . $i);

                continue;
            }

            if ($size == 1) {
                continue;
            }

            $lastChar2 = substr($column[$size - 2], -1);

            if (isset($operators[$lastChar2]) || ($size > 2 && $lastChar2 === '.' && isset($operators[substr($column[$size - 3], -1)]))) {
                if ($size != 2 || ltrim($column[0], '+') !== '' || $column[1][0] === "'") {
                    $columns[$i][] = 'AS';
                    $columns[$i][] = $this->quoteName('columnAlias' . $i);

                    continue;
                }
            } elseif ($column[$size - 1][0] !== '.' && $lastChar2 !== '.') {
                array_splice($columns[$i], -1, 0, 'AS');
            }
        }

        $selectColumns = [];

        foreach ($columns as $i => $column) {
            $selectColumns[$i] = implode(' ', $column);
        }

        $this->select = new QueryElement('SELECT', $selectColumns);

        return $columns;
    }

    /**
     * Добавляет имена отсутствующих столбцов в предложение GROUP BY.
     *
     * @param array[] $selectColumns  Массив столбцов из метода SplitSqlExpression.
     *
     * @return  $this
     */
    protected function fixGroupColumns(array $selectColumns): static {
        static $cacheCols = [];

        $knownColumnsByAlias = [];

        $iquotes = ['"' => '', '[' => '', "'" => ''];
        $nquotes = ['"', '[', ']'];

        $aFuncs = [
            'AVG(',
            'CHECKSUM_AGG(',
            'COUNT(',
            'COUNT_BIG(',
            'GROUPING(',
            'GROUPING_ID(',
            'MIN(',
            'MAX(',
            'SUM(',
            'STDEV(',
            'STDEVP(',
            'VAR(',
            'VARP(',
        ];

        $filteredColumns = [];

        $knownAliases   = [];
        $wildcardTables = [];

        foreach ($selectColumns as $i => $column) {
            $size = \count($column);

            if ($size === 0) {
                continue;
            }

            if ($i == 0 && stripos(' DISTINCT ALL ', " $column[0] ") !== false) {
                array_shift($selectColumns[0]);
                array_shift($column);
                $size--;
            }

            if ($size > 2 && $column[$size - 2] === 'AS') {
                $alias = $column[$size - 1];

                if (isset($iquotes[$alias[0]])) {
                    $alias = substr($alias, 1, -1);
                }

                $selectColumns[$i] = $column = \array_slice($column, 0, -2);

                if ($size === 3 || ($size === 4 && strpos('+-*/%&|~^', $column[0][0]) !== false)) {
                    $lastWord = $column[$size - 3];

                    if ($lastWord[0] === "'" || $lastWord === 'NULL' || is_numeric($lastWord)) {
                        unset($selectColumns[$i]);

                        continue;
                    }
                }

                $knownAliases[$alias] = implode(' ', $column);
            }

            $aggregated = false;

            foreach ($column as $j => $block) {
                if (substr($block, -2) === '.*') {
                    if (isset($iquotes[$block[0]])) {
                        $wildcardTables[] = substr($block, 1, -3);
                    } else {
                        $wildcardTables[] = substr($block, 0, -2);
                    }
                } elseif (str_ireplace($aFuncs, '', $block) != $block) {
                    $aggregated = true;
                }

                if ($block[0] === "'") {
                    $column[$j] = "''";
                }
            }

            if (!$aggregated) {
                $filteredColumns[] = implode(' ', $selectColumns[$i]);
            }

            $selectColumns[$i] = implode(' ', $column);
        }

        if ($wildcardTables) {
            $tables = $this->splitSqlExpression(implode(',', $this->from->getElements()));

            foreach ($tables as $i => $table) {
                $table = implode(' ', $table);

                if (strpos($table, '(') === false) {
                    $table = str_replace($nquotes, '', $table);
                    $table = str_replace('#__', $this->db->getPrefix(), $table);
                    $table = explode(' ', $table);
                    $alias = end($table);
                    $table = $table[0];

                    if (\in_array($alias, $wildcardTables, true)) {
                        if (!isset($cacheCols[$table])) {
                            $cacheCols[$table] = $this->db->getTableColumns($table);
                        }

                        if ($this->join || $table != $alias) {
                            foreach ($cacheCols[$table] as $name => $type) {
                                $knownColumnsByAlias[$alias][] = $alias . '.' . $name;
                            }
                        } else {
                            foreach ($cacheCols[$table] as $name => $type) {
                                $knownColumnsByAlias[$alias][] = $name;
                            }
                        }
                    }
                }
            }

            if ($this->join) {
                foreach ($this->join as $join) {
                    $joinTbl = str_replace($nquotes, '', (string) $join);
                    $joinTbl = str_replace('#__', $this->db->getPrefix(), $joinTbl);

                    if (preg_match('/JOIN\s+(\w+)(?:\s+AS)?(?:\s+(\w+))?/i', $joinTbl, $matches)) {
                        $table = $matches[1];
                        $alias = $matches[2] ?? $table;

                        if (\in_array($alias, $wildcardTables, true)) {
                            if (!isset($cacheCols[$table])) {
                                $cacheCols[$table] = $this->db->getTableColumns($table);
                            }

                            foreach ($cacheCols[$table] as $name => $type) {
                                $knownColumnsByAlias[$alias][] = $alias . '.' . $name;
                            }
                        }
                    }
                }
            }
        }

        $selectExpression = implode(',', $selectColumns);

        $groupColumns = $this->splitSqlExpression(implode(',', $this->group->getElements()));

        foreach ($groupColumns as $i => $column) {
            $groupColumns[$i] = implode(' ', $column);
            $column           = str_replace($nquotes, '', $groupColumns[$i]);

            if (isset($knownAliases[$column])) {
                if (!preg_match('/\b' . preg_quote($column, '/') . '\b/', $selectExpression)) {
                    $groupColumns[$i] = $knownAliases[$column];
                }
            }
        }

        foreach ($filteredColumns as $i => $column) {
            if (substr($column, -2) === '.*') {
                unset($filteredColumns[$i]);

                $groupColumns = array_merge($groupColumns, $knownColumnsByAlias[substr($column, 0, -2)]);
            }
        }

        $groupColumns = array_merge($groupColumns, $filteredColumns);

        if ($this->order) {
            $dir = [" DESC\v", " ASC\v"];

            $orderColumns = $this->splitSqlExpression(implode(',', $this->order->getElements()));

            foreach ($orderColumns as $i => $column) {
                $column           = implode(' ', $column);
                $orderColumns[$i] = $column = trim(str_ireplace($dir, '', "$column\v"), "\v");

                if (isset($knownAliases[str_replace($nquotes, '', $column)])) {
                    unset($orderColumns[$i]);
                }

                if (str_ireplace($aFuncs, '', $column) != $column) {
                    unset($orderColumns[$i]);
                }
            }

            $groupColumns = array_merge($groupColumns, $orderColumns);
        }

        $this->group = new QueryElement('GROUP BY', array_unique($groupColumns));

        return $this;
    }

    /**
     * Разделите строку выражения sql на массив отдельных столбцов.
     * Однострочные или конечные комментарии, а также многострочные комментарии удаляются.
     * Всегда возвращайте хотя бы один столбец.
     *
     * @param   string  $string  Введите строку выражения sql, например выражение выбора.
     *
     * @return  array[]  Столбцы входной строки разделены на массив.
     */
    protected function splitSqlExpression($string): array {
        $string .= ' ';

        $colIdx    = 0;
        $start     = 0;
        $open      = false;
        $openC     = 0;
        $comment   = false;
        $endString = '';
        $length    = \strlen($string);
        $columns   = [];
        $column    = [];
        $current   = '';
        $previous  = null;
        $operators = [
            '+' => '',
            '-' => '',
            '*' => '',
            '/' => '',
            '%' => '',
            '&' => '',
            '|' => '',
            '~' => '',
            '^' => '',
        ];

        $addBlock = function ($block) use (&$column, &$colIdx) {
            if (isset($column[$colIdx])) {
                $column[$colIdx] .= $block;
            } else {
                $column[$colIdx] = $block;
            }
        };

        for ($i = 0; $i < $length; $i++) {
            $current      = substr($string, $i, 1);
            $current2     = substr($string, $i, 2);
            $current3     = substr($string, $i, 3);
            $lenEndString = \strlen($endString);
            $testEnd      = substr($string, $i, $lenEndString);

            if (
                $current == '[' || $current == '"' || $current == "'" || $current2 == '--'
                || ($current2 == '/*')
                || ($current == '#' && $current3 != '#__')
                || ($lenEndString && $testEnd == $endString)
            ) {
                if ($open) {
                    if ($testEnd === $endString) {
                        if ($comment) {
                            if ($lenEndString > 1) {
                                $i += ($lenEndString - 1);
                            }

                            $start   = $i + 1;
                            $comment = false;
                        } elseif ($current == "'" || $current == ']' || $current == '"') {
                            $n = 1;

                            while ($i + $n < $length && $string[$i + $n] == $current) {
                                $n++;
                            }

                            $i += $n - 1;

                            if ($n % 2 === 0) {
                                continue;
                            }

                            if ($n > 2) {
                                $current = $string[$i];
                            }
                        }

                        $open      = false;
                        $endString = '';
                    }
                } else {
                    $open = true;

                    if ($current == '#' || $current2 == '--') {
                        $endString = "\n";
                        $comment   = true;
                    } elseif ($current2 == '/*') {
                        $endString = '*/';
                        $comment   = true;
                    } elseif ($current == '[') {
                        $endString = ']';
                    } else {
                        $endString = $current;
                    }

                    if ($comment && $start < $i) {
                        $addBlock(substr($string, $start, $i - $start));
                        $previous = $string[$i - 1];
                        $start    = $i;
                    }
                }
            } elseif (!$open) {
                if ($current == '(') {
                    $openC++;
                    $previous = $current;
                } elseif ($current == ')') {
                    $openC--;
                    $previous = $current;
                } elseif ($current == '.') {
                    if ($i === $start && $colIdx > 0 && !isset($column[$colIdx])) {
                        $colIdx--;
                    }

                    $previous = $current;
                } elseif ($openC === 0) {
                    if (ctype_space($current)) {
                        $string[$i] = ' ';

                        if ($start < $i) {
                            $addBlock(substr($string, $start, $i - $start));
                            $colIdx++;
                            $previous = $string[$i - 1];
                        } elseif (isset($column[$colIdx])) {
                            if ($colIdx > 1 || !isset($operators[$previous])) {
                                $colIdx++;
                            }
                        }

                        $start = $i + 1;
                    } elseif (isset($operators[$current]) && ($current !== '*' || $previous !== '.')) {
                        if ($start < $i) {
                            $addBlock(substr($string, $start, $i - $start));
                            $colIdx++;
                        } elseif (!isset($column[$colIdx]) && isset($operators[$previous])) {
                            $colIdx--;
                        }

                        $addBlock($current);
                        $previous = $current;
                        $colIdx++;

                        $start = $i + 1;
                    } else {
                        $previous = $current;
                    }
                }
            }

            if (($current == ',' && !$open && $openC == 0) || $i == $length - 1) {
                if ($start < $i && !$comment) {
                    $addBlock(substr($string, $start, $i - $start));
                }

                $columns[] = $column;

                $column   = [];
                $colIdx   = 0;
                $previous = null;

                $start = $i + 1;
            }
        }

        return $columns;
    }

    /**
     * Метод для изменения запроса, уже имеющего строковый формат, с необходимыми дополнениями, чтобы ограничить запрос определенным количеством результатов или начать с определенного смещения.
     *
     * @param   string   $query   Запрос в строковом формате.
     * @param   integer  $limit   Предел для набора результатов.
     * @param   integer  $offset  Смещение для набора результатов.
     *
     * @return  string
     */
    public function processLimit($query, $limit, $offset = 0): string {
        if ($offset > 0) {
            $commentPos = strrpos($query, '/*ORDER BY (SELECT 0)*/');

            if ($commentPos !== false && $commentPos + 2 === strripos($query, 'ORDER BY', $commentPos + 2)) {
                $query = substr_replace($query, 'ORDER BY (SELECT 0)', $commentPos, 23);
            }

            $query .= PHP_EOL . 'OFFSET ' . (int) $offset . ' ROWS';

            if ($limit > 0) {
                $query .= PHP_EOL . 'FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
            }
        } elseif ($limit > 0) {
            $position = stripos($query, 'SELECT');
            $distinct = stripos($query, 'SELECT DISTINCT');

            if ($position === $distinct) {
                $query = substr_replace($query, 'SELECT DISTINCT TOP ' . (int) $limit, $position, 15);
            } else {
                $query = substr_replace($query, 'SELECT TOP ' . (int) $limit, $position, 6);
            }
        }

        return $query;
    }

    /**
     * Добавляет запрос в UNION с текущим запросом.
     *
     * Использование:
     * $query->union('SELECT name FROM  #__foo')
     * $query->union('SELECT name FROM  #__foo', true)
     *
     * @param string|DatabaseQuery $query     Объект DatabaseQuery или строка для объединения.
     * @param boolean              $distinct  Значение true, чтобы возвращать из объединения только отдельные строки.
     *
     * @return  $this
     *
     */
    public function union(DatabaseQuery|string $query, bool $distinct = true): static {
        return $this->merge($distinct ? 'UNION SELECT * FROM ()' : 'UNION ALL SELECT * FROM ()', $query);
    }
}
