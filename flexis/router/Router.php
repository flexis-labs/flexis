<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router;

/**
 * Маршрутизатор пути.
 */
class Router implements RouterInterface, \Serializable {
    /**
     * Массив объектов Route, определяющих поддерживаемые пути.
     *
     * @var    Route[]
     */
    protected array $routes = [];

    /**
     * Конструктор.
     *
     * @param   Route[]|array[]  $routes  Список карт маршрутов или объектов маршрутов для добавления в маршрутизатор.
     */
    public function __construct(array $routes = []) {
        if (!empty($routes)) {
            $this->addRoutes($routes);
        }
    }

    /**
     * Добавляет маршрут к роутеру.
     *
     * @param   Route  $route  Определение маршрута
     *
     * @return  $this
     */
    public function addRoute(Route $route): RouterInterface {
        $this->routes[] = $route;

        return $this;
    }

    /**
     * Добавляет в маршрутизатор массив карт маршрутов или объектов.
     *
     * @param   Route[]|array[]  $routes  Список карт маршрутов или объектов маршрутов для добавления в маршрутизатор.
     *
     * @return  $this
     * @throws  \UnexpectedValueException Если отсутствуют ключи «шаблон» или «контроллер» в массиве сопоставлений.
     */
    public function addRoutes(array $routes): RouterInterface {
        foreach ($routes as $route) {
            if ($route instanceof Route) {
                $this->addRoute($route);
            } else {
                if (! array_key_exists('pattern', $route)) {
                    throw new \UnexpectedValueException('Карта маршрутов должна содержать переменную шаблона.');
                }

                if (! array_key_exists('controller', $route)) {
                    throw new \UnexpectedValueException('Карта маршрутов должна содержать переменную контроллера.');
                }

                $defaults = $route['defaults'] ?? [];
                $rules    = $route['rules'] ?? [];
                $methods  = $route['methods'] ?? ['GET'];

                $this->addRoute(new Route($methods, $route['pattern'], $route['controller'], $rules, $defaults));
            }
        }

        return $this;
    }

    /**
     * Возвращает маршруты, зарегистрированные на этом маршрутизаторе.
     *
     * @return  Route[]
     */
    public function getRoutes(): array {
        return $this->routes;
    }

    /**
     * Анализирует данный маршрут и возвращает информацию о маршруте, включая контроллер, назначенный этому маршруту.
     *
     * @param   string  $route   Строка маршрута, для которой нужно найти и выполнить контроллер.
     * @param   string  $method  Соответствующий метод запроса должен быть допустимым методом HTTP-запроса.
     *
     * @return  ResolvedRoute
     * @throws  Exception\MethodNotAllowedException если маршрут найден, но не поддерживает метод запроса
     * @throws  Exception\RouteNotFoundException если маршрут не найден
     */
    public function parseRoute(string $route, string $method = 'GET'): ResolvedRoute {
        $method = strtoupper($method);
        $route  = trim(parse_url($route, PHP_URL_PATH), ' /');

        foreach ($this->routes as $rule) {
            if (preg_match($rule->getRegex(), $route, $matches)) {
                if (!empty($rule->getMethods()) && !\in_array($method, $rule->getMethods())) {
                    throw new Exception\MethodNotAllowedException(
                        array_unique($rule->getMethods()),
                        sprintf('Маршрут `%s` не поддерживает запросы `%s`.', $route, strtoupper($method)),
                        405
                    );
                }

                $vars = $rule->getDefaults();

                foreach ($rule->getRouteVariables() as $i => $var) {
                    $vars[$var] = $matches[$i + 1];
                }

                return new ResolvedRoute($rule->getController(), $vars, $route);
            }
        }

        throw new Exception\RouteNotFoundException(sprintf('Невозможно обработать запрос на маршрут `%s`.', $route), 404);
    }

    /**
     * Добавляет маршрут GET к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function get(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['GET'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет POST-маршрут к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function post(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['POST'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут PUT к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function put(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['PUT'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут DELETE к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function delete(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['DELETE'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут HEAD к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function head(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['HEAD'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут OPTIONS к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function options(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['OPTIONS'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут TRACE к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function trace(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['TRACE'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут PATCH к маршрутизатору.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function patch(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route(['PATCH'], $pattern, $controller, $rules, $defaults));
    }

    /**
     * Добавляет маршрут к маршрутизатору, который принимает все методы запросов.
     *
     * @param   string  $pattern     Шаблон маршрута, используемый для сопоставления.
     * @param   mixed   $controller  Контроллер для сопоставления с данным шаблоном.
     * @param   array   $rules       Массив правил регулярных выражений, кодированных с использованием переменных маршрута.
     * @param   array   $defaults    Массив значений по умолчанию, которые используются при сопоставлении URL-адреса.
     *
     * @return  $this
     */
    public function all(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface {
        return $this->addRoute(new Route([], $pattern, $controller, $rules, $defaults));
    }

    /**
     Сериализует маршрутизатор.
     *
     * @return  string  Серийный маршрутизатор.
     */
    public function serialize(): string {
        return serialize($this->__serialize());
    }

    /**
     Сериализует маршрутизатор.
     *
     * @return  array  Данные, подлежащие сериализации
     */
    public function __serialize() {
        return [
            'routes' => $this->routes,
        ];
    }

    /**
     * Десериализовать маршрутизатор.
     *
     * @param   string  $serialized  Серийный маршрутизатор.
     *
     * @return  void
     */
    public function unserialize(string $serialized): void {
        $this->__unserialize(unserialize($serialized));
    }

    /**
     * Десериализовать маршрутизатор.
     *
     * @param   array  $data  Серийный маршрутизатор.
     *
     * @return  void
     */
    public function __unserialize(array $data) {
        $this->routes = $data['routes'];
    }
}
