<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use RuntimeException;

/**
 * Интерфейс базы данных Flexis Framework
 */
interface DatabaseInterface {
    /**
     * При необходимости подключается к базе данных.
     *
     * @return  void
     * @throws  RuntimeException
     */
    public function connect(): void;

    /**
     * Определяет, активно ли соединение с сервером.
     *
     * @return  boolean
     */
    public function connected(): bool;

    /**
     * Создаёт новую базу данных, используя информацию из объекта $options.
     *
     * @param \stdClass $options  Объект, используемый для передачи имени пользователя и базы данных драйверу базы данных. Для этого объекта должны быть установлены «db_name» и «db_user».
     * @param boolean   $utf      True, если база данных поддерживает набор символов UTF-8.
     *
     * @return  boolean|resource
     * @throws  RuntimeException
     */
    public function createDatabase(\stdClass $options, bool $utf = true);

    /**
     * Заменяет специальный заполнитель, представляющий двоичное поле, исходной строкой.
     *
     * @param string $data  Закодированная строка или ресурс.
     *
     * @return  string  Исходная строка.
     */
    public function decodeBinary(string $data): string;

    /**
     * Отключает базу данных.
     *
     * @return  void
     */
    public function disconnect(): void;

    /**
     * Удаляет таблицу из базы данных.
     *
     * @param string  $table     Имя таблицы базы данных, которую необходимо удалить.
     * @param boolean $ifExists  При необходимости укажите, что таблица должна существовать до ее удаления.
     *
     * @return  $this
     * @throws  RuntimeException
     */
    public function dropTable(string $table, bool $ifExists = true): static;

    /**
     * Экранирует строку для использования в инструкции SQL.
     *
     * @param mixed   $text   Строка, которую нужно экранировать.
     * @param boolean $extra  Необязательный параметр для обеспечения дополнительного экранирования.
     *
     * @return  string   Экранированная строка.
     */
    public function escape(mixed $text, bool $extra = false): string;

    /**
     * Выполняет оператор SQL.
     *
     * @return  boolean
     * @throws  RuntimeException
     */
    public function execute(): bool;

    /**
     * Возвращает количество затронутых строк для предыдущего выполненного оператора SQL.
     *
     * @return  integer
     */
    public function getAffectedRows(): int;

    /**
     * Метод получения используемых параметров сортировки базы данных путем выборки текстового поля таблицы в базе данных.
     *
     * @return  string|boolean  Параметры сортировки, используемые базой данных, или логическое значение false, если не поддерживается.
     */
    public function getCollation(): bool|string;

    /**
     * Метод, обеспечивающий доступ к базовому соединению с базой данных.
     *
     * @return  resource  Базовый ресурс подключения к базе данных.
     */
    public function getConnection();

    /**
     * Метод для получения параметров подключения к базе данных, о которых сообщает драйвер.
     *
     * Если соединитель не поддерживает сообщение об этом значении, верните пустую строку.
     *
     * @return  string|boolean
     */
    public function getConnectionCollation(): string|bool;

    /**
     * Метод для получения сведений об используемом шифровании базы данных (шифр и протокол).
     *
     * @return  string  Подробности шифрования базы данных.
     */
    public function getConnectionEncryption(): string;

    /**
     * Метод проверки того, поддерживается ли шифрование TLS-соединений базы данных.
     *
     * @return  boolean  Поддерживает ли база данных шифрование соединений TLS.
     */
    public function isConnectionEncryptionSupported(): bool;

    /**
     * Метод проверки того, поддерживается ли установленная версия базы данных драйвером базы данных.
     *
     * @return  boolean  True, если версия базы данных поддерживается.
     */
    public function isMinimumVersion(): bool;

    /**
     * Возвращает общее количество операторов SQL, выполненных драйвером базы данных.
     *
     * @return  integer
     */
    public function getCount(): int;

    /**
     * Возвращает формат даты, совместимый с функцией PHP date(), для драйвера базы данных.
     *
     * @return  string
     */
    public function getDateFormat(): string;

    /**
     * Возвращает минимальную поддерживаемую версию базы данных.
     *
     * @return  string
     */
    public function getMinimum(): string;

    /**
     * Возвращает имя драйвера базы данных.
     *
     * @return  string
     */
    public function getName(): string;

    /**
     * Возвращает нулевое или нулевое представление временной метки для драйвера базы данных.
     *
     * @return  string
     */
    public function getNullDate(): string;

    /**
     * Возвращает общий префикс таблицы для драйвера базы данных.
     *
     * @return  string  Префикс общей таблицы базы данных.
     */
    public function getPrefix(): string;

    /**
     * Возвращает количество возвращенных строк для предыдущего выполненного оператора SQL.
     *
     * @return  integer
     */
    public function getNumRows(): int;

    /**
     * Возвращает текущий объект запроса или новый объект QueryInterface.
     *
     * @return  QueryInterface
     * @throws  RuntimeException
     */
    public function getQuery(): QueryInterface;

    /**
     * Возвращает тип семейства серверов.
     *
     * @return  string
     */
    public function getServerType(): string;

    /**
     * Извлекает информацию о полях данных таблиц.
     *
     * @param string  $table     Имя таблицы базы данных.
     * @param boolean $typeOnly  True (по умолчанию), чтобы возвращать только типы полей.
     *
     * @return  array
     * @throws  RuntimeException
     */
    public function getTableColumns(string $table, bool $typeOnly = true): array;

    /**
     * Извлекает информацию о полях данных таблиц.
     *
     * @param   mixed  $tables  Имя таблицы или список имен таблиц.
     *
     * @return  array
     * @throws  RuntimeException
     */
    public function getTableKeys(mixed $tables): array;

    /**
     * Метод получения массива всех таблиц в базе данных.
     *
     * @return  array
     * @throws  RuntimeException
     */
    public function getTableList(): array;

    /**
     * Возвращает версию соединителя базы данных.
     *
     * @return  string
     */
    public function getVersion(): string;

    /**
     * Определяет, поддерживает ли ядро базы данных кодировку символов UTF-8.
     *
     * @return  boolean  True, если ядро базы данных поддерживает кодировку символов UTF-8.
     */
    public function hasUtfSupport(): bool;

    /**
     * Метод для получения автоматически увеличивающегося значения из последнего оператора INSERT.
     *
     * @return  mixed  Значение поля автоинкремента из последней вставленной строки.
     */
    public function insertid(): mixed;

    /**
     * Вставляет строку в таблицу на основе свойств объекта.
     *
     * @param string      $table   Имя таблицы базы данных, в которую требуется вставить.
     * @param object      $object  Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param string|null $key     Имя первичного ключа. Если указано, свойство объекта обновляется.
     *
     * @return  boolean
     * @throws  RuntimeException
     */
    public function insertObject(string $table, object &$object, string $key = null): bool;

    /**
     * Проверяет, доступен ли разъем.
     *
     * @return  boolean
     *
     */
    public static function isSupported(): bool;

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде ассоциативного массива ['field_name' => 'row_value'].
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadAssoc(): mixed;

    /**
     * Метод для получения массива строк набора результатов из запроса к базе данных,
     * где каждая строка представляет собой ассоциативный массив ['field_name' => 'row_value'].
     * Массив строк может быть дополнительно связан с именем поля, но по умолчанию это последовательный числовой массив.
     *
     * ПРИМЕЧАНИЕ. Выбор ключа массива результатов с помощью неуникального имени поля
     * может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string|null $key     Имя поля, в котором необходимо ввести массив результатов.
     * @param string|null $column  Необязательное имя столбца. Вместо всей строки в массиве результатов будет только значение этого столбца.
     *
     * @return  array
     * @throws  RuntimeException
     */
    public function loadAssocList(string $key = null, string $column = null): array;

    /**
     * Метод для получения массива значений из поля <var>$offset</var>
     * в каждой строке набора результатов запроса к базе данных.
     *
     * @param integer $offset  Смещение строки, которое будет использоваться для построения массива результатов.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadColumn(int $offset = 0): mixed;

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде объекта.
     *
     * @param string $class  Имя класса, которое будет использоваться для возвращаемого объекта строки.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadObject(string $class = \stdClass::class): mixed;

    /**
     * Метод для получения массива строк результирующего набора из запроса к базе данных, где каждая строка является объектом.
     * Массив объектов может быть дополнительно связан с именем поля, но по умолчанию это последовательный числовой массив.
     *
     * ПРИМЕЧАНИЕ. Выбор ключа массива результатов с помощью неуникального имени поля может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string $key    Имя поля, в котором необходимо ввести массив результатов.
     * @param string $class  Имя класса, которое будет использоваться для возвращаемых объектов строк.
     *
     * @return  array|null  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadObjectList(string $key = '', string $class = \stdClass::class): array|null;

    /**
     * Метод для получения первого поля первой строки набора результатов из запроса к базе данных.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadResult(): mixed;

    /**
     * Метод для получения первой строки набора результатов из запроса к базе данных в виде массива.
     *
     * Столбцы индексируются численно, поэтому первый столбец в наборе результатов будет доступен через <var>$row[0]</var> и т.д.
     *
     * @return  mixed  Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadRow(): mixed;

    /**
     * Метод для получения массива строк результирующего набора из запроса к базе данных, где каждая строка является массивом.
     * Массив объектов может быть дополнительно связан с помощью смещения поля, но по умолчанию используется последовательный числовой массив.
     *
     * ПРИМЕЧАНИЕ. Выбор ключа массива результатов с помощью неуникального поля может привести к нежелательному поведению, и этого следует избегать.
     *
     * @param string|null $key  Имя поля, в котором необходимо ввести массив результатов.
     *
     * @return  array|null   Возвращаемое значение или значение NULL, если запрос не выполнен.
     * @throws  RuntimeException
     */
    public function loadRowList(string $key = null): array|null;

    /**
     * Блокирует таблицу в базе данных.
     *
     * @param string $tableName  Имя таблицы, которую нужно блокировать.
     *
     * @return  $this
     * @throws  RuntimeException
     */
    public function lockTable(string $tableName): static;

    /**
     * Заключает в кавычки и, при необходимости, экранирует строку, соответствующую требованиям базы данных для использования в запросах к базе данных.
     *
     * @param array|string    $text    Строка или массив строк для цитирования.
     * @param boolean         $escape  True (по умолчанию), чтобы экранировать строку, false, чтобы оставить ее без изменений.
     *
     * @return  array|string
     */
    public function quote(array|string $text, bool $escape = true): array|string;

    /**
     * Заключает в кавычки двоичную строку, соответствующую требованиям к базе данных, для использования в запросах к базе данных.
     *
     * @param string $data  Двоичная строка для цитирования.
     *
     * @return  string  Входная строка в двоичных кавычках.
     */
    public function quoteBinary(string $data): string;

    /**
     * Заключает имя идентификатора оператора SQL, такое, как имена столбцов,
     * таблиц или баз данных, в кавычки, чтобы предотвратить риски внедрения
     * и конфликты зарезервированных слов.
     *
     * @param array|string      $name  Имя идентификатора, заключаемое в кавычки, или массив имен идентификаторов, заключаемый в кавычки.
     *                                 Каждый тип поддерживает имя в виде точечной записи.
     * @param array|string|null $as    Часть запроса AS, связанная с $name.
     *                                 Это может быть строка или массив, в последнем случае длина должна быть такой же, как $name;
     *                                 если значение, равно нулю, для строки или элемента массива не будет никакой части AS.
     *
     * @return  array|string  Имя в кавычках, того же типа, что и $name.
     */
    public function quoteName(array|string $name, array|string $as = null): array|string;

    /**
     * Переименовывает таблицу в базе данных.
     *
     * @param string      $oldTable  Имя таблицы, которую нужно переименовать.
     * @param string      $newTable  Новое имя таблицы.
     * @param string|null $backup    Префикс таблицы.
     * @param string|null $prefix    Для таблицы — используется для переименования ограничений в базах данных, отличных от MySQL.
     *
     * @return  $this
     * @throws  RuntimeException
     */
    public function renameTable(string $oldTable, string $newTable, string $backup = null, string $prefix = null): static;

    /**
     * Эта функция заменяет строковый идентификатор настроенным префиксом таблицы.
     *
     * @param string $sql     Оператор SQL, который нужно подготовить.
     * @param string $prefix  Префикс таблицы.
     *
     * @return  string  Обработанный оператор SQL.
     */
    public function replacePrefix(string $sql, string $prefix = '#__'): string;

    /**
     * Выбирает базу данных для использования.
     *
     * @param string $database  Имя базы данных, которую необходимо выбрать для использования.
     *
     * @return  boolean
     * @throws  RuntimeException
     */
    public function select(string $database): bool;

    /**
     * Устанавливает строку инструкции SQL для последующего выполнения.
     *
     * @param   mixed $query   Оператор SQL, который необходимо задать либо как объект Query, либо как строку.
     *
     * @return  $this
     */
    public function setQuery(mixed $query): static;

    /**
     * Метод фиксации транзакции.
     *
     * @param boolean $toSavepoint  Если true, сохранить последнюю точку сохранения.
     *
     * @return  void
     * @throws  RuntimeException
     */
    public function transactionCommit(bool $toSavepoint = false): void;

    /**
     * Метод для отката транзакции.
     *
     * @param boolean $toSavepoint  Если true, откат к последней точке сохранения.
     *
     * @return  void
     * @throws  RuntimeException
     */
    public function transactionRollback(bool $toSavepoint = false): void;

    /**
     * Метод инициализации транзакции.
     *
     * @param boolean $asSavepoint  Если true и транзакция уже активна, будет создана точка сохранения.
     *
     * @return  void
     * @throws  RuntimeException
     */
    public function transactionStart(bool $asSavepoint = false): void;

    /**
     * Метод удаления всех записей из таблицы.
     *
     * @param string $table  Таблица, которую нужно очистить
     *
     * @return  void
     * @throws  RuntimeException
     */
    public function truncateTable(string $table): void;

    /**
     * Разблокирует таблицы в базе данных.
     *
     * @return  $this
     * @throws  RuntimeException
     */
    public function unlockTables(): static;

    /**
     * Обновляет строку в таблице на основе свойств объекта.
     *
     * @param string              $table   Имя таблицы базы данных, которую необходимо обновить.
     * @param object              $object  Ссылка на объект, общедоступные свойства которого соответствуют полям таблицы.
     * @param array|string|object $key     Имя первичного ключа.
     * @param boolean             $nulls   Значение true для обновления пустых полей или значение false для их игнорирования.
     *
     * @return  boolean
     * @throws  RuntimeException
     */
    public function updateObject(string $table, object &$object, array|string|object $key, bool $nulls = false): bool;
}
