<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router;

/**
 * Интерфейс, определяющий маршрутизатор HTTP-пути.
 */
interface RouterInterface {
    /**
     * Добавляет маршрут к роутеру.
     *
     * @param   Route  $route  Определение маршрута
     *
     * @return  $this
     */
    public function addRoute(Route $route): RouterInterface;

    /**
     * Добавляет в маршрутизатор массив карт маршрутов или объектов.
     *
     * @param   Route[]|array[]  $routes  Список карт маршрутов или объектов маршрутов для добавления в маршрутизатор.
     *
     * @return  $this
     * @throws  \UnexpectedValueException  Если отсутствуют ключи «шаблон» или «контроллер» в массиве сопоставлений.
     */
    public function addRoutes(array $routes): RouterInterface;

    /**
     * Возвращает маршруты, зарегистрированные на этом маршрутизаторе.
     *
     * @return  Route[]
     */
    public function getRoutes(): array;

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
    public function parseRoute(string $route, string $method = 'GET'): ResolvedRoute;

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
    public function get(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function post(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function put(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function delete(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function head(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function options(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function trace(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function patch(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;

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
    public function all(string $pattern, mixed $controller, array $rules = [], array $defaults = []): RouterInterface;
}
