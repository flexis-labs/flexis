<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pdo;

use Flexis\Database\FetchMode;
use Flexis\Database\FetchOrientation;
use Flexis\Database\ParameterType;
use Flexis\Database\StatementInterface;

/**
 * Заявление о базе данных PDO.
 */
class PdoStatement implements StatementInterface {
    /**
     * Массив сопоставления для режимов выборки.
     *
     * @var    array
     */
    private const array FETCH_MODE_MAP = [
        FetchMode::ASSOCIATIVE     => \PDO::FETCH_ASSOC,
        FetchMode::NUMERIC         => \PDO::FETCH_NUM,
        FetchMode::MIXED           => \PDO::FETCH_BOTH,
        FetchMode::STANDARD_OBJECT => \PDO::FETCH_OBJ,
        FetchMode::COLUMN          => \PDO::FETCH_COLUMN,
        FetchMode::CUSTOM_OBJECT   => \PDO::FETCH_CLASS,
    ];

    /**
     * Массив сопоставления типов параметров.
     *
     * @var    array
     */
    private const array PARAMETER_TYPE_MAP = [
        ParameterType::BOOLEAN      => \PDO::PARAM_BOOL,
        ParameterType::INTEGER      => \PDO::PARAM_INT,
        ParameterType::LARGE_OBJECT => \PDO::PARAM_LOB,
        ParameterType::NULL         => \PDO::PARAM_NULL,
        ParameterType::STRING       => \PDO::PARAM_STR,
    ];

    /**
     * Декорированный объект PDOStatement.
     *
     * @var    \PDOStatement
     */
    protected \PDOStatement $pdoStatement;

    /**
     * Конструктор операторов.
     *
     * @param   \PDOStatement  $pdoStatement  Декорированный объект PDOStatement.
     */
    public function __construct(\PDOStatement $pdoStatement) {
        $this->pdoStatement = $pdoStatement;
    }

    /**
     * Привязывает параметр к указанному имени переменной.
     *
     * @param   integer|string  $parameter      Идентификатор параметра. Для подготовленного оператора, использующего именованные заполнители, это будет имя параметра в форме `:name`. Для подготовленного оператора с использованием заполнителей вопросительных знаков это будет позиция параметра с индексом 1.
     * @param   mixed           $variable       Имя переменной PHP для привязки к параметру оператора SQL.
     * @param   string          $dataType       Константа, соответствующая типу данных SQL. Это должен быть обработанный тип из QueryInterface.
     * @param   integer|null    $length         Длина переменной. Обычно требуется для выходных параметров.
     * @param   array|null      $driverOptions  Дополнительные параметры драйвера, которые будут использоваться.
     *
     * @return  boolean
     */
    public function bindParam(int|string $parameter, mixed &$variable, string $dataType = ParameterType::STRING, ?int $length = null, ?array $driverOptions = null): bool {
        $type            = $this->convertParameterType($dataType);
        $extraParameters = array_slice(func_get_args(), 3);

        if (count($extraParameters) !== 0) {
            $extraParameters[0] = $extraParameters[0] ?? 0;
        }

        $this->pdoStatement->bindParam($parameter, $variable, $type, ...$extraParameters);

        return true;
    }

    /**
     * Закрывает курсор, позволяя выполнить оператор еще раз.
     *
     * @return  void
     */
    public function closeCursor(): void {
        $this->pdoStatement->closeCursor();
    }

    /**
     * Извлекает SQLSTATE, связанный с последней операцией в дескрипторе инструкции.
     *
     * @return  int
     */
    public function errorCode(): int {
        return (int) $this->pdoStatement->errorCode();
    }

    /**
     * Извлекает расширенную информацию об ошибке, связанную с последней операцией над дескриптором инструкции.
     *
     * @return  string|array
     */
    public function errorInfo(): string|array {
		return $this->pdoStatement->errorInfo();
    }

    /**
     * Выполняет подготовленный оператор
     *
     * @param   array|null  $parameters  Массив значений, содержащий столько элементов, сколько связанных параметров в выполняемом операторе SQL.
     *
     * @return  boolean
     */
    public function execute(?array $parameters = null): bool {
        return $this->pdoStatement->execute($parameters);
    }

    /**
     * Извлекает следующую строку из набора результатов
     *
     * @param   integer|null  $fetchStyle         Управляет тем, как следующая строка будет возвращена вызывающему объекту. Это значение должно быть одной из констант FetchMode, по умолчанию равной значению FetchMode::MIXED.
     * @param   integer       $cursorOrientation  Для объекта StatementInterface, представляющего прокручиваемый курсор, это значение определяет, какая строка будет возвращена вызывающему объекту. Это значение должно быть одной из констант FetchOrientation, по умолчанию равной FetchOrientation::NEXT.
     * @param   integer       $cursorOffset       Для объекта StatementInterface, представляющего прокручиваемый курсор, для которого параметру курсорориентации присвоено значение FetchOrientation::ABS, это значение указывает абсолютный номер строки в наборе результатов, которая должна быть выбрана. Для объекта StatementInterface, представляющего прокручиваемый курсор, для которого параметру курсорориентации присвоено значение FetchOrientation::REL, это значение указывает строку, которую нужно извлечь относительно позиции курсора до вызова fetch().
     *
     * @return  mixed  Возвращаемое значение этой функции в случае успеха зависит от типа выборки. Во всех случаях в случае неудачи возвращается логическое значение false.
     */
    public function fetch(?int $fetchStyle = null, int $cursorOrientation = FetchOrientation::NEXT, int $cursorOffset = 0): mixed {
        if ($fetchStyle === null) {
            return $this->pdoStatement->fetch();
        }

        return $this->pdoStatement->fetch($this->convertFetchMode($fetchStyle), $cursorOrientation, $cursorOffset);
    }

    /**
     * Возвращает количество строк, на которые повлиял последний оператор SQL.
     *
     * @return  integer
     */
    public function rowCount(): int {
        return $this->pdoStatement->rowCount();
    }

    /**
     * Устанавливает режим выборки, который будет использоваться при итерации этого оператора.
     *
     * @param   integer  $fetchMode  Режим выборки должен быть одной из констант FetchMode.
     * @param   mixed    ...$args    Необязательные аргументы, специфичные для режима.
     *
     * @return  void
     */
    public function setFetchMode(int $fetchMode, ...$args): void {
        $this->pdoStatement->setFetchMode($this->convertFetchMode($fetchMode), ...$args);
    }

    /**
     * Преобразует режим выборки API базы данных в режим выборки PDO.
     *
     * @param   integer  $mode  Режим получения для конвертации.
     *
     * @return  integer
     * @throws  \InvalidArgumentException если режим выборки не поддерживается.
     */
    private function convertFetchMode(int $mode): int {
        if (!isset(self::FETCH_MODE_MAP[$mode])) {
            throw new \InvalidArgumentException(sprintf('Неподдерживаемый режим получения `%s`', $mode));
        }

        return self::FETCH_MODE_MAP[$mode];
    }

    /**
     * Преобразует тип параметра API базы данных в тип параметра PDO.
     *
     * @param   string  $type  Тип параметра для преобразования.
     *
     * @return  integer
     * @throws  \InvalidArgumentException если тип параметра не поддерживается.
     */
    private function convertParameterType(string $type): int {
        if (!isset(self::PARAMETER_TYPE_MAP[$type])) {
            throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип параметра `%s`', $type));
        }

        return self::PARAMETER_TYPE_MAP[$type];
    }
}
