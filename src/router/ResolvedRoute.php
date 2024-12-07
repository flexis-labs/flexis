<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router;

/**
 * Объект, представляющий разрешенный маршрут.
 */
class ResolvedRoute {
    /**
     * Контроллер, который обрабатывает этот маршрут.
     *
     * @var    mixed
     */
    private mixed $controller;

    /**
     * Переменные, соответствующие маршруту.
     *
     * @var    array
     */
    private array $routeVariables;

    /**
     * URI для этого маршрута.
     *
     * @var    string
     */
    private string $uri;

    /**
     * Конструктор.
     *
     * @param   mixed   $controller      Контроллер, который обрабатывает этот маршрут.
     * @param   array   $routeVariables  Переменные, соответствующие маршруту.
     * @param   string  $uri             URI для этого маршрута.
     */
    public function __construct(mixed $controller, array $routeVariables, string $uri) {
        $this->controller     = $controller;
        $this->routeVariables = $routeVariables;
        $this->uri            = $uri;
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
     * Возвращает переменные, соответствующие маршруту.
     *
     * @return  array
     */
    public function getRouteVariables(): array {
        return $this->routeVariables;
    }

    /**
     * Извлекает URL-адрес для этого маршрута.
     *
     * @return  string
     */
    public function getUri(): string {
        return $this->uri;
    }
}
