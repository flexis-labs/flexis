<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application\Controller;

use Psr\Container\ContainerInterface;

/**
 * Резолвер контроллера, который поддерживает создание контроллеров из контейнера, совместимого с PSR-11.
 *
 * Контроллеры должны быть зарегистрированы в контейнере, используя свое полное доменное имя в качестве служебного ключа.
 */
class ContainerControllerResolver extends ControllerResolver {
    /**
     * Контейнер для поиска контроллеров.
     *
     * @var    ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Конструктор.
     *
     * @param   ContainerInterface  $container  Контейнер для поиска контроллеров.
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /**
     * Создаёт экземпляр класса контроллера
     *
     * @param   string  $class  Класс для создания экземпляра
     *
     * @return  object  Экземпляр класса контроллера
     */
    protected function instantiateController(string $class): object {
        if ($this->container->has($class)) {
            return $this->container->get($class);
        }

        return parent::instantiateController($class);
    }
}
