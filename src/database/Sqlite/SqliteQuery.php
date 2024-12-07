<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlite;

use Flexis\Database\DatabaseQuery;
use Flexis\Database\Pdo\PdoQuery;
use Flexis\Database\Query\QueryElement;

/**
 * Класс построения запросов SQLite.
 */
class SqliteQuery extends PdoQuery {
    /**
     * Магический метод для преобразования запроса в строку.
     *
     * @return  string  Завершенный запрос.
     */
    public function __toString() {
        switch ($this->type) {
            case 'select':
                if ($this->selectRowNumber) {
                    $orderBy          = $this->selectRowNumber['orderBy'];
                    $orderColumnAlias = $this->selectRowNumber['orderColumnAlias'];

                    $column = "ROW_NUMBER() AS $orderColumnAlias";

                    if ($this->select === null) {
                        $query = PHP_EOL . 'SELECT 1'
                            . (string) $this->from
                            . (string) $this->where;
                    } else {
                        $tmpOffset    = $this->offset;
                        $tmpLimit     = $this->limit;
                        $this->offset = 0;
                        $this->limit  = 0;
                        $tmpOrder     = $this->order;
                        $this->order  = null;
                        $query        = parent::__toString();
                        $column       = "w.*, $column";
                        $this->order  = $tmpOrder;
                        $this->offset = $tmpOffset;
                        $this->limit  = $tmpLimit;
                    }

                    $query = PHP_EOL . "SELECT $column"
                        . PHP_EOL . "FROM ($query" . PHP_EOL . "ORDER BY $orderBy"
                        . PHP_EOL . ') AS w,(SELECT ROW_NUMBER(0)) AS r'
                        . ((string) $this->order ?: PHP_EOL . 'ORDER BY NULL');

                    return $this->processLimit($query, $this->limit, $this->offset);
                }

                break;

            case 'querySet':
                $query = $this->querySet;

                if ($query->order || $query->limit || $query->offset) {
                    $query = PHP_EOL . "SELECT * FROM ($query)";
                }

                if ($this->merge) {
                    foreach ($this->merge as $element) {
                        $query .= (string) $element;
                    }
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                }

                return $query;

            case 'update':
                if ($this->join) {
                    $table = $this->update->getElements();
                    $table = $table[0];

                    $tableName = explode(' ', $table);
                    $tableName = $tableName[0];

                    if ($this->columns === null) {
                        $fields = $this->db->getTableColumns($tableName);

                        foreach ($fields as $key => $value) {
                            $fields[$key] = $key;
                        }

                        $this->columns = new QueryElement('()', $fields);
                    }

                    $fields   = $this->columns->getElements();
                    $elements = $this->set->getElements();

                    foreach ($elements as $nameValue) {
                        $setArray = explode(' = ', $nameValue, 2);

                        if ($setArray[0][0] === '`') {
                            $setArray[0] = substr($setArray[0], 1, -1);
                        }

                        $fields[$setArray[0]] = $setArray[1];
                    }

                    $select = new static($this->db);
                    $select->select(array_values($fields))
                        ->from($table);

                    $select->join  = $this->join;
                    $select->where = $this->where;

                    return 'INSERT OR REPLACE INTO ' . $tableName
                        . ' (' . implode(',', array_keys($fields)) . ')'
                        . (string) $select;
                }
        }

        return parent::__toString();
    }

    /**
     * Возвращает количество символов в строке.
     *
     * Обратите внимание: используйте «length», чтобы найти количество байтов в строке.
     *
     * Использование:
     * $query->select($query->charLength('a'));
     *
     * @param string      $field      Значение.
     * @param string|null $operator   Оператор сравнения целочисленного значения charLength и $condition
     * @param string|null $condition  Целочисленное значение для сравнения charLength.
     *
     * @return  string  Требуемый вызов длины символов.
     */
    public function charLength(string $field, string $operator = null, string $condition = null): string {
        $statement = 'length(' . $field . ')';

        if ($operator !== null && $condition !== null) {
            $statement .= ' ' . $operator . ' ' . $condition;
        }

        return $statement;
    }

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * Использование:
     * $query->select($query->concatenate(array('a', 'b')));
     *
     * @param string[]    $values     Массив значений для объединения.
     * @param string|null $separator  В качестве разделителя между каждым значением.
     *
     * @return  string  Объединенные значения.
     */
    public function concatenate(array $values, string $separator = null): string {
        if ($separator !== null) {
            return implode(' || ' . $this->quote($separator) . ' || ', $values);
        }

        return implode(' || ', $values);
    }

    /**
     * Метод для изменения запроса, уже имеющего строковый формат, с необходимыми дополнениями, чтобы ограничить запрос определенным количеством результатов или начать с определенного смещения.
     *
     * @param string  $query   Запрос в строковом формате.
     * @param integer $limit   Предел для набора результатов.
     * @param integer $offset  Смещение для набора результатов.
     *
     * @return  string
     *
     */
    public function processLimit(string $query, int $limit, int $offset = 0): string {
        if ($limit > 0 || $offset > 0) {
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        return $query;
    }

    /**
     * Возвращает номер текущей строки.
     *
     * Использование:
     * $query->select('id');
     * $query->selectRowNumber('ordering,publish_up DESC', 'new_ordering');
     * $query->from('#__content');
     *
     * @param string $orderBy           Выражение порядка для оконной функции.
     * @param string $orderColumnAlias  Псевдоним для нового столбца заказа.
     *
     * @return  $this
     * 
     * @throws  \RuntimeException
     */
    public function selectRowNumber(string $orderBy, string $orderColumnAlias): static {
        $this->validateRowNumber($orderBy, $orderColumnAlias);

        return $this;
    }

    /**
     * Добавляет запрос в UNION с текущим запросом.
     *
     * Использование:
     * $query->union('SELECT name FROM  #__foo')
     * $query->union('SELECT name FROM  #__foo', true)
     *
     * @param string|DatabaseQuery $query     Объект DatabaseQuery или строка для объединения.
     * @param boolean              $distinct  Значение true, чтобы возвращать из объединения только отдельные строки..
     *
     * @return  $this
     *
     */
    public function union(DatabaseQuery|string $query, bool $distinct = true): static {
        return $this->merge($distinct ? 'UNION SELECT * FROM ()' : 'UNION ALL SELECT * FROM ()', $query);
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
        return 'group_concat(' . $expression . ', ' . $this->quote($separator) . ')';
    }
}
