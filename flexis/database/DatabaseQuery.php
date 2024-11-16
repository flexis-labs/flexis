<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Flexis\Database\Exception\QueryTypeAlreadyDefinedException;
use Flexis\Database\Exception\UnknownTypeException;
use Flexis\Database\Query\QueryElement;

/**
 * Класс построения запросов Flexis Framework.
 *
 * @property-read  array                      $bounded             Содержит пару ключ/значение связанных объектов.
 * @property-read  array                      $parameterMapping    Массив сопоставления типов параметров.
 * @property-read  DatabaseInterface          $db                  Драйвер базы данных.
 * @property-read  string                     $sql                 SQL-запрос (если была предоставлена строка прямого запроса).
 * @property-read  string                     $type                Тип запроса.
 * @property-read  string|null                $alias               Псевдоним запроса.
 * @property-read  QueryElement               $element             Элемент запроса для общего запроса (тип = null).
 * @property-read  QueryElement               $select              Элемент select.
 * @property-read  QueryElement               $delete              Элемент delete.
 * @property-read  QueryElement               $update              Элемент update.
 * @property-read  QueryElement               $insert              Элемент insert.
 * @property-read  QueryElement               $from                Элемент from.
 * @property-read  QueryElement[]|null        $join                Элемент join.
 * @property-read  QueryElement               $set                 Элемент set.
 * @property-read  QueryElement               $where               Элемент where.
 * @property-read  QueryElement               $group               Элемент group.
 * @property-read  QueryElement               $having              Элемент having.
 * @property-read  QueryElement               $columns             Список столбцов для инструкции INSERT.
 * @property-read  QueryElement               $values              Список значений для инструкции INSERT.
 * @property-read  QueryElement               $order               Элемент order.
 * @property-read  boolean                    $autoIncrementField  Элемент поля вставки с автоинкрементом.
 * @property-read  QueryElement               $call                Элемент вызова.
 * @property-read  QueryElement               $exec                Исполнительный элемент.
 * @property-read  QueryElement[]|null        $merge               Список элементов запроса.
 * @property-read  DatabaseQuery|null         $querySet            Объект запроса.
 * @property-read  array|null                 $selectRowNumber     Подробности функции окна.
 * @property-read  string[]                   $nullDatetimeList    Список нулевых или нулевых представлений даты и времени.
 * @property-read  integer|null               $offset              Смещение для набора результатов.
 * @property-read  integer|null               $limit               Предел результирующего набора.
 * @property-read  integer                    $preparedIndex       Внутренний индекс функции bindArray для уникальных подготовленных параметров.
 */
abstract class DatabaseQuery implements QueryInterface {
    /**
     * Содержит пару ключ/значение связанных объектов.
     *
     * @var    array
     */
    protected array $bounded = [];

    /**
     * Массив сопоставления типов параметров.
     *
     * @var    array
     */
    protected array $parameterMapping = [
        ParameterType::BOOLEAN      => ParameterType::BOOLEAN,
        ParameterType::INTEGER      => ParameterType::INTEGER,
        ParameterType::LARGE_OBJECT => ParameterType::LARGE_OBJECT,
        ParameterType::NULL         => ParameterType::NULL,
        ParameterType::STRING       => ParameterType::STRING,
    ];

    /**
     * Драйвер базы данных.
     *
     * @var    DatabaseInterface|null
     */
    protected ?DatabaseInterface $db;

    /**
     * SQL-запрос (если была предоставлена строка прямого запроса).
     *
     * @var    string|null
     */
    protected ?string $sql = null;

    /**
     * Тип запроса.
     *
     * @var    string|null
     */
    protected ?string $type = null;

    /**
     * Псевдоним запроса.
     *
     * @var    string|null
     */
    protected ?string $alias = null;

    /**
     * Элемент запроса для общего запроса (тип = null).
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $element = null;

    /**
     * Элемент выбора.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $select = null;

    /**
     * Элемент удаления.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $delete = null;

    /**
     * Элемент обновления.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $update = null;

    /**
     * Элемент вставки.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $insert = null;

    /**
     * Элемент from.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $from = null;

    /**
     * Элементы соединения.
     *
     * @var    QueryElement[]|null
     */
    protected ?array $join = null;

    /**
     * Элемент набора.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $set = null;

    /**
     * Элемент где.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $where = null;

    /**
     * Группировка по элементам.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $group = null;

    /**
     * Имеющий элемент.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $having = null;

    /**
     * Список столбцов для инструкции INSERT.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $columns = null;

    /**
     * Список значений для инструкции INSERT.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $values = null;

    /**
     * Элемент сортировки.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $order = null;

    /**
     * Элемент поля вставки с автоинкрементом.
     *
     * @var    boolean|null
     */
    protected ?bool $autoIncrementField = null;

    /**
     * Элемент вызова.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $call = null;

    /**
     * Исполнительный элемент.
     *
     * @var    QueryElement|null
     */
    protected ?QueryElement $exec = null;

    /**
     * Список элементов запроса, который может включать UNION, UNION ALL, EXCEPT и INTERSECT.
     *
     * @var    QueryElement[]|null
     */
    protected ?array $merge = null;

    /**
     * Объект запроса.
     *
     * @var    DatabaseQuery|null
     */
    protected ?DatabaseQuery $querySet = null;

    /**
     * Подробности функции окна.
     *
     * @var    array|null
     */
    protected ?array $selectRowNumber = null;

    /**
     * Список нулевых или нулевых представлений даты и времени.
     *
     * @var    string[]
     */
    protected array $nullDatetimeList = [];

    /**
     * Смещение для набора результатов.
     *
     * @var    integer
     */
    protected int $offset = 0;

    /**
     * Предел результирующего набора.
     *
     * @var    integer
     */
    protected int $limit = 0;

    /**
     * Внутренний индекс функции bindArray для уникальных подготовленных параметров.
     *
     * @var    integer
     */
    protected int $preparedIndex = 0;

    /**
     * Конструктор класса.
     *
     * @param   ?DatabaseInterface  $db  Драйвер базы данных.
     *
     */
    public function __construct(?DatabaseInterface $db = null) {
        $this->db = $db;
    }

    /**
     * Магический метод для преобразования запроса в строку.
     *
     * @return  string  Завершенный запрос.
     *
     */
    public function __toString() {
        if ($this->sql) {
            return $this->processLimit($this->sql, $this->limit, $this->offset);
        }

        $query = '';

        switch ($this->type) {
            case 'element':
                $query .= $this->element;

                break;

            case 'select':
                $query .= $this->select;
                $query .= $this->from;

                if ($this->join) {
                    // Особый случай для объединений
                    foreach ($this->join as $join) {
                        $query .= $join;
                    }
                }

                if ($this->where) {
                    $query .= $this->where;
                }

                if ($this->selectRowNumber === null) {
                    if ($this->group) {
                        $query .= $this->group;
                    }

                    if ($this->having) {
                        $query .= $this->having;
                    }

                    if ($this->merge) {
                        // Особый случай слияния
                        foreach ($this->merge as $element) {
                            $query .= $element;
                        }
                    }
                }

                if ($this->order) {
                    $query .= $this->order;
                }

                break;

            case 'querySet':
                $query = $this->querySet;

                if ($query->order || ($query->limit || $query->offset)) {
                    // Если существует оператор ORDER BY или LIMIT, для первого запроса необходимы круглые скобки.
                    $query = "($query)";
                }

                if ($this->merge) {
                    // Особый случай слияния
                    foreach ($this->merge as $element) {
                        $query .= $element;
                    }
                }

                if ($this->order) {
                    $query .= $this->order;
                }

                break;

            case 'delete':
                $query .= $this->delete;
                $query .= $this->from;

                if ($this->join) {
                    // Особый случай для объединений
                    foreach ($this->join as $join) {
                        $query .= $join;
                    }
                }

                if ($this->where) {
                    $query .= $this->where;
                }

                break;

            case 'update':
                $query .= $this->update;

                if ($this->join) {
                    // Особый случай для объединений
                    foreach ($this->join as $join) {
                        $query .= $join;
                    }
                }

                $query .= $this->set;

                if ($this->where) {
                    $query .= $this->where;
                }

                break;

            case 'insert':
                $query .= $this->insert;

                if ($this->set) {
                    $query .= $this->set;
                } elseif ($this->values) {
                    if ($this->columns) {
                        $query .= $this->columns;
                    }

                    $elements = $this->values->getElements();

                    if (!($elements[0] instanceof $this)) {
                        $query .= ' VALUES ';
                    }

                    $query .= $this->values;
                }

                break;

            case 'call':
                $query .= $this->call;

                break;

            case 'exec':
                $query .= $this->exec;

                break;
        }

        $query = $this->processLimit($query, $this->limit, $this->offset);

        if ($this->type === 'select' && $this->alias !== null) {
            $query = '(' . $query . ') AS ' . $this->alias;
        }

        return $query;
    }

    /**
     * Магический метод для получения значения защищенной переменной
     *
     * @param string $name Имя переменной.
     *
     * @return  mixed
     *
     */
    public function __get(string $name) {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Неопределенное свойство через __get(): ' . $name . ' в ' . $trace[0]['file'] . ' строка ' . $trace[0]['line'],
            E_USER_NOTICE
        );
    }

    /**
     * Добавляет один столбец или массив столбцов в предложение CALL запроса.
     *
     * Использование:
     * $query->call('a.*')->call('b.id');
     * $query->call(array('a.*', 'b.id'));
     *
     * @param array|string $columns  Строка или массив имен полей.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function call(array|string $columns): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'call') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «call», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type = 'call';

        if ($this->call === null) {
            $this->call = new QueryElement('CALL', $columns);
        } else {
            $this->call->append($columns);
        }

        return $this;
    }

    /**
     * Приводит значение к символу.
     *
     * Перед передачей методу убедитесь, что значение правильно заключено в кавычки.
     *
     * Использование:
     * $query->select($query->castAs('CHAR', 'a'));
     *
     * @param   string   $type    Тип строки для преобразования.
     * @param   string   $value   Значение для преобразования в виде символа.
     * @param   ?string  $length  При необходимости укажите длину поля (если тип поддерживает это, иначе игнорируется).
     *
     * @return  string  Оператор SQL для приведения значения к типу char.
     *
     */
    public function castAs(string $type, string $value, ?string $length = null): string {
        return match (strtoupper($type)) {
            'CHAR'  => $value,
            default => throw new UnknownTypeException(
                sprintf(
                    'Тип %s не был распознан драйвером базы данных как допустимый для приведения.',
                    $type
                )
            ),
        };
    }

    /**
     * Получает количество символов в строке.
     *
     * Обратите внимание: используйте «длину», чтобы найти количество байтов в строке.
     *
     * Использование:
     * $query->select($query->charLength('a'));
     *
     * @param string      $field      Значение.
     * @param string|null $operator   Оператор сравнения целочисленного значения charLength и $condition
     * @param string|null $condition  Целочисленное значение для сравнения charLength.
     *
     * @return  string  Требуемый вызов длины символов.
     *
     */
    public function charLength(string $field, string $operator = null, string $condition = null): string {
        $statement = 'CHAR_LENGTH(' . $field . ')';

        if ($operator !== null && $condition !== null) {
            $statement .= ' ' . $operator . ' ' . $condition;
        }

        return $statement;
    }

    /**
     * Очищает данные из запроса или конкретного предложения запроса.
     *
     * @param string|null $clause  При необходимости можно указать имя очищаемого предложения или ничего, чтобы очистить весь запрос.
     *
     * @return  $this
     *
     */
    public function clear(?string $clause = null): static {
        $this->sql = null;

        switch ($clause) {
            case 'alias':
                $this->alias = null;
                break;

            case 'select':
                $this->select          = null;
                $this->type            = null;
                $this->selectRowNumber = null;

                break;

            case 'delete':
                $this->delete = null;
                $this->type   = null;

                break;

            case 'update':
                $this->update = null;
                $this->type   = null;

                break;

            case 'insert':
                $this->insert             = null;
                $this->type               = null;
                $this->autoIncrementField = null;

                break;

            case 'querySet':
                $this->querySet = null;
                $this->type     = null;

                break;

            case 'from':
                $this->from = null;

                break;

            case 'join':
                $this->join = null;

                break;

            case 'set':
                $this->set = null;

                break;

            case 'where':
                $this->where = null;

                break;

            case 'group':
                $this->group = null;

                break;

            case 'having':
                $this->having = null;

                break;

            case 'merge':
                $this->merge = null;

                break;

            case 'order':
                $this->order = null;

                break;

            case 'columns':
                $this->columns = null;

                break;

            case 'values':
                $this->values = null;

                break;

            case 'exec':
                $this->exec = null;
                $this->type = null;

                break;

            case 'call':
                $this->call = null;
                $this->type = null;

                break;

            case 'limit':
                $this->offset = 0;
                $this->limit  = 0;

                break;

            case 'offset':
                $this->offset = 0;

                break;

            case 'bounded':
                $this->bounded = [];

                break;

            default:
                $this->type               = null;
                $this->alias              = null;
                $this->bounded            = [];
                $this->select             = null;
                $this->selectRowNumber    = null;
                $this->delete             = null;
                $this->update             = null;
                $this->insert             = null;
                $this->querySet           = null;
                $this->from               = null;
                $this->join               = null;
                $this->set                = null;
                $this->where              = null;
                $this->group              = null;
                $this->having             = null;
                $this->merge              = null;
                $this->order              = null;
                $this->columns            = null;
                $this->values             = null;
                $this->autoIncrementField = null;
                $this->exec               = null;
                $this->call               = null;
                $this->offset             = 0;
                $this->limit              = 0;

                break;
        }

        return $this;
    }

    /**
     * Добавляет столбец или массив имен столбцов, которые будут использоваться для инструкции INSERT INTO.
     *
     * @param array|string $columns  Имя столбца или массив имен столбцов.
     *
     * @return  $this
     *
     */
    public function columns(array|string $columns): static {
        if ($this->columns === null) {
            $this->columns = new QueryElement('()', $columns);
        } else {
            $this->columns->append($columns);
        }

        return $this;
    }

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * Использование:
     * $query->select($query->concatenate(array('a', 'b')));
     *
     * @param string[]    $values        Массив значений для объединения.
     * @param string|null $separator     В качестве разделителя между каждым значением.
     *
     * @return  string  Объединенные значения.
     *
     */
    public function concatenate(array $values, string $separator = null): string {
        if ($separator !== null) {
            return 'CONCATENATE(' . implode(' || ' . $this->quote($separator) . ' || ', $values) . ')';
        }

        return 'CONCATENATE(' . implode(' || ', $values) . ')';
    }

    /**
     * Получает текущую дату и время.
     *
     * Использование:
     * $query->where('published_up < '.$query->currentTimestamp());
     *
     * @return  string
     *
     */
    public function currentTimestamp(): string {
        return 'CURRENT_TIMESTAMP()';
    }

    /**
     * Добавляет к текущей дате и времени.
     *
     * Использование:
     * $query->select($query->dateAdd());
     *
     * Если перед интервалом поставить знак -(отрицательный знак), будет использоваться вычитание.
     * Примечание. Не все драйверы поддерживают все устройства.
     *
     * @param string $date      Строковое представление даты, которую нужно добавить, в кавычках базы данных.
     *                          Может быть дата или дата и время.
     * @param string $interval  Строковое представление соответствующего количества единиц.
     * @param string $datePart  Часть даты для выполнения сложения.
     *
     * @return  string  Строка с соответствующим sql для добавления дат.
     *
     * @link    https://dev.mysql.com/doc/en/date-and-time-functions.html
     */
    public function dateAdd(string $date, string $interval, string $datePart): string {
        return 'DATE_ADD(' . $date . ', INTERVAL ' . $interval . ' ' . $datePart . ')';
    }

    /**
     * Возвращает формат даты, совместимый с функцией PHP date(), для драйвера базы данных.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод getDateFormat.
     *
     * @return  string  Строка формата.
     *
     * @throws  \RuntimeException
     */
    public function dateFormat(): string {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        return $this->db->getDateFormat();
    }

    /**
     * Добавляет имя таблицы в предложение DELETE запроса.
     *
     * Использование:
     * $query->delete('#__a')->where('id = 1');
     *
     * @param string|null $table  Имя таблицы, из которой требуется удалить.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен.
     */
    public function delete(string $table = null): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'delete') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «delete», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type   = 'delete';
        $this->delete = new QueryElement('DELETE', null);

        if (!empty($table)) {
            $this->from($table);
        }

        return $this;
    }

    /**
     * Псевдоним для метода escape
     *
     * @param string  $text   Строка, которую нужно экранировать.
     * @param boolean $extra  Необязательный параметр для обеспечения дополнительного экранирования.
     *
     * @return  string  Экранированная строка.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function e(string $text, bool $extra = false): string {
        return $this->escape($text, $extra);
    }

    /**
     * Метод экранирования строки для использования в инструкции SQL.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать escape-метод.
     *
     * Обратите внимание, что «e» — это псевдоним этого метода, как и в DatabaseDriver.
     *
     * @param string  $text   Строка, которую нужно экранировать.
     * @param boolean $extra  Необязательный параметр для обеспечения дополнительного экранирования.
     *
     * @return  string  Экранированная строка.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function escape(string $text, bool $extra = false): string {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        return $this->db->escape($text, $extra);
    }

    /**
     * Добавляет один столбец или массив столбцов в предложение EXEC запроса.
     *
     * Использование:
     * $query->exec('a.*')->exec('b.id');
     * $query->exec(array('a.*', 'b.id'));
     *
     * @param array|string $columns  Строка или массив имен полей.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен.
     */
    public function exec(array|string $columns): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'exec') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «exec», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type = 'exec';

        if ($this->exec === null) {
            $this->exec = new QueryElement('EXEC', $columns);
        } else {
            $this->exec->append($columns);
        }

        return $this;
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
        return '';
    }

    /**
     * Добавляет таблицу в предложение FROM запроса.
     *
     * Использование:
     * $query->select('*')->from('#__a');
     * $query->select('*')->from($subquery->alias('a'));
     *
     * @param string|QueryInterface $table  Имя таблицы или объекта DatabaseQuery (или его дочернего объекта) с установленным псевдонимом.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function from(QueryInterface|string $table): static {
        if ($table instanceof $this && $table->alias === null) {
            throw new \RuntimeException('Ошибка базы данных. псевдоним под запроса не может быть null.');
        }

        if ($this->from === null) {
            $this->from = new QueryElement('FROM', $table);
        } else {
            $this->from->append($table);
        }

        return $this;
    }

    /**
     * Добавляет псевдоним для текущего запроса.
     *
     * Использование:
     * $query->select('*')->from('#__a')->alias('subquery');
     *
     * @param string $alias Псевдоним, используемый для DatabaseQuery.
     *
     * @return  $this
     */
    public function alias(string $alias): static {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Используется для получения строки для извлечения года из столбца даты.
     *
     * Использование:
     * $query->select($query->year($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий год, который необходимо извлечь.
     *
     * @return  string  Возвращает строку для извлечения года из даты.
     *
     */
    public function year(string $date): string {
        return 'YEAR(' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения месяца из столбца даты.
     *
     * Использование:
     * $query->select($query->month($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемый месяц.
     *
     * @return  string  Возвращает строку для извлечения месяца из даты.
     *
     */
    public function month(string $date): string {
        return 'MONTH(' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения дня из столбца даты.
     *
     * Использование:
     * $query->select($query->day($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий день, который необходимо извлечь.
     *
     * @return  string  Возвращает строку для извлечения дня из даты.
     *
     */
    public function day(string $date): string {
        return 'DAY(' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения часа из столбца даты.
     *
     * Использование:
     * $query->select($query->hour($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемый час.
     *
     * @return  string  Возвращает строку для извлечения часа из даты.
     *
     */
    public function hour(string $date): string {
        return 'HOUR(' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения минут из столбца даты.
     *
     * Использование:
     * $query->select($query->minute($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемые минуты.
     *
     * @return  string  Возвращает строку для извлечения минут из даты.
     *
     */
    public function minute(string $date): string {
        return 'MINUTE(' . $date . ')';
    }

    /**
     * Используется для получения строки для извлечения секунд из столбца даты.
     *
     * Использование:
     * $query->select($query->second($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий секунду, которую нужно извлечь.
     *
     * @return  string  Возвращает строку для извлечения секунды из даты.
     *
     */
    public function second(string $date): string {
        return 'SECOND(' . $date . ')';
    }

    /**
     * Добавляет столбец группировки в предложение GROUP запроса.
     *
     * Использование:
     * $query->group('id');
     *
     * @param array|string $columns  Строка или массив столбцов упорядочения.
     *
     * @return  $this
     *
     */
    public function group(array|string $columns): static {
        if ($this->group === null) {
            $this->group = new QueryElement('GROUP BY', $columns);
        } else {
            $this->group->append($columns);
        }

        return $this;
    }

    /**
     * Условия для предложения HAVING запроса.
     *
     * Использование:
     * $query->group('id')->having('COUNT(id) > 5');
     *
     * @param array|string $conditions  Строка или массив столбцов.
     * @param string       $glue        Объединение, с помощью которого можно соединить условия.
     *                                  По умолчанию используется AND.
     *
     * @return  $this
     *
     */
    public function having(array|string $conditions, string $glue = 'AND'): static {
        if ($this->having === null) {
            $glue         = strtoupper($glue);
            $this->having = new QueryElement('HAVING', $conditions, " $glue ");
        } else {
            $this->having->append($conditions);
        }

        return $this;
    }

    /**
     * Добавляет имя таблицы в предложение INSERT запроса.
     *
     * Использование:
     * $query->insert('#__a')->set('id = 1');
     * $query->insert('#__a')->columns('id, title')->values('1,2')->values('3,4');
     * $query->insert('#__a')->columns('id, title')->values(array('1,2', '3,4'));
     *
     * @param string  $table           Имя таблицы, в которую нужно вставить данные.
     * @param boolean $incrementField  Имя поля для автоматического увеличения.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен.
     */
    public function insert(string $table, bool $incrementField = false): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'insert') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «insert», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type               = 'insert';
        $this->insert             = new QueryElement('INSERT INTO', $table);
        $this->autoIncrementField = $incrementField;

        return $this;
    }

    /**
     * Добавляет в запрос предложение JOIN.
     *
     * Использование:
     * $query->join('INNER', 'b', 'b.id = a.id);
     *
     * @param string      $type       Тип соединения. Эта строка добавляется к ключевому слову JOIN.
     * @param string      $table      Имя таблицы.
     * @param string|null $condition  Условие соединения.
     *
     * @return  $this
     *
     */
    public function join(string $type, string $table, string $condition = null): static {
        $type = strtoupper($type) . ' JOIN';

        if ($condition !== null) {
            $this->join[] = new QueryElement($type, [$table, $condition], ' ON ');
        } else {
            $this->join[] = new QueryElement($type, $table);
        }

        return $this;
    }

    /**
     * Добавляет в запрос предложение INNER JOIN.
     *
     * Использование:
     * $query->innerJoin('b', 'b.id = a.id')->innerJoin('c', 'c.id = b.id');
     *
     * @param   string  $table      Имя таблицы.
     * @param   string  $condition  Условие соединения.
     *
     * @return  $this
     *
     */
    public function innerJoin($table, $condition = null): static {
        return $this->join('INNER', $table, $condition);
    }

    /**
     * Добавляет в запрос предложение OUTER JOIN.
     *
     * Использование:
     * $query->outerJoin('b', 'b.id = a.id')->leftJoin('c', 'c.id = b.id');
     *
     * @param string      $table      Имя таблицы.
     * @param string|null $condition  Условие соединения.
     *
     * @return  $this
     *
     */
    public function outerJoin(string $table, string $condition = null): static {
        return $this->join('OUTER', $table, $condition);
    }

    /**
     * Добавляет в запрос предложение LEFT JOIN.
     *
     * Использование:
     * $query->leftJoin('b', 'b.id = a.id')->leftJoin('c', 'c.id = b.id');
     *
     * @param string      $table      Имя таблицы.
     * @param string|null $condition  Условие соединения.
     *
     * @return  $this
     *
     */
    public function leftJoin(string $table, string $condition = null): static {
        return $this->join('LEFT', $table, $condition);
    }

    /**
     * Добавляет в запрос предложение RIGHT JOIN.
     *
     * Использование:
     * $query->rightJoin('b', 'b.id = a.id')->rightJoin('c', 'c.id = b.id');
     *
     * @param string      $table      Имя таблицы.
     * @param string|null $condition  Условие соединения.
     *
     * @return  $this
     *
     */
    public function rightJoin(string $table, string $condition = null): static {
        return $this->join('RIGHT', $table, $condition);
    }

    /**
     * Возвращает длину строки в байтах.
     *
     * Обратите внимание: используйте «charLength», чтобы найти количество символов в строке.
     *
     * Использование:
     * query->where($query->length('a').' > 3');
     *
     * @param string $value  Строка для измерения.
     *
     * @return  string
     *
     */
    public function length(string $value): string {
        return 'LENGTH(' . $value . ')';
    }

    /**
     * Возвращает NULL или нулевое представление временной метки для драйвера базы данных.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод nullDate.
     *
     * Использование:
     * $query->where('modified_date <> '.$query->nullDate());
     *
     * @param boolean $quoted  При необходимости нулевую дату помещает в кавычки базы данных (по умолчанию true).
     *
     * @return  string  NULL или пустое представление метки времени.
     *
     * @throws  \RuntimeException
     */
    public function nullDate(bool $quoted = true): string {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        $result = $this->db->getNullDate();

        if ($quoted) {
            return $this->db->quote($result);
        }

        return $result;
    }

    /**
     * Создаёт оператор SQL, чтобы проверить, представляет ли столбец нулевое или нулевое значение даты и времени.
     *
     * Использование:
     * $query->where($query->isNullDatetime('modified_date'));
     *
     * @param string $column Имя столбца.
     *
     * @return  string
     */
    public function isNullDatetime(string $column): string {
        if (!$this->db instanceof DatabaseInterface) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        if ($this->nullDatetimeList) {
            return "($column IN ("
            . implode(', ', $this->db->quote($this->nullDatetimeList))
            . ") OR $column IS NULL)";
        }

        return "$column IS NULL";
    }

    /**
     * Добавляет столбец упорядочивания в предложение ORDER запроса.
     *
     * Использование:
     * $query->order('foo')->order('bar');
     * $query->order(array('foo','bar'));
     *
     * @param array|string $columns  Строка или массив столбцов упорядочения.
     *
     * @return  $this
     *
     */
    public function order(array|string $columns): static {
        if ($this->order === null) {
            $this->order = new QueryElement('ORDER BY', $columns);
        } else {
            $this->order->append($columns);
        }

        return $this;
    }

    /**
     * Псевдоним метода цитаты
     *
     * @param array|string $text    Строка или массив строк для цитирования.
     * @param boolean      $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  string  Входная строка в кавычках.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function q(array|string $text, bool $escape = true): string {
        return $this->quote($text, $escape);
    }

    /**
     * Метод для цитирования и, при необходимости, экранирования строки, соответствующей требованиям базы данных для вставки в базу данных.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод quote.
     *
     * Обратите внимание, что «q» — это псевдоним этого метода, как и в DatabaseDriver.
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
    public function quote(array|string $text, bool $escape = true): string {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        return $this->db->quote($text, $escape);
    }

    /**
     * Псевдоним для метода quoteName
     *
     * @param array|string      $name  Имя идентификатора, заключаемое в кавычки, или массив имен идентификаторов, заключаемый в кавычки.
     *                                 Каждый тип поддерживает имя в виде точечной записи.
     * @param array|string|null $as    Часть запроса AS, связанная с $name. Это может быть строка или массив,
     *                                 в последнем случае длина должна быть такой же, как $name;
     *                                 если значение равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function qn(array|string $name, array|string $as = null): array|string {
        return $this->quoteName($name, $as);
    }

    /**
     * Заключает имя идентификатора оператора SQL, такое как имена столбцов, таблиц или баз данных, в кавычки,
     * чтобы предотвратить риски внедрения и конфликты зарезервированных слов.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод quoteName.
     *
     * Обратите внимание, что «qn» — это псевдоним этого метода, как и в DatabaseDriver.
     *
     * Использование:
     * $query->quoteName('#__a');
     * $query->qn('#__a');
     *
     * @param array|string      $name  Имя идентификатора, заключаемое в кавычки, или массив имен идентификаторов, заключаемый в кавычки.
     *                                 Каждый тип поддерживает имя в виде точечной записи.
     * @param array|string|null $as    Часть запроса AS, связанная с $name. Это может быть строка или массив,
     *                                 в последнем случае длина должна быть такой же, как $name;
     *                                 если значение равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function quoteName(array|string $name, array|string $as = null): array|string {
        if (!($this->db instanceof DatabaseInterface)) {
            throw new \RuntimeException(sprintf('Экземпляр %s не установлен для объекта запроса.', DatabaseInterface::class));
        }

        return $this->db->quoteName($name, $as);
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
        return '';
    }

    /**
     * Возвращает оператор регулярного выражения
     *
     * Использование:
     * $query->where('field ' . $query->regexp($search));
     *
     * @param string $value  Шаблон регулярного выражения.
     *
     * @return  string
     *
     */
    public function regexp(string $value): string {
        return ' ' . $value;
    }

    /**
     * Добавляет один столбец или массив столбцов в предложение SELECT запроса.
     *
     * Обратите внимание, что при построении запроса нельзя смешивать вызовы методов вставки, обновления, удаления и выбора.
     * Однако метод select можно вызывать несколько раз в одном запросе.
     *
     * Использование:
     * $query->select('a.*')->select('b.id');
     * $query->select(array('a.*', 'b.id'));
     *
     * @param array|string $columns  Строка или массив имен полей.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function select(array|string $columns): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'select') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «select», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type = 'select';

        if ($this->select === null) {
            $this->select = new QueryElement('SELECT', $columns);
        } else {
            $this->select->append($columns);
        }

        return $this;
    }

    /**
     * Добавляет одну строку условия или массив строк в предложение SET запроса.
     *
     * Использование:
     * $query->set('a = 1')->set('b = 2');
     * $query->set(array('a = 1', 'b = 2');
     *
     * @param array|string $conditions  Строка или массив строковых условий.
     * @param string       $glue        Связующий элемент, с помощью которого можно соединить строки условия. По умолчанию ','.
     *                                  Обратите внимание, что объединение фиксируется при первом использовании и не может быть изменен.
     *
     * @return  $this
     *
     */
    public function set(array|string $conditions, string $glue = ','): static {
        if ($this->set === null) {
            $glue      = strtoupper($glue);
            $this->set = new QueryElement('SET', $conditions, \PHP_EOL . "\t$glue ");
        } else {
            $this->set->append($conditions);
        }

        return $this;
    }

    /**
     * Устанавливает смещение и предел для набора результатов, если драйвер базы данных поддерживает это.
     *
     * Использование:
     * $query->setLimit(100, 0); (получить 100 строк, начиная с первой записи)
     * $query->setLimit(50, 50); (получить 50 строк, начиная с 50-й записи)
     *
     * @param integer $limit   Предел для набора результатов
     * @param integer $offset  Смещение для набора результатов
     *
     * @return  $this
     */
    public function setLimit(int $limit = 0, int $offset = 0): static {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * Позволяет отправлять прямой запрос к методу setQuery() драйвера базы данных,
     * но при этом разрешает запросам иметь ограниченные переменные.
     *
     * Использование:
     * $query->setQuery('select * from #__users');
     *
     * @param   DatabaseQuery|string  $sql  Строка запроса SQL или объект DatabaseQuery.
     *
     * @return  $this
     *
     */
    public function setQuery($sql): static {
        $this->sql = $sql;

        return $this;
    }

    /**
     * Добавляет имя таблицы в предложение UPDATE запроса.
     *
     * Использование:
     * $query->update('#__foo')->set(...);
     *
     * @param string $table  Таблица для обновления.
     *
     * @return  $this
     *
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен.
     */
    public function update(string $table): static {
        if ($this->type !== null && $this->type !== '' && $this->type !== 'update') {
            throw new QueryTypeAlreadyDefinedException(
                \sprintf(
                    'Невозможно установить тип запроса «update», поскольку тип запроса уже установлен на «%s».'
                        . ' Вам следует либо вызвать метод `clear()`, чтобы сбросить тип, либо создать новый объект запроса.',
                    $this->type
                )
            );
        }

        $this->type   = 'update';
        $this->update = new QueryElement('UPDATE', $table);

        return $this;
    }

    /**
     * Добавляет набор или массив наборов, которые будут использоваться в качестве значений для инструкции INSERT INTO.
     *
     * Использование:
     * $query->values('1,2,3')->values('4,5,6');
     * $query->values(array('1,2,3', '4,5,6'));
     *
     * @param array|string $values  Один набор или массив наборов.
     *
     * @return  $this
     *
     */
    public function values(array|string $values): static {
        if ($this->values === null) {
            $this->values = new QueryElement('()', $values, '),(');
        } else {
            $this->values->append($values);
        }

        return $this;
    }

    /**
     * Добавляет одно условие или массив условий в предложение WHERE запроса.
     *
     * Использование:
     * $query->where('a = 1')->where('b = 2');
     * $query->where(array('a = 1', 'b = 2'));
     *
     * @param array|string $conditions  Строка или массив условий.
     * @param string       $glue        Объединение, с помощью которого можно соединить условия. По умолчанию используется AND.
     *                                  Обратите внимание, что объединение фиксируется при первом использовании и не может быть изменен.
     *
     * @return  $this
     *
     */
    public function where(array|string $conditions, string $glue = 'AND'): static {
        if ($this->where === null) {
            $glue        = strtoupper($glue);
            $this->where = new QueryElement('WHERE', $conditions, " $glue ");
        } else {
            $this->where->append($conditions);
        }

        return $this;
    }

    /**
     * Добавляет в запрос оператор WHERE IN.
     *
     * Обратите внимание, что все значения должны быть одного типа данных.
     *
     * Использование:
     * $query->whereIn('id', [1, 2, 3]);
     *
     * @param   string        $keyName    Ключевое имя для предложения where.
     * @param   array         $keyValues  Массив значений для сопоставления.
     * @param array|string    $dataType   Константа, соответствующая типу данных SQL.
     *                                    Это может быть массив, в этом случае он должен иметь ту же длину, что и $keyValues.
     *
     * @return  $this
     */
    public function whereIn(string $keyName, array $keyValues, array|string $dataType = ParameterType::INTEGER): static {
        return $this->where(
            $keyName . ' IN (' . implode(',', $this->bindArray($keyValues, $dataType)) . ')'
        );
    }

    /**
     * Добавляет в запрос оператор WHERE NOT IN.
     *
     * Обратите внимание, что все значения должны быть одного типа данных.
     *
     * Usage
     * $query->whereNotIn('id', [1, 2, 3]);
     *
     * @param   string        $keyName    Ключевое имя для предложения where
     * @param   array         $keyValues  Массив значений для сопоставления
     * @param array|string    $dataType   Константа, соответствующая типу данных SQL.
     *                                    Это может быть массив, в этом случае он должен иметь ту же длину, что и $keyValues.
     *
     * @return  $this
     */
    public function whereNotIn(string $keyName, array $keyValues, array|string $dataType = ParameterType::INTEGER): static {
        return $this->where(
            $keyName . ' NOT IN (' . implode(',', $this->bindArray($keyValues, $dataType)) . ')'
        );
    }

    /**
     * Расширяет предложение WHERE одним условием или массивом условий,
     * используя потенциально другой логический оператор,
     * отличный от оператора в текущем предложении WHERE.
     *
     * Использование:
     * $query->where(array('a = 1', 'b = 2'))->extendWhere('XOR', array('c = 3', 'd = 4'));
     * будет производить: WHERE ((a = 1 AND b = 2) XOR (c = 3 AND d = 4)
     *
     * @param string $outerGlue   Связующее звено, с помощью которого можно соединить условия с текущими условиями WHERE.
     * @param mixed  $conditions  Строка или массив условий WHERE.
     * @param string $innerGlue   Объединение, с помощью которого можно соединить условия.
     *                            По умолчанию используется AND.
     *
     * @return  $this
     */
    public function extendWhere(string $outerGlue, mixed $conditions, string $innerGlue = 'AND'): static {
        $this->where = new QueryElement('WHERE', $this->where->setName('()'), " $outerGlue ");
        $this->where->append(new QueryElement('()', $conditions, " $innerGlue "));

        return $this;
    }

    /**
     * Расширяет предложение WHERE с помощью OR и одного условия или массива условий.
     *
     * Использование:
     * $query->where(array('a = 1', 'b = 2'))->orWhere(array('c = 3', 'd = 4'));
     * будет производить: WHERE ((a = 1 AND b = 2) OR (c = 3 AND d = 4)
     *
     * @param   mixed   $conditions  Строка или массив условий WHERE.
     * @param   string  $glue        Объединение, с помощью которого можно соединить условия.
     *                               По умолчанию используется AND.
     *
     * @return  $this
     */
    public function orWhere($conditions, $glue = 'AND'): static {
        return $this->extendWhere('OR', $conditions, $glue);
    }

    /**
     * Расширяет предложение WHERE с помощью AND и одного условия или массива условий.
     *
     * Использование:
     * $query->where(array('a = 1', 'b = 2'))->andWhere(array('c = 3', 'd = 4'));
     * будет производить: WHERE ((a = 1 AND b = 2) AND (c = 3 OR d = 4)
     *
     * @param   mixed   $conditions  Строка или массив условий WHERE.
     * @param   string  $glue        Объединение, с помощью которого можно соединить условия.
     *                               По умолчанию OR.
     *
     * @return  $this
     */
    public function andWhere(mixed $conditions, string $glue = 'OR'): static {
        return $this->extendWhere('AND', $conditions, $glue);
    }

    /**
     * Метод для добавления переменной во внутренний массив, которая будет привязана к подготовленному оператору SQL перед выполнением запроса.
     *
     * @param array|integer|string $key            Ключ, который будет использоваться в вашем SQL-запросе для ссылки на значение.
     *                                             Обычно имеет форму «:key», но также может быть целым числом.
     * @param   mixed              $value          Значение, которое будет привязано. Это может быть массив, в этом случае он должен иметь ту же длину, что и $key;
     *                                             Значение передается по ссылке для поддержки выходных параметров, например тех, которые возможны при использовании хранимых процедур.
     * @param array|string         $dataType       Константа, соответствующая типу данных SQL. Это может быть массив, в этом случае он должен иметь ту же длину, что и $key.
     * @param integer              $length         Длина переменной. Обычно требуется для выходных параметров.
     * @param array                $driverOptions  Дополнительные параметры драйвера, которые будут использоваться.
     *
     * @return  $this
     *
     * @throws  \InvalidArgumentException
     */
    public function bind(array|int|string $key, mixed &$value, array|string $dataType = ParameterType::STRING, int $length = 0, array $driverOptions = []): static {
        if (!$key) {
            throw new \InvalidArgumentException('Требуется ключ');
        }

        $key   = (array) $key;
        $count = \count($key);

        if (\is_array($value)) {
            if ($count != \count($value)) {
                throw new \InvalidArgumentException('Длина массива $key и $value не равна');
            }

            reset($value);
        }

        if (\is_array($dataType) && $count != \count($dataType)) {
            throw new \InvalidArgumentException('Длина массива $key и $dataType не равна');
        }

        foreach ($key as $index) {
            if (\is_array($value)) {
                $localValue = &$value[key($value)];
                next($value);
            } else {
                $localValue = &$value;
            }

            if (\is_array($dataType)) {
                $localDataType = array_shift($dataType);
            } else {
                $localDataType = $dataType;
            }

            if (!isset($this->parameterMapping[$localDataType])) {
                throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип параметра `%s`', $localDataType));
            }

            $obj                = new \stdClass();
            $obj->value         = &$localValue;
            $obj->dataType      = $this->parameterMapping[$localDataType];
            $obj->length        = $length;
            $obj->driverOptions = $driverOptions;

            $this->bounded[$index] = $obj;

            unset($localValue);
        }

        return $this;
    }

    /**
     * Метод для отмены привязки связанной переменной.
     *
     * @param array|integer|string $key  Ключ или массив ключей для отмены привязки.
     *
     * @return  $this
     */
    public function unbind(array|int|string $key): static {
        if (\is_array($key)) {
            foreach ($key as $k) {
                unset($this->bounded[$k]);
            }
        } else {
            unset($this->bounded[$key]);
        }

        return $this;
    }

    /**
     * Связывает массив значений и возвращает массив имен подготовленных параметров.
     *
     * Обратите внимание, что все значения должны быть одного типа данных.
     *
     * Использование:
     * $query->where('column in (' . implode(',', $query->bindArray($keyValues, $dataType)) . ')');
     *
     * @param   array         $values    Значения для привязки
     * @param array|string    $dataType  Константа, соответствующая типу данных SQL.
     *                                   Это может быть массив, в этом случае он должен иметь ту же длину, что и $key.
     *
     * @return  array   Массив с именами параметров
     */
    public function bindArray(array $values, array|string $dataType = ParameterType::INTEGER): array {
        $parameterNames = [];

        for ($i = 0; $i < count($values); $i++) {
            $parameterNames[] = ':preparedArray' . (++$this->preparedIndex);
        }

        $this->bind($parameterNames, $values, $dataType);

        return $parameterNames;
    }

    /**
     * Метод обеспечения базовой поддержки копирования.
     *
     * Любой объект, помещенный в данные этого класса, должен иметь собственную реализацию __clone().
     * Этот метод не поддерживает копирование объектов в многомерный массив.
     *
     * @return  void
     *
     */
    public function __clone() {
        foreach ($this as $k => $v) {
            if ($k === 'db') {
                continue;
            }

            if (\is_object($v)) {
                $this->{$k} = clone $v;
            } elseif (\is_array($v)) {
                foreach ($v as $i => $element) {
                    if (\is_object($element)) {
                        $this->{$k}[$i] = clone $element;
                    }
                }
            }
        }
    }

    /**
     * Извлекает массив связанных параметров, когда ключ имеет значение NULL, и возвращает его по ссылке.
     * Если указан ключ, этот элемент возвращается.
     *
     * @param mixed|null $key  Ограниченный переменный ключ, который необходимо получить.
     *
     * @return  mixed
     *
     */
    public function &getBounded(mixed $key = null) {
        if (empty($key)) {
            return $this->bounded;
        }

        if (isset($this->bounded[$key])) {
            return $this->bounded[$key];
        }
    }

    /**
     * Объедините оператор выбора с текущим запросом с помощью одного из операторов набора.
     * Операторы: UNION, UNION ALL, EXCEPT or INTERSECT.
     *
     * @param string               $name   Имя оператора установки в скобках.
     * @param string|DatabaseQuery $query  Объект или строка DatabaseQuery.
     *
     * @return  $this
     */
    protected function merge(string $name, DatabaseQuery|string $query): static {
        $this->type = $this->type ?: 'select';

        $this->merge[] = new QueryElement($name, $query);

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
     * @param boolean              $distinct  Значение true, чтобы возвращать из объединения только отдельные строки.
     *
     * @return  $this
     *
     */
    public function union(DatabaseQuery|string $query, bool $distinct = true): static {
        return $this->merge($distinct ? 'UNION ()' : 'UNION ALL ()', $query);
    }

    /**
     * Добавляет запрос в UNION ALL с текущим запросом.
     *
     * Использование:
     * $query->unionAll('SELECT name FROM  #__foo')
     *
     * @param string|DatabaseQuery $query     Объект DatabaseQuery или строка для объединения.
     *
     * @return  $this
     *
     * @see     union
     */
    public function unionAll(DatabaseQuery|string $query): static {
        return $this->union($query, false);
    }

    /**
     * Устанавливает один запрос в набор запросов.
     * В этом типе DatabaseQuery вы можете использовать Union(), UnionAll(), order() и setLimit().
     *
     * Использование:
     * $query->querySet($query2->select('name')->from('#__foo')->order('id DESC')->setLimit(1))
     *       ->unionAll($query3->select('name')->from('#__foo')->order('id')->setLimit(1))
     *       ->order('name')
     *       ->setLimit(1)
     *
     * @param string|DatabaseQuery $query  Объект или строка DatabaseQuery.
     *
     * @return  $this
     */
    public function querySet(DatabaseQuery|string $query): static {
        $this->type = 'querySet';

        $this->querySet = $query;

        return $this;
    }

    /**
     * Создаёт объект DatabaseQuery типа querySet из текущего запроса.
     *
     * Использование:
     * $query->select('name')->from('#__foo')->order('id DESC')->setLimit(1)
     *       ->toQuerySet()
     *       ->unionAll($query2->select('name')->from('#__foo')->order('id')->setLimit(1))
     *       ->order('name')
     *       ->setLimit(1)
     *
     * @return  DatabaseQuery  Новый объект DatabaseQuery.
     */
    public function toQuerySet(): DatabaseQuery {
        return (new static($this->db))->querySet($this);
    }

    /**
     * Найдите и замените токены типа sprintf в строке формата.
     * Каждый токен принимает одну из следующих форм:
     *     %%       - Буквальный символ процента.
     *     %[t]     - Где [t] — спецификатор типа.
     *     %[n]$[t] - Где [n] — спецификатор аргумента, а [t] — спецификатор типа.
     *
     * Типы:
     * a - Numeric: текст замены приводится к числовому типу, но не заключен в кавычки и не экранирован.
     * e - Escape: текст замены передается в $this->escape().
     * E - Escape (дополнительно): текст замены передается в $this->escape() со вторым аргументом true.
     * n - Quote name: Текст замены передается в $this->quoteName().
     * q - Quote: Текст замены передается в $this->quote().
     * Q - Quote (без escape): Текст замены передается в $this->quote() со вторым аргументом false.
     * r - Raw: текст замены используется как есть. (Будь осторожен)
     *
     * Типы дат:
     * - Текст замены автоматически цитируется (в качестве кавычки имени используйте прописные буквы).
     * - Текст замены должен представлять собой строку в формате даты или имя столбца даты.
     * y/Y - Год
     * m/M - Месяц
     * d/D - День
     * h/H - Час
     * i/I - Минуты
     * s/S - Секунды
     *
     * Неизменные типы:
     * - Не принимает аргументов.
     * - Индекс аргумента не увеличен.
     * t - Текст замены является результатом $this->currentTimestamp().
     * z - Текст замены является результатом $this->nullDate(false).
     * Z - Текст замены является результатом $this->nullDate(true).
     *
     * Использование:
     * $query->format('SELECT %1$n FROM %2$n WHERE %3$n = %4$a', 'foo', '#__foo', 'bar', 1);
     * Возврат: SELECT `foo` FROM `#__foo` WHERE `bar` = 1
     *
     * Примечания:
     * Спецификатор аргумента не является обязательным, но рекомендуется для ясности.
     * Индекс аргумента, используемый для неуказанных токенов, увеличивается только при его использовании.
     *
     * @param string $format  Строка форматирования.
     *
     * @return  string  Возвращает строку, созданную в соответствии со строкой форматирования.
     *
     */
    public function format(string $format): string {
        $query = $this;
        $args  = \array_slice(\func_get_args(), 1);
        array_unshift($args, null);

        $i    = 1;
        $func = function ($match) use ($query, $args, &$i) {
            if (isset($match[6]) && $match[6] === '%') {
                return '%';
            }

            switch ($match[5]) {
                case 't':
                    return $query->currentTimestamp();

                case 'z':
                    return $query->nullDate(false);

                case 'Z':
                    return $query->nullDate(true);
            }

            $index = is_numeric($match[4]) ? (int) $match[4] : $i++;

            if (!$index || !isset($args[$index])) {
                $replacement = '';
            } else {
                $replacement = $args[$index];
            }

            switch ($match[5]) {
                case 'a':
                    return 0 + $replacement;

                case 'e':
                    return $query->escape($replacement);

                case 'E':
                    return $query->escape($replacement, true);

                case 'n':
                    return $query->quoteName($replacement);

                case 'q':
                    return $query->quote($replacement);

                case 'Q':
                    return $query->quote($replacement, false);

                case 'r':
                    return $replacement;

                case 'y':
                    return $query->year($query->quote($replacement));

                case 'Y':
                    return $query->year($query->quoteName($replacement));

                case 'm':
                    return $query->month($query->quote($replacement));

                case 'M':
                    return $query->month($query->quoteName($replacement));

                case 'd':
                    return $query->day($query->quote($replacement));

                case 'D':
                    return $query->day($query->quoteName($replacement));

                case 'h':
                    return $query->hour($query->quote($replacement));

                case 'H':
                    return $query->hour($query->quoteName($replacement));

                case 'i':
                    return $query->minute($query->quote($replacement));

                case 'I':
                    return $query->minute($query->quoteName($replacement));

                case 's':
                    return $query->second($query->quote($replacement));

                case 'S':
                    return $query->second($query->quoteName($replacement));
            }

            return '';
        };

        /**
         * Regex для поиска и замены всех токенов.
         * Соответствующие поля:
         * 0: Полный токен
         * 1: Все следующее «%»
         * 2: Все, что следует за «%», кроме «%»
         * 3: Спецификатор аргумента и «$»
         * 4: Спецификатор аргумента
         * 5: Спецификатор типа
         * 6: «%», если полный токен равен «%%»
         */
        return preg_replace_callback('#%(((([\d]+)\$)?([aeEnqQryYmMdDhHiIsStzZ]))|(%))#', $func, $format);
    }

    /**
     * Проверяет аргументы, передаваемые в метод selectRowNumber, и настройте общие переменные.
     *
     * @param string $orderBy           Выражение порядка для оконной функции.
     * @param string $orderColumnAlias  Псевдоним для нового столбца сортировки.
     *
     * @return  void
     * @throws  \RuntimeException
     */
    protected function validateRowNumber(string $orderBy, string $orderColumnAlias): void {
        if ($this->selectRowNumber) {
            throw new \RuntimeException("Метод «selectRowNumber» можно вызывать только один раз для каждого экземпляра.");
        }

        $this->type = 'select';

        $this->selectRowNumber = [
            'orderBy'          => $orderBy,
            'orderColumnAlias' => $orderColumnAlias,
        ];
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
     * @param string $orderColumnAlias  Псевдоним для нового столбца сортировки.
     *
     * @return  $this
     * @throws  \RuntimeException
     */
    public function selectRowNumber(string $orderBy, string $orderColumnAlias): static {
        $this->validateRowNumber($orderBy, $orderColumnAlias);

        return $this->select("ROW_NUMBER() OVER (ORDER BY $orderBy) AS $orderColumnAlias");
    }
}
