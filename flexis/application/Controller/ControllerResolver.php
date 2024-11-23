<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application\Controller;

use Flexis\Controller\ControllerInterface;
use Flexis\Router\ResolvedRoute;

/**
 * Разрешает контроллер для данного маршрута.
 */
class ControllerResolver implements ControllerResolverInterface {
    /**
     * Разрешение контроллера для маршрута
     *
     * @param  ResolvedRoute  $route  Маршрут для разрешения контроллера для
     *
     * @return  callable
     *
     * @throws  \InvalidArgumentException
     */
    public function resolve(ResolvedRoute $route): callable {
        $controller = $route->getController();

        if (\is_array($controller)) {
            if (isset($controller[0]) && \is_string($controller[0]) && isset($controller[1])) {
                if (!\class_exists($controller[0])) {
                    throw new \InvalidArgumentException(
                        \sprintf('Невозможно разрешить контроллер для URI `%s`', $route->getUri())
                    );
                }

                try {
                    $controller[0] = $this->instantiateController($controller[0]);
                } catch (\ArgumentCountError $error) {
                    throw new \InvalidArgumentException(
                        \sprintf(
                            'Контроллер `%s` имеет необходимые аргументы конструктора, не может создать экземпляр класса.',
                            $controller[0]
                        ),
                        0,
                        $error
                    );
                }
            }

            if (!\is_callable($controller)) {
                throw new \InvalidArgumentException(
                    \sprintf('Невозможно разрешить контроллер для URI `%s`', $route->getUri())
                );
            }

            return $controller;
        }

        if (\is_object($controller)) {
            if (!\is_callable($controller)) {
                throw new \InvalidArgumentException(
                    \sprintf('Невозможно разрешить контроллер для URI `%s`', $route->getUri())
                );
            }

            return $controller;
        }

        if (\function_exists($controller)) {
            return $controller;
        }

        if (\is_string($controller) && \interface_exists(ControllerInterface::class)) {
            if (!\class_exists($controller)) {
                throw new \InvalidArgumentException(
                    \sprintf('Невозможно разрешить контроллер для URI `%s`', $route->getUri())
                );
            }

            try {
                return [$this->instantiateController($controller), 'execute'];
            } catch (\ArgumentCountError $error) {
                throw new \InvalidArgumentException(
                    \sprintf(
                        'Контроллер `%s` имеет необходимые аргументы конструктора, не может создать экземпляр класса.',
                        $controller
                    ),
                    0,
                    $error
                );
            }
        }

        throw new \InvalidArgumentException(\sprintf('Невозможно разрешить контроллер для URI `%s`', $route->getUri()));
    }

    /**
     * Создаёт экземпляр класса контроллера
     *
     * @param  string  $class  Класс для создания экземпляра
     *
     * @return  object  Экземпляр класса контроллера
     */
    protected function instantiateController(string $class): object {
        return new $class();
    }
}
