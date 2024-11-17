<?php

/**
 * Часть пакета Flexis Input Framework.
 */

namespace Flexis\Input;

use Flexis\Filter;
use Flexis\Filter\InputFilter;

/**
 * Input Базовый класс
 *
 * Это абстрактный класс ввода, используемый для управления получением данных из среды приложения.
 *
 * @property-read    Input   $get
 * @property-read    Input   $post
 * @property-read    Input   $request
 * @property-read    Input   $server
 * @property-read    Input   $env
 * @property-read    Files   $files
 * @property-read    Cookie  $cookie
 * @property-read    Json    $json
 *
 * @method      integer  getInt($name, $default = null)       Возвращает целое число со знаком.
 * @method      integer  getUint($name, $default = null)      Возвращает беззнаковое целое число.
 * @method      float    getFloat($name, $default = null)     Возвращает число с плавающей запятой.
 * @method      boolean  getBool($name, $default = null)      Возвращает логическое значение.
 * @method      string   getWord($name, $default = null)      Возвращает слово.
 * @method      string   getAlnum($name, $default = null)     Возвращает буквенно-цифровую строку.
 * @method      string   getCmd($name, $default = null)       Возвращает строку, отфильтрованную CMD.
 * @method      string   getBase64($name, $default = null)    Возвращает строку в кодировке Base64.
 * @method      string   getString($name, $default = null)    Возвращает веревку.
 * @method      string   getHtml($name, $default = null)      Возвращает HTML-строку.
 * @method      string   getPath($name, $default = null)      Возвращает путь к файлу.
 * @method      string   getUsername($name, $default = null)  Возвращает имя пользователя.
 * @method      mixed    getRaw($name, $default = null)       Возвращает нефильтрованное значение.
 */
class Input implements \Countable {
    /**
     * Контейнер с разрешенными суперглобальными объектами.
     *
     * @var    array
     */
    private const array ALLOWED_GLOBALS = ['REQUEST', 'GET', 'POST', 'FILES', 'SERVER', 'ENV'];

    /**
     * Массив параметров для экземпляра ввода.
     *
     * @var    array
     */
    protected array $options = [];

    /**
     * Объект фильтра для использования.
     *
     * @var    InputFilter
     */
    protected InputFilter $filter;

    /**
     * Входные данные.
     *
     * @var    array
     */
    protected array $data = [];

    /**
     * Входные объекты
     *
     * @var    Input[]
     */
    protected array $inputs = [];

    /**
     * Конструктор.
     *
     * @param   array|null  $source   Необязательные исходные данные. Если этот параметр опущен, используется копия серверной переменной «_REQUEST».
     * @param   array       $options  Необязательный ассоциативный массив параметров конфигурации:
     *                                фильтр: экземпляр Filter\Input. Если этот параметр опущен, инициализируется фильтр по умолчанию.
     */
    public function __construct(?array $source = null, array $options = []) {
        $this->data    = $source ?? $_REQUEST;
        $this->filter  = $options['filter'] ?? new InputFilter();
        $this->options = $options;
    }

    /**
     * Волшебный метод для получения входного объекта.
     *
     * @param   mixed  $name  Имя входного объекта, который требуется получить.
     *
     * @return  Input|null  Входной объект запроса.
     */
    public function __get(mixed $name): ?self {
        if (isset($this->inputs[$name])) {
            return $this->inputs[$name];
        }

        $className = __NAMESPACE__ . '\\' . ucfirst($name);

        if (class_exists($className)) {
            $this->inputs[$name] = new $className(null, $this->options);

            return $this->inputs[$name];
        }

        $superGlobal = '_' . strtoupper($name);

        if (\in_array(strtoupper($name), self::ALLOWED_GLOBALS, true) && isset($GLOBALS[$superGlobal])) {
            $this->inputs[$name] = new self($GLOBALS[$superGlobal], $this->options);

            return $this->inputs[$name];
        }

        $trace = debug_backtrace();
        trigger_error(
            'Неопределенное свойство через __get(): ' . $name . ' в ' . $trace[0]['file'] . ' строка ' . $trace[0]['line'],
            E_USER_NOTICE
        );

        return null;
    }

    /**
     * Получите количество переменных.
     *
     * @return  integer  Количество переменных во входных данных.
     * @see     Countable::count()
     */
    #[\ReturnTypeWillChange]
    public function count(): int {
        return \count($this->data);
    }

    /**
     * Получает значение из входных данных.
     *
     * @param   string  $name     Имя значения, которое нужно получить.
     * @param   mixed   $default  Значение по умолчанию, которое возвращается, если переменная не существует.
     * @param   string  $filter   Фильтр, применяемый к значению.
     *
     * @return  mixed  The filtered input value.
     *
     * @see     \Flexis\Filter\InputFilter::clean()
     */
    public function get(string $name, mixed $default = null, string $filter = 'cmd'): mixed {
        if ($this->exists($name)) {
            return $this->filter->clean($this->data[$name], $filter);
        }

        return $default;
    }

    /**
     * Получает массив значений из запроса.
     *
     * @param   array  $vars        Ассоциативный массив ключей и типов применяемых фильтров.
     *                              Если пусто и источник данных имеет значение NULL, все входные данные будут возвращены,
     *                              но отфильтрованы с использованием регистра по умолчанию в InputFilter::clean.
     * @param   mixed  $datasource  Массив для получения данных или значение null.
     *
     * @return  array  Отфильтрованные входные данные.
     */
    public function getArray(array $vars = [], mixed $datasource = null): array {
        if (empty($vars) && $datasource === null) {
            $vars = $this->data;
        }

        $results = [];

        foreach ($vars as $k => $v) {
            if (\is_array($v)) {
                if ($datasource === null) {
                    $results[$k] = $this->getArray($v, $this->get($k, null, 'array'));
                } else {
                    $results[$k] = $this->getArray($v, $datasource[$k]);
                }
            } else {
                if ($datasource === null) {
                    $results[$k] = $this->get($k, null, $v);
                } elseif (isset($datasource[$k])) {
                    $results[$k] = $this->filter->clean($datasource[$k], $v);
                } else {
                    $results[$k] = $this->filter->clean(null, $v);
                }
            }
        }

        return $results;
    }

    /**
     * Получает экземпляр ввода, содержащий данные для текущего метода запроса.
     *
     * @return  Input
     */
    public function getInputForRequestMethod(): self {
        return match (strtoupper($this->getMethod())) {
            'GET' => $this->get,
            'POST' => $this->post,
            default => $this,
        };
    }

    /**
     * Устанавливает значение
     *
     * @param   string  $name   Имя значения, которое необходимо установить.
     * @param   mixed   $value  Значение, которое нужно присвоить входу.
     *
     * @return  void
     */
    public function set(string $name, mixed $value): void {
        $this->data[$name] = $value;
    }

    /**
     * Задаёт значение по умолчанию.
     * Значение будет установлено только в том случае, если для имени нет значения или оно равно нулю.
     *
     * @param   string  $name   Имя значения, которое необходимо определить.
     * @param   mixed   $value  Значение, которое нужно присвоить входу.
     *
     * @return  void
     */
    public function def(string $name, mixed $value): void {
        if (isset($this->data[$name])) {
            return;
        }

        $this->data[$name] = $value;
    }

    /**
     * Проверяет, существует ли имя значения.
     *
     * @param   string  $name  Имя значения.
     *
     * @return  boolean
     */
    public function exists(string $name): bool {
        return isset($this->data[$name]);
    }

    /**
     * Псевдоним для exists().
     *
     * @param   string  $name  Имя значения.
     *
     * @return  boolean
     */
    public function has(string $name): bool {
        return $this->exists($name);
    }

    /**
     * Магический метод получения отфильтрованных входных данных.
     *
     * @param   string  $name       Имя типа фильтра с префиксом «get».
     * @param   array   $arguments  [0] Имя переменной [1] Значение по умолчанию.
     *
     * @return  mixed   Отфильтрованное входное значение.
     */
    public function __call(string $name, array $arguments): mixed {
        if (str_starts_with($name, 'get')) {
            $filter = substr($name, 3);

            $default = null;

            if (isset($arguments[1])) {
                $default = $arguments[1];
            }

            return $this->get($arguments[0], $default, $filter);
        }

        $trace = debug_backtrace();
        trigger_error(
            'Вызов неопределенного метода через call(): ' . $name . ' в ' . $trace[0]['file'] . ' строка ' . $trace[0]['line'],
            E_USER_ERROR
        );
    }

    /**
     * Получает метод запроса.
     *
     * @return  string   Метод запроса.
     */
    public function getMethod(): string {
        return strtoupper($this->server->getCmd('REQUEST_METHOD'));
    }
}
