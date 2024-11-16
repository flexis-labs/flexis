<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Интерфейс, определяющий оператор запроса.
 *
 * Этот интерфейс является частичной автономной реализацией PDOStatement.
 */
interface StatementInterface {
    /**
     * Привязывает параметр к указанному имени переменной.
     *
     * @param integer|string    $parameter      Идентификатор параметра.
     *                                          Для подготовленного оператора, использующего именованные заполнители,
     *                                          это будет имя параметра в форме `:name`.
     *                                          Для подготовленного оператора с использованием заполнителей вопросительных знаков
     *                                          это будет позиция параметра с индексом 1.
     * @param   mixed           $variable       Имя переменной PHP для привязки к параметру оператора SQL.
     * @param   string          $dataType       Константа, соответствующая типу данных SQL. Это должен быть обработанный тип из QueryInterface.
     * @param   ?integer        $length         Длина переменной. Обычно требуется для выходных параметров.
     * @param   ?array          $driverOptions  Дополнительные параметры драйвера, которые будут использоваться.
     *
     * @return  boolean
     */
    public function bindParam(
        int|string $parameter,
        mixed &$variable,
        string $dataType = ParameterType::STRING,
        ?int $length = null,
        ?array $driverOptions = null
    ): bool;

    /**
     * Закрывает курсор, позволяя выполнить оператор еще раз.
     *
     * @return  void
     */
    public function closeCursor(): void;

    /**
     * Извлекает SQLSTATE, связанный с последней операцией в дескрипторе инструкции.
     *
     * @return  int
     */
    public function errorCode(): int;

    /**
     * Извлекает расширенную информацию об ошибке, связанную с последней операцией над дескриптором инструкции.
     *
     * @return  string|array
     */
    public function errorInfo(): string|array;

    /**
     * Выполняет подготовленный оператор
     *
     * @param   array|null  $parameters  Массив значений, содержащий столько элементов, сколько связанных параметров в выполняемом операторе SQL.
     *
     * @return  boolean
     */
    public function execute(?array $parameters = null): bool;

    /**
     * Извлекает следующую строку из набора результатов.
     *
     * @param   integer|null  $fetchStyle         Управляет тем, как следующая строка будет возвращена вызывающему объекту.
     *                                            Это значение должно быть одной из констант FetchMode, по умолчанию равной значению FetchMode::MIXED.
     * @param   integer       $cursorOrientation  Для объекта StatementInterface, представляющего прокручиваемый курсор, это значение определяет,
     *                                            какая строка будет возвращена вызывающему объекту.
     *                                            Это значение должно быть одной из констант FetchOrientation, по умолчанию равной FetchOrientation::NEXT.
     * @param   integer       $cursorOffset       Для объекта StatementInterface, представляющего прокручиваемый курсор,
     *                                            для которого параметру курсорориентации присвоено значение FetchOrientation::ABS,
     *                                            это значение указывает абсолютный номер строки в наборе результатов, которая должна быть выбрана.
     *                                            Для объекта StatementInterface, представляющего прокручиваемый курсор,
     *                                            для которого параметру курсорориентации присвоено значение FetchOrientation::REL,
     *                                            это значение указывает строку, которую нужно извлечь относительно позиции курсора до вызова fetch().
     *
     * @return  mixed  Возвращаемое значение этой функции в случае успеха зависит от типа выборки. Во всех случаях в случае неудачи возвращается логическое значение false.
     */
    public function fetch(
        ?int $fetchStyle = null,
        int $cursorOrientation = FetchOrientation::NEXT,
        int $cursorOffset = 0
    ): mixed;

    /**
     * Возвращает количество строк, на которые повлиял последний оператор SQL.
     *
     * @return  integer
     */
    public function rowCount(): int;

    /**
     * Устанавливает режим выборки, который будет использоваться при итерации этого оператора.
     *
     * @param   integer  $fetchMode  Режим выборки должен быть одной из констант FetchMode.
     * @param   mixed    ...$args    Необязательные аргументы, специфичные для режима.
     *
     * @return  void
     */
    public function setFetchMode(int $fetchMode, ...$args): void;
}
