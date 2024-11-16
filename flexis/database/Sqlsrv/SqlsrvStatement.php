<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Sqlsrv;

use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\Exception\PrepareStatementFailureException;
use Flexis\Database\FetchMode;
use Flexis\Database\FetchOrientation;
use Flexis\Database\ParameterType;
use Flexis\Database\StatementInterface;

/**
 * Положение о базе данных SQL Server.
 *
 * Этот класс создан по образцу \Doctrine\DBAL\Driver\SQLSrv\SQLSrvStatement
 */
class SqlsrvStatement implements StatementInterface {
    /**
     * Ресурс подключения к базе данных.
     *
     * @var    resource
     */
    protected $connection;

    /**
     * Режим выборки по умолчанию для оператора.
     *
     * @var    integer
     */
    protected int $defaultFetchStyle = FetchMode::MIXED;

    /**
     * Класс по умолчанию, используемый для построения наборов результатов объектов.
     *
     * @var    class-string
     */
    protected string $defaultObjectClass = \stdClass::class;

    /**
     * Сопоставление массива, преобразующего режимы выборки в собственный тип механизма.
     *
     * @var    array
     */
    private array $fetchMap = [
        FetchMode::MIXED       => SQLSRV_FETCH_BOTH,
        FetchMode::ASSOCIATIVE => SQLSRV_FETCH_ASSOC,
        FetchMode::NUMERIC     => SQLSRV_FETCH_NUMERIC,
    ];

    /**
     * Строка запроса подготавливается.
     *
     * @var    string|null
     */
    protected ?string $query;

    /**
     * Внутренний флаг отслеживания, указывающий, доступен ли набор результатов для обработки.
     *
     * @var    boolean
     */
    private bool $result = false;

    /**
     * Подготовленное заявление.
     *
     * @var    resource
     */
    protected $statement;

    /**
     * Типы связанных параметров.
     *
     * @var    array|null
     */
    protected ?array $typesKeyMapping;

    /**
     * Ссылки на переменные, связанные как параметры оператора.
     *
     * @var    array
     */
    private array $bindedValues = [];

    /**
     * Сопоставление именованных параметров и позиции в запросе.
     *
     * @var    array|null
     */
    protected ?array $parameterKeyMapping;

    /**
     * Массив сопоставления типов параметров.
     *
     * @var    array
     */
    protected array $parameterTypeMapping = [
        ParameterType::BOOLEAN      => ParameterType::BOOLEAN,
        ParameterType::INTEGER      => ParameterType::INTEGER,
        ParameterType::LARGE_OBJECT => ParameterType::LARGE_OBJECT,
        ParameterType::NULL         => ParameterType::NULL,
        ParameterType::STRING       => ParameterType::STRING,
    ];

    /**
     * Конструктор.
     *
     * @param   resource  $connection  Ресурс подключения к базе данных
     * @param   string    $query       Запрос, который будет обрабатывать этот оператор
     * @throws  PrepareStatementFailureException
     */
    public function __construct($connection, string $query) {
        // Initial parameter types for prepared statements
        $this->parameterTypeMapping = [
            ParameterType::BOOLEAN      => SQLSRV_PHPTYPE_INT,
            ParameterType::INTEGER      => SQLSRV_PHPTYPE_INT,
            ParameterType::LARGE_OBJECT => SQLSRV_PHPTYPE_STREAM(SQLSRV_ENC_BINARY),
            ParameterType::NULL         => SQLSRV_PHPTYPE_NULL,
            ParameterType::STRING       => SQLSRV_PHPTYPE_STRING(SQLSRV_ENC_CHAR),
        ];

        $this->connection = $connection;
        $this->query      = $this->prepareParameterKeyMapping($query);
    }

    /**
     * Заменить именованные параметры нумерованными параметрами
     *
     * @param   string  $sql  Оператор SQL, который нужно подготовить.
     *
     * @return  string  Обработанный оператор SQL.
     */
    public function prepareParameterKeyMapping($sql): string {
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
     * @param   integer|string  $parameter       Идентификатор параметра. Для подготовленного оператора, использующего именованные заполнители, это будет имя параметра в форме `:name`. Для подготовленного оператора с использованием заполнителей вопросительных знаков это будет позиция параметра с индексом 1.
     * @param   mixed           $variable        Имя переменной PHP для привязки к параметру оператора SQL.
     * @param   string          $dataType        Константа, соответствующая типу данных SQL. Это должен быть обработанный тип из QueryInterface.
     * @param   ?integer        $length          Длина переменной. Обычно требуется для выходных параметров.
     * @param   ?array          $driverOptions   Дополнительные параметры драйвера, которые будут использоваться.
     *
     * @return  boolean
     */
    public function bindParam(int|string $parameter, mixed &$variable, string $dataType = ParameterType::STRING,
                              ?int $length = null, ?array $driverOptions = null): bool {

        $this->bindedValues[$parameter] =& $variable;

        if (!isset($this->parameterTypeMapping[$dataType])) {
            throw new \InvalidArgumentException(sprintf('Неподдерживаемый тип параметра `%s`', $dataType));
        }

        $this->typesKeyMapping[$parameter] = $this->parameterTypeMapping[$dataType];

        $this->statement = null;

        return true;
    }

    /**
     * Привязывает значение к указанной переменной.
     *
     * @param   integer|string  $parameter       Идентификатор параметра. Для подготовленного оператора, использующего именованные заполнители, это будет имя параметра в форме `:name`. Для подготовленного оператора с использованием заполнителей вопросительных знаков это будет позиция параметра с индексом 1.
     * @param   mixed           $variable        Имя переменной PHP для привязки к параметру оператора SQL.
     * @param   string          $dataType        Константа, соответствующая типу данных SQL. Это должен быть обработанный тип из QueryInterface.
     *
     * @return  void
     */
    private function bindValue(int|string $parameter, mixed $variable, string $dataType = ParameterType::STRING): void {
        $this->bindedValues[$parameter]    = $variable;
        $this->typesKeyMapping[$parameter] = $dataType;
    }

    /**
     * Закрывает курсор, позволяя выполнить оператор еще раз.
     *
     * @return  void
     */
    public function closeCursor(): void {
        if (!$this->result || !\is_resource($this->statement)) {
            return;
        }

        while (sqlsrv_fetch($this->statement)) {
            // Ничего не делаем (см. выше)
        }

        $this->result = false;
    }

    /**
     * Извлекает SQLSTATE, связанный с последней операцией в дескрипторе инструкции.
     *
     * @return  string
     */
    public function errorCode(): string {
        $errors = sqlsrv_errors(SQLSRV_ERR_ERRORS);

        if ($errors) {
            return $errors[0]['code'];
        }

        return false;
    }

    /**
     * Извлекает расширенную информацию об ошибке, связанную с последней операцией над дескриптором инструкции.
     *
     * @return  string|array
     */
    public function errorInfo(): string|array {
        return sqlsrv_errors(SQLSRV_ERR_ERRORS);
    }

    /**
     * Выполняет подготовленный оператор.
     *
     * @param   array|null  $parameters  Массив значений, содержащий столько элементов, сколько связанных параметров в выполняемом операторе SQL.
     *
     * @return  boolean
     */
    public function execute(?array $parameters = null): bool {
        if (empty($this->bindedValues) && $parameters !== null) {
            $hasZeroIndex = array_key_exists(0, $parameters);

            foreach ($parameters as $key => $val) {
                $key = ($hasZeroIndex && is_numeric($key)) ? $key + 1 : $key;
                $this->bindValue($key, $val);
            }
        }

        if (!$this->statement) {
            $this->statement = $this->prepare();
        }

        if (!sqlsrv_execute($this->statement)) {
            $errors = $this->errorInfo();

            throw new ExecutionFailureException($this->query, $errors[0]['message'], $errors[0]['code']);
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
     * @return  mixed  Возвращаемое значение этой функции в случае успеха зависит от типа выборки. Во всех случаях в случае неудачи возвращается логическое значение false.
     */
    public function fetch(?int $fetchStyle = null, int $cursorOrientation = FetchOrientation::NEXT, int $cursorOffset = 0): mixed {
        if (!$this->result) {
            return false;
        }

        $fetchStyle = $fetchStyle ?: $this->defaultFetchStyle;

        if ($fetchStyle === FetchMode::COLUMN) {
            return $this->fetchColumn();
        }

        if (isset($this->fetchMap[$fetchStyle])) {
            return sqlsrv_fetch_array($this->statement, $this->fetchMap[$fetchStyle]) ?: false;
        }

        if (\in_array($fetchStyle, [FetchMode::STANDARD_OBJECT, FetchMode::CUSTOM_OBJECT], true)) {
            return sqlsrv_fetch_object($this->statement, $this->defaultObjectClass) ?: false;
        }

        throw new \InvalidArgumentException("Неизвестный тип получения '{$fetchStyle}'");
    }

    /**
     * Возвращает один столбец из следующей строки результирующего набора.
     *
     * @param   integer  $columnIndex   Номер столбца с индексом 0, который вы хотите получить из строки.
     *                                  Если значение не указано, извлекается первый столбец.
     *
     * @return  mixed    Возвращает один столбец из следующей строки результирующего набора или логическое значение false, если строк больше нет.
     */
    public function fetchColumn(int $columnIndex = 0): mixed {
        $row = $this->fetch(FetchMode::NUMERIC);

        if ($row === false) {
            return false;
        }

        return $row[$columnIndex] ?? null;
    }

    /**
     * Подготавливает ресурс инструкции SQL Server к выполнению.
     *
     * @return  resource
     */
    private function prepare() {
        $params  = [];
        $options = [];

        foreach ($this->bindedValues as $key => &$value) {
            $variable = [
                &$value,
                SQLSRV_PARAM_IN,
            ];

            if ($this->typesKeyMapping[$key] === $this->parameterTypeMapping[ParameterType::LARGE_OBJECT]) {
                $variable[] = $this->typesKeyMapping[$key];
                $variable[] = SQLSRV_SQLTYPE_VARBINARY('max');
            }

            if (isset($this->parameterKeyMapping[$key])) {
                $paramKey = $this->parameterKeyMapping[$key];

                foreach ($paramKey as $currentKey) {
                    $params[$currentKey] = $variable;
                }
            } else {
                $params[] = $variable;
            }
        }

        unset($value);

        if (strncmp(strtoupper(ltrim($this->query)), 'SELECT', \strlen('SELECT')) === 0) {
            $options = ['Scrollable' => SQLSRV_CURSOR_KEYSET];
        }

        $statement = sqlsrv_prepare($this->connection, $this->query, $params, $options);

        if (!$statement) {
            $errors = $this->errorInfo();

            throw new PrepareStatementFailureException($errors[0]['message'], $errors[0]['code']);
        }

        return $statement;
    }

    /**
     * Возвращает количество строк, на которые повлиял последний оператор SQL.
     *
     * @return  integer
     */
    public function rowCount(): int {
        if (strncmp(strtoupper(ltrim($this->query)), 'SELECT', \strlen('SELECT')) === 0) {
            return sqlsrv_num_rows($this->statement);
        }

        return sqlsrv_rows_affected($this->statement);
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

        if (isset($args[0])) {
            $this->defaultObjectClass = $args[0];
        }
    }
}
