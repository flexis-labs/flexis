<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Flexis\Database\Exception\QueryTypeAlreadyDefinedException;
use Flexis\Database\Exception\UnknownTypeException;

/**
 * Интерфейс построения запросов Flexis Framework.
 */
interface QueryInterface {
    /**
     * Преобразует объект запроса в строку.
     *
     * @return  string
     */
    public function __toString();

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
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function call(array|string $columns): static;

    /**
     * Приводит значение к указанному типу.
     *
     * Перед передачей методу убедитесь, что значение правильно заключено в кавычки.
     *
     * Использование:
     * $query->select($query->castAs('CHAR', 'a'));
     *
     * @param string      $type     Тип строки для преобразования.
     * @param string      $value    Значение для преобразования в виде символа.
     * @param string|null $length   При желании укажите длину поля
     *                              (если тип поддерживает это, иначе игнорируется).
     *
     * @return  string  Оператор SQL для приведения значения к типу char.
     * @throws UnknownTypeException Когда не поддерживается приведение для драйвера базы данных
     */
    public function castAs(string $type, string $value, ?string $length = null): string;

    /**
     * Получает количество символов в строке.
     *
     * Обратите внимание: используйте «длину», чтобы найти количество байтов в строке.
     *
     * Использование:
     * $query->select($query->charLength('a'));
     *
     * @param string      $field      Значение.
     * @param string|null $operator   Оператор сравнения целочисленного значения charLength и $condition.
     * @param string|null $condition  Целочисленное значение для сравнения charLength.
     *
     * @return  string  Оператор SQL для получения длины символа.
     */
    public function charLength(string $field, string $operator = null, string $condition = null): string;

    /**
     * Очищает данные из запроса или конкретного предложения запроса.
     *
     * @param string|null $clause  При необходимости можно указать имя очищаемого предложения или ничего, чтобы очистить весь запрос.
     *
     * @return  $this
     */
    public function clear(string $clause = null): static;

    /**
     * Добавляет столбец или массив имен столбцов, которые будут использоваться для инструкции INSERT INTO.
     *
     * @param array|string $columns  Имя столбца или массив имен столбцов.
     *
     * @return  $this
     */
    public function columns(array|string $columns): static;

    /**
     * Объединяет массив имен или значений столбцов.
     *
     * Использование:
     * $query->select($query->concatenate(array('a', 'b')));
     *
     * @param string[]       $values     Массив значений для объединения.
     * @param string|null    $separator  В качестве разделителя между каждым значением.
     *
     * @return  string  Оператор SQL, представляющий объединенные значения.
     */
    public function concatenate(array $values, string $separator = null): string;

    /**
     * Получает текущую дату и время.
     *
     * Использование:
     * $query->where('published_up < '.$query->currentTimestamp());
     *
     * @return  string  Оператор SQL для получения текущей отметки времени.
     */
    public function currentTimestamp(): string;

    /**
     * Добавляет имя таблицы в предложение DELETE запроса.
     *
     * Использование:
     * $query->delete('#__a')->where('id = 1');
     *
     * @param string|null $table  Имя таблицы, из которой требуется удалить.
     *
     * @return  $this
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function delete(string $table = null): static;

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
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function exec(array|string $columns): static;

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
    public function findInSet(string $value, string $set): string;

    /**
     * Добавляет таблицу в предложение FROM запроса.
     *
     * Использование:
     * $query->select('*')->from('#__a');
     * $query->select('*')->from($subquery->alias('a'));
     *
     * @param string|QueryInterface $table  Имя таблицы или объекта QueryInterface (или его дочернего элемента) с установленным псевдонимом.
     *
     * @return  $this
     */
    public function from(QueryInterface|string $table): static;

    /**
     * Добавляет псевдоним для текущего запроса.
     *
     * Использование:
     * $query->select('*')->from('#__a')->alias('subquery');
     *
     * @param string $alias  Псевдоним, используемый для DatabaseQuery.
     *
     * @return  $this
     */
    public function alias(string $alias): static;

    /**
     * Используется для получения строки для извлечения года из столбца даты.
     *
     * Использование:
     * $query->select($query->year($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий год, который необходимо извлечь.
     *
     * @return  string  Оператор SQL для получения года по значению даты.
     */
    public function year(string $date): string;

    /**
     * Используется для получения строки для извлечения месяца из столбца даты.
     *
     * Использование:
     * $query->select($query->month($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемый месяц.
     *
     * @return  string  Оператор SQL для получения месяца из значения даты.
     */
    public function month(string $date): string;

    /**
     * Используется для получения строки для извлечения дня из столбца даты.
     *
     * Использование:
     * $query->select($query->day($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий день, который необходимо извлечь.
     *
     * @return  string  Оператор SQL для получения дня из значения даты.
     */
    public function day(string $date): string;

    /**
     * Используется для получения строки для извлечения часа из столбца даты.
     *
     * Использование:
     * $query->select($query->hour($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемый час.
     *
     * @return  string  Оператор SQL для получения часа из значения даты/времени.
     */
    public function hour(string $date): string;

    /**
     * Используется для получения строки для извлечения минут из столбца даты.
     *
     * Использование:
     * $query->select($query->minute($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий извлекаемые минуты.
     *
     * @return  string  Оператор SQL для получения минут из значения даты/времени.
     */
    public function minute(string $date): string;

    /**
     * Используется для получения строки для извлечения секунд из столбца даты.
     *
     * Использование:
     * $query->select($query->second($query->quoteName('dateColumn')));
     *
     * @param string $date  Столбец даты, содержащий секунду, которую нужно извлечь.
     *
     * @return  string  Оператор SQL для получения второго значения даты/времени.
     */
    public function second(string $date): string;

    /**
     * Добавляет столбец группировки в предложение GROUP запроса.
     *
     * Использование:
     * $query->group('id');
     *
     * @param array|string $columns  Строка или массив столбцов упорядочения.
     *
     * @return  $this
     */
    public function group(array|string $columns): static;

    /**
     * Агрегатная функция для получения входных значений, объединенных в строку, разделенную разделителем.
     *
     * Использование:
     * $query->groupConcat('id', ',');
     *
     * @param string $expression  Выражение, к которому применяется объединение.
     *                            Это может быть имя столбца или сложный оператор SQL.
     * @param string $separator   Разделитель каждого объединенного значения.
     *
     * @return  string  Входные значения объединены в строку, разделенную разделителем.
     */
    public function groupConcat(string $expression, string $separator = ','): string;

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
     */
    public function having(array|string $conditions, string $glue = 'AND'): static;

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
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function insert(string $table, bool $incrementField = false): static;

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
     */
    public function join(string $type, string $table, string $condition = null): static;

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
     */
    public function length(string $value): string;

    /**
     * Возвращает нулевое или нулевое представление временной метки для драйвера базы данных.
     *
     * Этот метод предназначен для использования, когда объект запроса передается функции для модификации.
     * Если у вас есть прямой доступ к объекту базы данных, рекомендуется напрямую использовать метод nullDate.
     *
     * Использование:
     * $query->where('modified_date <> '.$query->nullDate());
     *
     * @param boolean $quoted  При необходимости нулевую дату помещает в кавычки базы данных (по умолчанию true).
     *
     * @return  string  Нулевое или нулевое представление метки времени.
     * @throws  \RuntimeException
     */
    public function nullDate(bool $quoted = true): string;

    /**
     * Создаёт оператор SQL, чтобы проверить, представляет ли столбец нулевое или нулевое значение даты и времени.
     *
     * Использование:
     * $query->where($query->isNullDatetime('modified_date'));
     *
     * @param string $column  Имя столбца.
     *
     * @return  string
     */
    public function isNullDatetime(string $column): string;

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
     */
    public function order(array|string $columns): static;

    /**
     * Заключает имя идентификатора оператора SQL, такое, как имена столбцов, таблиц или баз данных, в кавычки,
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
     *                                 если значение, равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     *
     * @throws  \RuntimeException если внутреннее свойство db не является допустимым объектом.
     */
    public function quoteName(array|string $name, array|string $as = null): array|string;

    /**
     * Возвращает функцию, возвращающую случайное значение с плавающей запятой.
     *
     * Использование:
     * $query->rand();
     *
     * @return  string
     */
    public function rand(): string;

    /**
     * Возвращает оператор регулярного выражения
     *
     * Использование:
     * $query->where('field ' . $query->regexp($search));
     *
     * @param string $value  Шаблон регулярного выражения.
     *
     * @return  string
     */
    public function regexp(string $value): string;

    /**
     * Добавляет один столбец или массив столбцов в предложение SELECT запроса.
     *
     * Использование:
     * $query->select('a.*')->select('b.id');
     * $query->select(array('a.*', 'b.id'));
     *
     * @param array|string $columns  Строка или массив имен полей.
     *
     * @return  $this
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function select(array|string $columns): static;

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
     * @throws  \RuntimeException
     */
    public function selectRowNumber(string $orderBy, string $orderColumnAlias): static;

    /**
     * Добавляет одну строку условия или массив строк в предложение SET запроса.
     *
     * Использование:
     * $query->set('a = 1')->set('b = 2');
     * $query->set(array('a = 1', 'b = 2');
     *
     * @param array|string $conditions  Строка или массив строковых условий.
     * @param string       $glue        Связующий элемент, с помощью которого можно соединить строки условия. По умолчанию `,`.
     *                                  Пожалуйста, обратите внимание, что объединение фиксируется при первом использовании и не может быть изменено.
     *
     * @return  $this
     */
    public function set(array|string $conditions, string $glue = ','): static;

    /**
     * Добавляет имя таблицы в предложение UPDATE запроса.
     *
     * Использование:
     * $query->update('#__foo')->set(...);
     *
     * @param string $table  Таблица для обновления.
     *
     * @return  $this
     * @throws  QueryTypeAlreadyDefinedException если тип запроса уже определен
     */
    public function update(string $table): static;

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
     */
    public function values(array|string $values): static;

    /**
     * Добавляет одно условие или массив условий в предложение WHERE запроса.
     *
     * Использование:
     * $query->where('a = 1')->where('b = 2');
     * $query->where(array('a = 1', 'b = 2'));
     *
     * @param array|string $conditions  Строка или массив условий.
     * @param string       $glue        Объединение, с помощью которого можно соединить условия. По умолчанию используется AND.
     *                                  Обратите внимание, что объединение фиксируется при первом использовании и не может быть изменено.
     *
     * @return  $this
     */
    public function where(array|string $conditions, string $glue = 'AND'): static;

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
    public function whereIn(string $keyName, array $keyValues, array|string $dataType = ParameterType::INTEGER): static;

    /**
     * Добавляет в запрос оператор WHERE NOT IN.
     *
     * Обратите внимание, что все значения должны быть одного типа данных.
     *
     * Использование:
     * $query->whereNotIn('id', [1, 2, 3]);
     *
     * @param   string        $keyName    Ключевое имя для предложения where
     * @param   array         $keyValues  Массив значений для сопоставления
     * @param array|string    $dataType   Константа, соответствующая типу данных SQL.
     *                                    Это может быть массив, в этом случае он должен иметь ту же длину, что и $keyValues.
     *
     * @return  $this
     */
    public function whereNotIn(string $keyName, array $keyValues, array|string $dataType = ParameterType::INTEGER): static;

    /**
     * Расширяет предложение WHERE одним условием или массивом условий,
     * используя потенциально другой логический оператор,
     * отличный от оператора в текущем предложении WHERE.
     *
     * Использование:
     * $query->where(array('a = 1', 'b = 2'))->extendWhere('XOR', array('c = 3', 'd = 4'));
     * будет производить: WHERE ((a = 1 AND b = 2) XOR (c = 3 AND d = 4)
     *
     * @param string    $outerGlue   Связующее звено, с помощью которого можно соединить условия с текущими условиями WHERE.
     * @param   mixed   $conditions  Строка или массив условий WHERE.
     * @param string    $innerGlue   Объединение, с помощью которого можно соединить условия. По умолчанию используется AND.
     *
     * @return  $this
     */
    public function extendWhere(string $outerGlue, mixed $conditions, string $innerGlue = 'AND'): static;

    /**
     * Связывает массив значений и возвращает массив имен подготовленных параметров.
     *
     * Обратите внимание, что все значения должны быть одного типа данных.
     *
     * Использование:
     * $query->whereIn('column in (' . implode(',', $query->bindArray($keyValues, $dataType)) . ')');
     *
     * @param   array         $values    Значение для привязки.
     * @param array|string    $dataType  Константа, соответствующая типу данных SQL.
     *                                   Это может быть массив, в этом случае он должен иметь ту же длину, что и $key.
     *
     * @return  array   Массив с именами параметров.
     */
    public function bindArray(array $values, array|string $dataType = ParameterType::INTEGER): array;

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
    public function union(DatabaseQuery|string $query, bool $distinct = true): static;

    /**
     * Добавляет запрос в UNION ALL с текущим запросом.
     *
     * Использование:
     * $query->unionAll('SELECT name FROM  #__foo')
     *
     * @param string|DatabaseQuery $query  Объект DatabaseQuery или строка для объединения.
     *
     * @return  $this
     *
     * @see     union
     */
    public function unionAll(DatabaseQuery|string $query): static;

    /**
     * Устанавливает один запрос в набор запросов.
     * В этом типе DatabaseQuery вы можете использовать Union(), UnionAll(), order() и setLimit().
     *
     * Использование:
     * @example $query->querySet($query2->select('name')->from('#__foo')->order('id DESC')->setLimit(1))
     *       ->unionAll($query3->select('name')->from('#__foo')->order('id')->setLimit(1))
     *       ->order('name')
     *       ->setLimit(1)
     *
     * @param string|DatabaseQuery $query  Объект или строка DatabaseQuery.
     *
     * @return  $this
     */
    public function querySet(DatabaseQuery|string $query): static;

    /**
     * Создаёт объект DatabaseQuery типа querySet из текущего запроса.
     *
     * Использование:
     * @example $query->select('name')->from('#__foo')->order('id DESC')->setLimit(1)
     *       ->toQuerySet()
     *       ->unionAll($query2->select('name')->from('#__foo')->order('id')->setLimit(1))
     *       ->order('name')
     *       ->setLimit(1)
     *
     * @return  DatabaseQuery Новый объект DatabaseQuery.
     */
    public function toQuerySet(): DatabaseQuery;
}
