<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP;

use Flexis\DIP\Exception\ContainerNotFoundException;

/**
 * Определяет признак для класса, поддерживающего работу с контейнерами.
 */
trait ContainerAwareTrait {
    /**
     * DI контейнер.
     *
     * @var    Container
     */
    private $container;

    /**
     * Получение DI контейнера.
     *
     * @return  Container
     *
     * @throws  ContainerNotFoundException Может быть выброшен, если контейнер не был установлен.
     */
    protected function getContainer():Container {
        if ($this->container) {
            return $this->container;
        }

        throw new ContainerNotFoundException('Container not set in ' . \get_class($this));
    }

    /**
     * Установка DI контейнера.
     *
     * @param   Container  $container  DI контейнер.
     *
     * @return  $this
     *
     */
    public function setContainer(Container $container):Container {
        $this->container = $container;

        return $this;
    }
}
