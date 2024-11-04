<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP;

/**
 * Интерфейс для поставщика услуг.
 */
interface ServiceProviderInterface {
    /**
     * Регистрирует поставщика услуг с помощью контейнера DI.
     *
     * @param   Container  $container  DI контейнер.
     *
     * @return  void
     *
     */
    public function register(Container $container):void;
}
