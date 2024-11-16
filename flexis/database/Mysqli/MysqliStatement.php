<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysqli;

use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Database\FetchMode;
use Flexis\Database\FetchOrientation;
use Flexis\Database\ParameterType;
use Flexis\Database\StatementInterface;

/**
 * Заявление о базе данных MySQLi.
 *
 * Этот класс создан по образцу \Doctrine\DBAL\Driver\Mysqli\MysqliStatement
 */
class MysqliStatement implements StatementInterface {
    /**
     * Значения, которые были привязаны к оператору.
     *
     * @var    array|null
     */
    protected ?array $bindedValues;

    /**
     * Сопоставление именованных параметров и позиции в запросе.
     *
     * @var    array
     */
    protected array $parameterKeyMapping;

    /**
     * Массив сопоставления типов параметров.
     *
     * @var    array
     */
    protected array $parameterTypeMapping = [
        ParameterType::BOOLEAN      => 'i',
        ParameterType::INTEGER      => 'i',
        ParameterType::LARGE_OBJECT => 's',
        ParameterType::NULL         => 's',
        ParameterType::STRING       => 's',
    ];

    /**
     * Имена столбцов из выполненного оператора.
     *
     * @var    array|boolean|null
     */
    protected array|bool|null $columnNames;

    /**
     * Ресурс подключения к базе данных.
     *
     * @var    \mysqli
     */
    protected $connection;

    /**
     * Режим выборки по умолчанию для оператора.
     *
     * @var    integer
     */
    protected int $defaultFetchStyle = FetchMode::MIXED;

    /**
     * Строка запроса подготавливается.
     *
     * @var    string
     */
    protected string $query;

    /**
     * Внутренний флаг отслеживания, указывающий, доступен ли набор результатов для обработки.
     *
     * @var    boolean
     */
    private bool $result = false;

    /**
     * Значения, привязанные к строкам каждого набора результатов.
     *
     * @var    array|null
     */
    protected ?array $rowBindedValues;

    /**
     * Подготовленное заявление.
     *
     * @var    \mysqli_stmt
     */
    protected $statement;

    /**
     * Типы связанных параметров.
     *
     * @var    array
     */
    protected array $typesKeyMapping;

    /**
     * Конструктор.
     *
     * @param   \mysqli  $connection  Ресурс подключения к базе данных.
     * @param   string   $query       Запрос, который будет обрабатывать этот оператор.
     *
     * @throws  PrepareStatementFailureException
     */
    public function __construct(\mysqli $connection, string $query) {
        $this->connection   = $connection;
        $this->query        = $query;

        $query = $this->prepareParameterKeyMapping($query);

        $this->statement  = $connection->prepare($query);

        if (!$this->statement) {
            throw new PrepareStatementFailureException($this->connection->error, $this->connection->errno);
        }
    }

    /**
     * Заменить именованные параметры нумерованными параметрами
     *
     * @param   string  $sql  Оператор SQL, который нужно подготовить.
     *
     * @return  string  Обработанный оператор SQL.
     */
    public function prepareParameterKeyMapping(string $sql): string {
        $escaped    = false;
        $startPos   = 0;
        $quoteChar  = '';
        $literal    = '';
        $mapping    = [];
        $position   = 0;
        $matches    = [];
        $pattern    = '/([:][a-zA-Z0-9_]+)/';

        if (!preg_match($pattern, $sql, $matches)) {
            return $sql;
        }

        $sql = trim($sql);
        $n   = \strlen($sql);

        while ($startPos < $n) {
            if (!preg_match($pattern, $sql, $matches, 0, $startPos)) {
                break;
            }

            $j = strpos($sql, "'", $startPos);
            $k = strpos($sql, '"', $startPos);

            if (($k !== false) && (($k < $j) || ($j === false))) {
                $quoteChar = '"';
                $j         = $k;
            } else {
                $quoteChar = "'";
            }

            if ($j === false) {
                $j = $n;
            }

            $substring = substr($sql, $startPos, $j - $startPos);

            if (preg_match_all($pattern, $substring, $matches, PREG_PATTERN_ORDER + PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $i => $match) {
                    if ($i === 0) {
                        $literal .= substr($substring, 0, $match[1]);
                    }

                    if (!isset($mapping[$match[0]])) {
                        $mapping[$match[0]] = [];
                    }

                    $mapping[$match[0]][]   = $position++;
                    $endOfPlaceholder       = $match[1] + strlen($match[0]);
                    $beginOfNextPlaceholder = $matches[0][$i + 1][1] ?? strlen($substring);
                    $beginOfNextPlaceholder -= $endOfPlaceholder;
                    $literal                .= '?' . substr($substring, $endOfPlaceholder, $beginOfNextPlaceholder);
                }
            } else {
                $literal .= $substring;
            }

            $startPos = $j;
            $j++;

            if ($j >= $n) {
                break;
            }

            while (true) {
                $k       = strpos($sql, $quoteChar, $j);
                $escaped = false;

                if ($k === false) {
                    break;
                }

                $l = $k - 1;

                while ($l >= 0 && $sql[$l] === '\\') {
                    $l--;
                    $escaped = !$escaped;
                }

                if ($escaped) {
                    $j = $k + 1;

                    continue;
                }

                break;
            }

            if ($k === false) {
                break;
            }

            $literal .= substr($sql, $startPos, $k - $startPos + 1);
            $startPos = $k + 1;
        }

        if ($startPos < $n) {
            $literal .= substr($sql, $startPos, $n - $startPos);
        }

        $this->parameterKeyMapping = $mapping;

        return $literal;
    }

    /**
     * Привязывает параметр к указанному имени переменной.
     *
     * @param integer|string    $parameter      Идентификатор параметра. Для подготовленного оператора, использующего именованные заполнители, это будет имя параметра в форме `:name`. Для подготовленного оператора с использованием заполнителей вопросительных знаков это будет позиция параметра с индексом 1.
     * @param   mixed           $variable       Имя переменной PHP для привязки к параметру оператора SQL.
     * @param   string          $dataType       Константа, соответствующая типу данных SQL. Это должен быть обработанный тип из QueryInterface.
     * @param   integer|null    $length         Длина переменной. Обычно требуется для выходных параметров.
     * @param   array|null      $driverOptions  Дополнительные параметры драйвера, которые будут использоваться.
     *
     * @return  boolean
     */
    public function bindParam(
        int|string $parameter,
        mixed &$variable,
        string $dataType = ParameterType::STRING,
        ?int $length = null,
        ?array $driverOptions = null
    ): bool {
        $this->bindedValues[$parameter] =& $variable;

        if (!isset($this->parameterTypeMapping[$dataType])) {
            throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип параметра `%s`', $dataType));
        }

        $this->typesKeyMapping[$parameter] = $this->parameterTypeMapping[$dataType];

        return true;
    }

    /**
     * Связывает массив значений с привязанными параметрами.
     *
     * @param   array  $values  Значения для привязки к оператору.
     *
     * @return  boolean
     */
    private function bindValues(array $values): bool {
        $params = [];
        $types  = str_repeat('s', \count($values));

        if (!empty($this->parameterKeyMapping)) {
            foreach ($values as $key => &$value) {
                $params[$this->parameterKeyMapping[$key]] =& $value;
            }

            ksort($params);
        } else {
            foreach ($values as $key => &$value) {
                $params[] =& $value;
            }
        }

        array_unshift($params, $types);

        return \call_user_func_array([$this->statement, 'bind_param'], $params);
    }

    /**
     * Закрывает курсор, позволяя выполнить оператор еще раз.
     *
     * @return  void
     */
    public function closeCursor(): void {
        $this->statement->free_result();
        $this->result = false;
    }

    /**
     * Извлекает SQLSTATE, связанный с последней операцией в дескрипторе инструкции.
     *
     * @return  integer
     */
    public function errorCode(): int {
        return $this->statement->errno;
    }

    /**
     * Извлекает расширенную информацию об ошибке, связанную с последней операцией над дескриптором инструкции.
     *
     * @return  string|array
     */
    public function errorInfo(): string|array {
        return $this->statement->error;
    }

    /**
     * Выполняет подготовленный оператор
     *
     * @param   array|null  $parameters  Массив значений, содержащий столько элементов, сколько связанных параметров в выполняемом операторе SQL.
     *
     * @return  boolean
     */
    public function execute(?array $parameters = null): bool {
        if ($this->bindedValues !== null) {
            $params = [];
            $types  = [];

            if (!empty($this->parameterKeyMapping)) {
                foreach ($this->bindedValues as $key => &$value) {
                    $paramKey = $this->parameterKeyMapping[$key];

                    foreach ($paramKey as $currentKey) {
                        $params[$currentKey] =& $value;
                        $types[$currentKey]  = $this->typesKeyMapping[$key];
                    }
                }
            } else {
                foreach ($this->bindedValues as $key => &$value) {
                    $params[]    =& $value;
                    $types[$key] = $this->typesKeyMapping[$key];
                }
            }

            ksort($params);
            ksort($types);

            array_unshift($params, implode('', $types));

            if (!\call_user_func_array([$this->statement, 'bind_param'], $params)) {
                throw new PrepareStatementFailureException($this->statement->error, $this->statement->errno);
            }
        } elseif ($parameters !== null) {
            if (!$this->bindValues($parameters)) {
                throw new PrepareStatementFailureException($this->statement->error, $this->statement->errno);
            }
        }

        try {
            if (!$this->statement->execute()) {
                throw new ExecutionFailureException($this->query, $this->statement->error, $this->statement->errno);
            }
        } catch (\Throwable $e) {
            throw new ExecutionFailureException($this->query, $e->getMessage(), $e->getCode(), $e);
        }

        if ($this->columnNames === null) {
            $meta = $this->statement->result_metadata();

            if ($meta !== false) {
                $columnNames = [];

                foreach ($meta->fetch_fields() as $col) {
                    $columnNames[] = $col->name;
                }

                $meta->free();

                $this->columnNames = $columnNames;
            } else {
                $this->columnNames = false;
            }
        }

        if ($this->columnNames !== false) {
            $this->statement->store_result();

            $this->rowBindedValues = array_fill(0, \count($this->columnNames), null);
            $refs                  = [];

            foreach ($this->rowBindedValues as $key => &$value) {
                $refs[$key] =& $value;
            }

            if (!\call_user_func_array([$this->statement, 'bind_result'], $refs)) {
                throw new \RuntimeException($this->statement->error, $this->statement->errno);
            }
        }

        $this->result = true;

        return true;
    }

    /**
     * Извлекает следующую строку из набора результатов
     *
     * @param   integer|null  $fetchStyle         Управляет тем, как следующая строка будет возвращена вызывающему объекту. Это значение должно быть одной из констант FetchMode, по умолчанию равной значению FetchMode::MIXED.
     * @param   integer       $cursorOrientation  Для объекта StatementInterface, представляющего прокручиваемый курсор, это значение определяет, какая строка будет возвращена вызывающему объекту. Это значение должно быть одной из констант FetchOrientation, по умолчанию равной FetchOrientation::NEXT.
     * @param   integer       $cursorOffset       Для объекта StatementInterface, представляющего прокручиваемый курсор, для которого параметру курсорориентации присвоено значение FetchOrientation::ABS, это значение указывает абсолютный номер строки в наборе результатов, которая должна быть выбрана. Для объекта StatementInterface, представляющего прокручиваемый курсор, для которого параметру курсорориентации присвоено значение FetchOrientation::REL, это значение указывает строку, которую нужно извлечь относительно позиции курсора до вызова fetch().
     *
     * @return  mixed   Возвращаемое значение этой функции в случае успеха зависит от типа выборки.
     *                  Во всех случаях в случае неудачи возвращается логическое значение false.
     */
    public function fetch(
        ?int $fetchStyle = null,
        int $cursorOrientation = FetchOrientation::NEXT,
        int $cursorOffset = 0
    ): mixed {

        if (!$this->result) {
            return false;
        }

        $fetchStyle = $fetchStyle ?: $this->defaultFetchStyle;

        if ($fetchStyle === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        $values = $this->fetchData();

        if ($values === null) {
            return false;
        }

        if ($values === false) {
            throw new \RuntimeException($this->statement->error, $this->statement->errno);
        }

        switch ($fetchStyle) {
            case FetchMode::NUMERIC:
                return $values;

            case FetchMode::ASSOCIATIVE:
                return array_combine($this->columnNames, $values);

            case FetchMode::MIXED:
                $ret = array_combine($this->columnNames, $values);
                $ret += $values;

                return $ret;

            case FetchMode::STANDARD_OBJECT:
                return (object) array_combine($this->columnNames, $values);

            default:
                throw new \InvalidArgumentException("Неизвестный тип получения '{$fetchStyle}'");
        }
    }

    /**
     * Возвращает один столбец из следующей строки результирующего набора.
     *
     * @param   integer  $columnIndex  Номер столбца с индексом 0, который вы хотите получить из строки.
     *                                 Если значение не указано, извлекается первый столбец.
     *
     * @return  mixed  Возвращает один столбец из следующей строки результирующего набора или логическое значение false, если строк больше нет.
     */
    public function fetchColumn(int $columnIndex = 0): mixed {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * Возвращает данные из заявления.
     *
     * @return  array|boolean
     */
    private function fetchData(): bool|array {
        $return = $this->statement->fetch();

        if ($return === true) {
            $values = [];

            foreach ($this->rowBindedValues as $v) {
                $values[] = $v;
            }

            return $values;
        }

        return $return;
    }

    /**
     * Возвращает количество строк, на которые повлиял последний оператор SQL.
     *
     * @return  integer
     */
    public function rowCount(): int {
        if ($this->columnNames === false) {
            return $this->statement->affected_rows;
        }

        return $this->statement->num_rows;
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
        $this->defaultFetchStyle = $fetchMode;
    }
}
