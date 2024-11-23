<?php

/**
 * Часть пакета Flexis DIP Framework.
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
