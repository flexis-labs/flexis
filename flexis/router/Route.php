<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router;

use Opis\Closure\SerializableClosure;

/**
 * Объект, представляющий определение маршрута.
 */
class Route implements \Serializable {
    /**
     * Контроллер, который обрабатывает этот маршрут
     *
     * @var    mixed
     */
    private mixed $controller;

    /**
     * Переменные по умолчанию, определенные маршрутом
     *
     * @var    array
     */
    private array $defaults = [];

    /**
     * Методы HTTP, поддерживаемые этим маршрутом
     *
     * @var    string[]
     */
    private array $methods;

    /**
     * Шаблон маршрута, используемый для сопоставления
     *
     * @var    string
     */
    private string $pattern;

    /**
     * Регулярное выражение пути, которое обрабатывает этот маршрут
     *
     * @var    string
     */
    private string $regex;

    /**
     * Переменные, определенные маршрутом
     *
     * @var    array
     */
    private array $routeVariables = [];

    /**
     * Массив правил регулярных выражений с использованием переменных маршрута.
     *
     * @var    array
     */
    private array $rules = [];

    /**
     * Конструктор.
     *
     * @param   array   $methods     Методы HTTP, поддерживаемые этим маршрутом
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления
     * @param   mixed   $controller  Контроллер, который обрабатывает этот маршрут
     * @param   array   $rules       Массив правил регулярных выражений с использованием переменных маршрута.
     * @param   array   $defaults    Переменные по умолчанию, определенные маршрутом
     */
    public function __construct(
        array $methods,
        string $pattern,
        mixed $controller,
        array $rules = [],
        array $defaults = []
    ) {
        $this->setMethods($methods);
        $this->setPattern($pattern);
        $this->setController($controller);
        $this->setRules($rules);
        $this->setDefaults($defaults);
    }

    /**
     * Анализирует шаблон маршрута, чтобы извлечь именованные переменные
     * и построить правильное регулярное выражение для использования при анализе маршрутов.
     *
     * @return  void
     */
    protected function buildRegexAndVarList(): void {
        $pattern = explode('/', trim(parse_url($this->getPattern(), PHP_URL_PATH), ' /'));
        $vars    = [];
        $regex   = [];

        foreach ($pattern as $segment) {
            if ($segment == '*') {
                $regex[] = '.*';
            } elseif (isset($segment[0]) && $segment[0] == '*') {
                $vars[]  = substr($segment, 1);
                $regex[] = '(.*)';
            } elseif (isset($segment[0]) && $segment[0] == '\\' && $segment[1] == '*') {
                $regex[] = '\*' . preg_quote(substr($segment, 2));
            } elseif ($segment == ':') {
                $regex[] = '([^/]*)';
            } elseif (isset($segment[0]) && $segment[0] == ':') {
                $varName = substr($segment, 1);
                $vars[]  = $varName;
                $regex[] = array_key_exists($varName, $this->getRules()) ? '(' . $this->getRules()[$varName] . ')' : '([^/]*)';
            } elseif (isset($segment[0]) && $segment[0] == '\\' && $segment[1] == ':') {
                $regex[] = preg_quote(substr($segment, 1));
            } else {
                $regex[] = preg_quote($segment);
            }
        }

        $this->setRegex(\chr(1) . '^' . implode('/', $regex) . '$' . \chr(1));
        $this->setRouteVariables($vars);
    }

    /**
     * Возвращает контроллер, который обрабатывает этот маршрут.
     *
     * @return  mixed
     */
    public function getController(): mixed {
        return $this->controller;
    }

    /**
     * Возвращает переменные по умолчанию, определенные маршрутом
     *
     * @return  array
     */
    public function getDefaults(): array {
        return $this->defaults;
    }

    /**
     * Возвращает методы HTTP, которые поддерживает этот маршрут.
     *
     * @return  string[]
     */
    public function getMethods(): array {
        return $this->methods;
    }

    /**
     * Возвращает шаблон маршрута, который будет использоваться для сопоставления.
     *
     * @return  string
     */
    public function getPattern(): string {
        return $this->pattern;
    }

    /**
     * Возвращает регулярное выражение пути, которое обрабатывает этот маршрут.
     *
     * @return  string
     */
    public function getRegex(): string {
        if (!$this->regex) {
            $this->buildRegexAndVarList();
        }

        return $this->regex;
    }

    /**
     * Возвращает переменные, определенные маршрутом
     *
     * @return  array
     */
    public function getRouteVariables(): array {
        if (!$this->regex) {
            $this->buildRegexAndVarList();
        }

        return $this->routeVariables;
    }

    /**
     * Возвращает правила регулярных выражений, заданные с помощью переменных маршрута.
     *
     * @return  array
     */
    public function getRules(): array {
        return $this->rules;
    }

    /**
     * Устанавливает контроллер, который обрабатывает этот маршрут.
     *
     * @param   mixed  $controller  Контроллер, который обрабатывает этот маршрут
     *
     * @return  $this
     */
    public function setController(mixed $controller): self {
        $this->controller = $controller;

        return $this;
    }

    /**
     * Устанавливает переменные по умолчанию, определенные маршрутом
     *
     * @param   array  $defaults  Переменные по умолчанию, определенные маршрутом
     *
     * @return  $this
     */
    public function setDefaults(array $defaults): self {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * Устанавливает методы HTTP, которые поддерживает этот маршрут.
     *
     * @param   array  $methods  Методы HTTP, поддерживаемые этим маршрутом
     *
     * @return  $this
     */
    public function setMethods(array $methods): self {
        $this->methods = array_map('strtoupper', $methods);

        return $this;
    }

    /**
     * Устанавливает шаблон маршрута, который будет использоваться для сопоставления
     *
     * @param   string  $pattern  Шаблон маршрута, используемый для сопоставления
     *
     * @return  $this
     */
    public function setPattern(string $pattern): self {
        $this->pattern = $pattern;

        $this->setRegex('');
        $this->setRouteVariables([]);

        return $this;
    }

    /**
     * Устанавливает регулярное выражение пути, которое обрабатывает этот маршрут
     *
     * @param   string  $regex  Регулярное выражение пути, которое обрабатывает этот маршрут
     *
     * @return  $this
     */
    public function setRegex(string $regex): self {
        $this->regex = $regex;

        return $this;
    }

    /**
     * Устанавливает переменные, определенные маршрутом
     *
     * @param   array  $routeVariables  Переменные, определенные маршрутом
     *
     * @return  $this
     */
    public function setRouteVariables(array $routeVariables): self {
        $this->routeVariables = $routeVariables;

        return $this;
    }

    /**
     * Устанавливает правила регулярных выражений, используя переменные маршрута.
     *
     * @param   array  $rules  Правила, определенные маршрутом
     *
     * @return  $this
     */
    public function setRules(array $rules): self {
        $this->rules = $rules;

        return $this;
    }

    /**
     * Сериализует маршрут.
     *
     * @return  string  Сериализованный маршрут.
     */
    public function serialize(): string {
        return serialize($this->__serialize());
    }

    /**
     Сериализует маршрут.
     *
     * @return  array  Данные, подлежащие сериализации.
     */
    public function __serialize() {
        $controller = $this->getController();

        if ($controller instanceof \Closure) {
            if (!class_exists(SerializableClosure::class)) {
                throw new \RuntimeException(
                    \sprintf(
                        'Невозможно сериализовать маршрут для шаблона «%s», поскольку контроллер является замыканием. '
                        . 'Установите пакет «opis/closure» для сериализации замыканий.',
                        $this->getPattern()
                    )
                );
            }

            $controller = new SerializableClosure($controller);
        }

        return [
            'controller'     => $controller,
            'defaults'       => $this->getDefaults(),
            'methods'        => $this->getMethods(),
            'pattern'        => $this->getPattern(),
            'regex'          => $this->getRegex(),
            'routeVariables' => $this->getRouteVariables(),
            'rules'          => $this->getRules(),
        ];
    }

    /**
     * Десериализовать маршрут.
     *
     * @param   string  $serialized  Сериализованный маршрут.
     *
     * @return  void
     */
    public function unserialize(string $serialized): void {
        $this->__unserialize(unserialize($serialized));
    }

    /**
     * Десериализовать маршрут.
     *
     * @param   array  $data  Сериализованный маршрут.
     *
     * @return  void
     */
    public function __unserialize(array $data) {
        $this->controller     = $data['controller'];
        $this->defaults       = $data['defaults'];
        $this->methods        = $data['methods'];
        $this->pattern        = $data['pattern'];
        $this->regex          = $data['regex'];
        $this->routeVariables = $data['routeVariables'];
        $this->rules          = $data['rules'];
    }
}
