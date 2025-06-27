<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP;

/**
 * Определяет интерфейс для класса, поддерживающего работу с контейнерами.
 */
interface ContainerAwareInterface {
    /**
     * Установка контейнера DI.
     *
     * @param   Container  $container  DI контейнер.
     *
     * @return  mixed
     *
     */
    public function setContainer(Container $container);
}
