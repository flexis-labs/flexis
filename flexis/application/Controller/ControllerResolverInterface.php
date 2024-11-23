<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application\Controller;

use Flexis\Router\ResolvedRoute;

/**
 * Интерфейс, определяющий преобразователь контроллера.
 */
interface ControllerResolverInterface {
    /**
     * Разрешение контроллера для маршрута.
     *
     * @param   ResolvedRoute  $route  Маршрут для разрешения контроллера.
     *
     * @return  callable
     * @throws  \InvalidArgumentException
     */
    public function resolve(ResolvedRoute $route): callable;
}
