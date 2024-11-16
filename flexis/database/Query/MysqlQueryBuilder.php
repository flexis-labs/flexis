<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Query;

/**
 * Особенность построения запросов MySQL.
 */
trait MysqlQueryBuilder {
    /**
     * Магический метод для преобразования запроса в строку.
     *
     * @return  string  Завершенный запрос.
     */
    public function __toString() {
        switch ($this->type) {
            case 'select':
                if ($this->selectRowNumber) {
                    $orderBy      = $this->selectRowNumber['orderBy'];
                    $tmpOffset    = $this->offset;
                    $tmpLimit     = $this->limit;
                    $this->offset = 0;
                    $this->limit  = 0;
                    $tmpOrder     = $this->order;
                    $this->order  = null;
                    $query        = parent::__toString();
                    $this->order  = $tmpOrder;
                    $this->offset = $tmpOffset;
                    $this->limit  = $tmpLimit;

                    $query = PHP_EOL . 'SELECT * FROM (' . $query . PHP_EOL . "ORDER BY $orderBy" . PHP_EOL . ') w';

                    if ($this->order) {
                        $query .= (string) $this->order;
                    }

                    return $this->processLimit($query, $this->limit, $this->offset);
                }
        }

        return parent::__toString();
    }

    /**
     * Метод для изменения запроса, уже имеющего строковый формат, с необходимыми дополнениями, чтобы ограничить запрос определенным количеством результатов или начать с определенного смещения.
     *
     * @param string  $query   Запрос в строковом формате.
     * @param integer $limit   Предел для набора результатов.
     * @param integer $offset  Смещение для набора результатов.
     *
     * @return  string
     */
    public function processLimit(string $query, int $limit, int $offset = 0): string {
        if ($limit > 0 && $offset > 0) {
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        } elseif ($limit > 0) {
            $query .= ' LIMIT ' . $limit;
        }

        return $query;
    }

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * @param string[]    $values     Массив значений для объединения.
     * @param string|null $separator  В качестве разделителя между каждым значением.
     *
     * @return  string  Объединенные значения.
     */
    public function concatenate(array $values, string $separator = null): string {
        if ($separator !== null) {
            $statement = 'CONCAT_WS(' . $this->quote($separator);

            foreach ($values as $value) {
                $statement .= ', ' . $value;
            }

            return $statement . ')';
        }

        return 'CONCAT(' . implode(',', $values) . ')';
    }

    /**
     * Агрегатная функция для получения входных значений, объединенных в строку, разделенную разделителем.
     *
     * Использование:
     * $query->groupConcat('id', ',');
     *
     * @param string $expression  Выражение, к которому применяется объединение. Это может быть имя столбца или сложный оператор SQL.
     * @param string $separator   Разделитель каждого объединенного значения.
     *
     * @return  string  Входные значения объединены в строку, разделенную разделителем.
     */
    public function groupConcat(string $expression, string $separator = ','): string {
        return 'GROUP_CONCAT(' . $expression . ' SEPARATOR ' . $this->quote($separator) . ')';
    }

    /**
     * Метод для цитирования и, при необходимости, экранирования строки, соответствующей требованиям базы данных для вставки в базу данных.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод quote.
     *
     * @note «q» — это псевдоним этого метода, как и в DatabaseDriver.
     *
     * Использование:
     * $query->quote('fulltext');
     * $query->q('fulltext');
     * $query->q(array('option', 'fulltext'));
     *
     * @param array|string $text    Строка или массив строк для цитирования.
     * @param boolean      $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  string  Входная строка в кавычках.
     * 
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    abstract public function quote(array|string $text, bool $escape = true): string;

    /**
     * Возвращает оператор регулярного выражения.
     *
     * Использование:
     * $query->where('field ' . $query->regexp($search));
     *
     * @param string $value  Шаблон регулярного выражения.
     *
     * @return  string
     */
    public function regexp(string $value): string {
        return ' REGEXP ' . $value;
    }

    /**
     * Возвращает функцию, возвращающую случайное значение с плавающей запятой.
     *
     * Использование:
     * $query->rand();
     *
     * @return  string
     */
    public function rand(): string {
        return ' RAND() ';
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
     */
    public function findInSet(string $value, string $set): string {
        return ' find_in_set(' . $value . ', ' . $set . ')';
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
        if ($this->db->isMariaDb() && version_compare($this->db->getVersion(), '11.0.0', '>=')) {
            return parent::selectRowNumber($orderBy, $orderColumnAlias);
        }

        $this->validateRowNumber($orderBy, $orderColumnAlias);

        return $this->select("(SELECT @rownum := @rownum + 1 FROM (SELECT @rownum := 0) AS r) AS $orderColumnAlias");
    }

    /**
     * Приводит значение к символу.
     *
     * Перед передачей методу убедитесь, что значение заключено в правильные кавычки.
     *
     * Использование:
     * $query->select($query->castAs('CHAR', 'a'));
     *
     * @param   string  $type    Тип строки для преобразования.
     * @param   string  $value   Значение для преобразования в виде символа.
     * @param   string  $length  Значение для преобразования в виде символа.
     *
     * @return  string  Оператор SQL для приведения значения к типу char.
     *
     */
    public function castAs(string $type, string $value, ?string $length = null): string {
        switch (strtoupper($type)) {
            case 'CHAR':
                if (!$length) {
                    return $value;
                } else {
                    return 'CAST(' . $value . ' AS CHAR(' . $length . '))';
                }

            case 'INT':
                return '(' . $value . ' + 0)';
        }

        return parent::castAs($type, $value, $length);
    }
}
