<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Query;

/**
 * Особенность построения запросов PostgreSQL.
 */
trait PostgresqlQueryBuilder {
    /**
     * Элемент FOR UPDATE, используемый в блокировке FOR UPDATE.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $forUpdate = null;

    /**
     * Элемент FOR SHARE, используемый в замке FOR SHARE.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $forShare = null;

    /**
     * Элемент NOWAIT, используемый в блокировках FOR SHARE и FOR UPDATE.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $noWait = null;

    /**
     * Элемент INSERT INTO
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $returning = null;

    /**
     * Магический метод для преобразования запроса в строку, только для запросов, специфичных для PostgreSQL.
     *
     * @return  string  Завершенный запрос.
     */
    public function __toString() {
        $query = '';

        switch ($this->type) {
            case 'select':
                $query .= (string) $this->select;
                $query .= (string) $this->from;

                if ($this->join) {
                    foreach ($this->join as $join) {
                        $query .= (string) $join;
                    }
                }

                if ($this->where) {
                    $query .= (string) $this->where;
                }

                if ($this->selectRowNumber) {
                    if ($this->order) {
                        $query .= (string) $this->order;
                    }

                    break;
                }

                if ($this->group) {
                    $query .= (string) $this->group;
                }

                if ($this->having) {
                    $query .= (string) $this->having;
                }

                if ($this->merge) {
                    foreach ($this->merge as $element) {
                        $query .= (string) $element;
                    }
                }

                if ($this->order) {
                    $query .= (string) $this->order;
                }

                if ($this->forUpdate) {
                    $query .= (string) $this->forUpdate;
                } else {
                    if ($this->forShare) {
                        $query .= (string) $this->forShare;
                    }
                }

                if ($this->noWait) {
                    $query .= (string) $this->noWait;
                }

                $query = $this->processLimit($query, $this->limit, $this->offset);

                break;

            case 'update':
                $query .= (string) $this->update;
                $query .= (string) $this->set;

                if ($this->join) {
                    $tmpFrom     = $this->from;
                    $tmpWhere    = $this->where ? clone $this->where : null;
                    $this->from  = null;

                    foreach ($this->join as $join) {
                        $joinElem = $join->getElements();

                        $this->from($joinElem[0]);

                        if (isset($joinElem[1])) {
                            $this->where($joinElem[1]);
                        }
                    }

                    $query .= (string) $this->from;

                    if ($this->where) {
                        $query .= (string) $this->where;
                    }

                    $this->from  = $tmpFrom;
                    $this->where = $tmpWhere;
                } elseif ($this->where) {
                    $query .= (string) $this->where;
                }

                $query = $this->processLimit($query, $this->limit, $this->offset);

                break;

            case 'insert':
                $query .= (string) $this->insert;

                if ($this->values) {
                    if ($this->columns) {
                        $query .= (string) $this->columns;
                    }

                    $elements = $this->values->getElements();

                    if (!($elements[0] instanceof $this)) {
                        $query .= ' VALUES ';
                    }

                    $query .= (string) $this->values;

                    if ($this->returning) {
                        $query .= (string) $this->returning;
                    }
                }

                $query = $this->processLimit($query, $this->limit, $this->offset);

                break;

            default:
                $query = parent::__toString();

                break;
        }

        if ($this->type === 'select' && $this->alias !== null) {
            $query = '(' . $query . ') AS ' . $this->alias;
        }

        return $query;
    }

    /**
     * Очищает данные из запроса или конкретного предложения запроса.
     *
     * @param string|null $clause  При необходимости можно указать имя очищаемого предложения или ничего, чтобы очистить весь запрос.
     *
     * @return  $this
     */
    public function clear(?string $clause = null): static {
        switch ($clause) {
            case 'limit':
                $this->limit = null;

                break;

            case 'offset':
                $this->offset = null;

                break;

            case 'forUpdate':
                $this->forUpdate = null;

                break;

            case 'forShare':
                $this->forShare = null;

                break;

            case 'noWait':
                $this->noWait = null;

                break;

            case 'returning':
                $this->returning = null;

                break;

            case 'select':
            case 'update':
            case 'delete':
            case 'insert':
            case 'querySet':
            case 'from':
            case 'join':
            case 'set':
            case 'where':
            case 'group':
            case 'having':
            case 'merge':
            case 'order':
            case 'columns':
            case 'values':
                parent::clear($clause);

                break;

            default:
                $this->forUpdate = null;
                $this->forShare  = null;
                $this->noWait    = null;
                $this->returning = null;

                parent::clear($clause);

                break;
        }

        return $this;
    }

    /**
     * Приводит значение к символу.
     *
     * Перед передачей методу убедитесь, что значение заключено в правильные кавычки.
     *
     * <pre>
     * Использование:
     * $query->select($query->castAs('CHAR', 'a'));
     * </pre>
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
                    return $value . '::text';
                } else {
                    return 'CAST(' . $value . ' AS CHAR(' . $length . '))';
                }

            case 'INT':
                return 'CAST(' . $value . ' AS INTEGER)';
        }

        return parent::castAs($type, $value, $length);
    }

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * <pre>
     * Использование:
     * $query->select($query->concatenate(array('a', 'b')));
     * </pre>
     *
     * @param string[]    $values     Массив значений для объединения.
     * @param string|null $separator  В качестве разделителя между каждым значением.
     *
     * @return  string  Объединенные значения.
     */
    public function concatenate(array $values, ?string $separator = null): string {
        if ($separator !== null) {
            return implode(' || ' . $this->quote($separator) . ' || ', $values);
        }

        return implode(' || ', $values);
    }

    /**
     * Возвращает текущую дату и время.
     *
     * @return  string  Возвращаемая строка, используемая в запросе для получения
     */
    public function currentTimestamp(): string {
        return 'NOW()';
    }

    /**
     * Устанавливает блокировку FOR UPDATE для выходной строки select.
     *
     * @param string $tableName  Стол для блокировки
     * @param string $glue       Объединение, с помощью которого можно соединить условия. По умолчанию ','
     *
     * @return  $this
     */
    public function forUpdate(string $tableName, string $glue = ','): static {
        $this->type = 'forUpdate';

        if ($this->forUpdate === null) {
            $glue            = strtoupper($glue);
            $this->forUpdate = new QueryElement('FOR UPDATE', 'OF ' . $tableName, "$glue ");
        } else {
            $this->forUpdate->append($tableName);
        }

        return $this;
    }

    /**
     * Устанавливает блокировку FOR SHARE для выходной строки select.
     *
     * @param string $tableName  Стол для блокировки
     * @param string $glue       Объединение, с помощью которого можно соединить условия. По умолчанию ','
     *
     * @return  $this
     */
    public function forShare(string $tableName, string $glue = ','): static {
        $this->type = 'forShare';

        if ($this->forShare === null) {
            $glue           = strtoupper($glue);
            $this->forShare = new QueryElement('FOR SHARE', 'OF ' . $tableName, "$glue ");
        } else {
            $this->forShare->append($tableName);
        }

        return $this;
    }

    /**
     * Агрегатная функция для получения входных значений, объединенных в строку, разделенную разделителем.
     *
     * <pre>
     * Использование:
     * $query->groupConcat('id', ',');
     * </pre>
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
     * Используется для получения строки для извлечения года из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->year($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий год, который необходимо извлечь.
     *
     * @return  string  Возвращает строку для извлечения года из даты.
     */
    public function year(string $date): string {
        return 'EXTRACT (YEAR FROM ' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения месяца из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->month($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий извлекаемый месяц.
     *
     * @return  string  Возвращает строку для извлечения месяца из даты.
     */
    public function month(string $date): string {
        return 'EXTRACT (MONTH FROM ' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения дня из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->day($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий день, который необходимо извлечь.
     *
     * @return  string  Возвращает строку для извлечения дня из даты.
     */
    public function day(string $date): string {
        return 'EXTRACT (DAY FROM ' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения часа из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->hour($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий извлекаемый час.
     *
     * @return  string  Возвращает строку для извлечения часа из даты.
     */
    public function hour(string $date): string {
        return 'EXTRACT (HOUR FROM ' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения минут из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->minute($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий извлекаемые минуты.
     *
     * @return  string  Возвращает строку для извлечения минут из даты.
     */
    public function minute(string $date): string {
        return 'EXTRACT (MINUTE FROM ' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения секунд из столбца даты.
     *
     * <pre>
     * Использование:
     * $query->select($query->second($query->quoteName('dateColumn')));
     * </pre>
     *
     * @param string $date  Столбец даты, содержащий секунду, которую нужно извлечь.
     *
     * @return  string  Возвращает строку для извлечения секунды из даты.
     */
    public function second(string $date): string {
        return 'EXTRACT (SECOND FROM ' . $date . ')';
    }

    /**
     * Устанавливает блокировку NOWAIT для выходной строки select.
     *
     * @return  $this
     */
    public function noWait(): static {
        $this->type = 'noWait';

        if ($this->noWait === null) {
            $this->noWait = new QueryElement('NOWAIT', null);
        }

        return $this;
    }

    /**
     * Устанавливает предложение LIMIT в запрос.
     *
     * @param integer $limit  Количество строк для возврата.
     *
     * @return  $this
     */
    public function limit(int $limit = 0): static {
        if ($this->limit === null) {
            $this->limit = new QueryElement('LIMIT', (int) $limit);
        }

        return $this;
    }

    /**
     * Устанавливает в запрос предложение OFFSET.
     *
     * @param integer $offset  Целое число для пропуска строк.
     *
     * @return  $this
     */
    public function offset(int $offset = 0): static {
        if ($this->offset === null) {
            $this->offset = new QueryElement('OFFSET', (int) $offset);
        }

        return $this;
    }

    /**
     * Добавляет элемент RETURNING в оператор INSERT INTO.
     *
     * @param   mixed  $pkCol  Имя столбца первичного ключа.
     *
     * @return  $this
     */
    public function returning(mixed $pkCol): static {
        if ($this->returning === null) {
            $this->returning = new QueryElement('RETURNING', $pkCol);
        }

        return $this;
    }

    /**
     * Метод для изменения запроса, уже имеющего строковый формат, с необходимыми дополнениями, чтобы ограничить запрос определенным количеством результатов или начать с определенного смещения.
     *
     * @param string  $query   Запрос в строковом формате.
     * @param integer $limit   Предел результирующего набора.
     * @param integer $offset  Смещение для набора результатов.
     *
     * @return  string
     */
    public function processLimit(string $query, int $limit, int $offset = 0): string {
        if ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
        }

        if ($offset > 0) {
            $query .= ' OFFSET ' . $offset;
        }

        return $query;
    }

    /**
     * Добавляет к текущей дате и времени.
     *
     * <pre>
     * Использование:
     * $query->select($query->dateAdd());
     * </pre>
     *
     * Если перед интервалом поставить знак -(отрицательный знак), будет использоваться вычитание.
     *
     * @param string $date      Строковое представление даты, которую нужно добавить в кавычки db.
     * @param string $interval  Строковое представление соответствующего количества единиц.
     * @param string $datePart  Часть даты для выполнения сложения.
     *
     * @return  string  Строка с соответствующим sql для добавления дат.
     * @link    http://www.postgresql.org/docs/9.0/static/functions-datetime.html.
     */
    public function dateAdd(string $date, string $interval, string $datePart): string {
        if (substr($interval, 0, 1) !== '-') {
            return 'timestamp ' . $date . " + interval '" . $interval . ' ' . $datePart . "'";
        }

        return 'timestamp ' . $date . " - interval '" . ltrim($interval, '-') . ' ' . $datePart . "'";
    }

    /**
     * Возвращает оператор регулярного выражения
     *
     * <pre>
     * Использование:
     * $query->where('field ' . $query->regexp($search));
     * </pre>
     *
     * @param string $value  Шаблон регулярного выражения.
     *
     * @return  string
     */
    public function regexp(string $value): string {
        return ' ~* ' . $value;
    }

    /**
     * Возвращает функцию, возвращающую случайное значение с плавающей запятой.
     *
     * <pre>
     * Использование:
     * $query->rand();
     * </pre>
     *
     * @return  string
     */
    public function rand(): string {
        return ' RANDOM() ';
    }

    /**
     * Поиск значения в varchar, используемом как набор.
     *
     * Перед передачей методу убедитесь, что значение является целым числом.
     *
     * <pre>
     * Использование:
     * $query->findInSet((int) $parent->id, 'a.assigned_cat_ids')
     * </pre>
     *
     * @param string $value  Значение для поиска.
     * @param string $set    Список значений разделенных запятыми.
     *
     * @return  string  Представление функции MySQL find_in_set() для драйвера.
     */
    public function findInSet(string $value, string $set): string {
        return " $value = ANY (string_to_array($set, ',')::integer[]) ";
    }
}
