<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Service;

use Flexis\Database\DatabaseDriver;
use Flexis\Database\DatabaseFactory;
use Flexis\Database\DatabaseInterface;
use Flexis\DIP\Container;
use Flexis\DIP\ServiceProviderInterface;
use Flexis\Registry\Registry;

/**
 * Поставщик услуг базы данных
 */
class DatabaseProvider implements ServiceProviderInterface {
    /**
     * Регистрирует поставщика услуг в контейнере DI.
     *
     * @param   Container  $container  Контейнер DI.
     *
     * @return  void
     */
    public function register(Container $container): void {
        $container->alias('DatabaseDriver', DatabaseInterface::class)
            ->alias('db', DatabaseInterface::class)
            ->alias(DatabaseDriver::class, DatabaseInterface::class)
            ->share(
                DatabaseDriver::class,
                function (Container $container) {
                    /** @var Registry $config */
                    $config  = $container->get('config');

                    $options = (array) $config->get('database');

                    return $container->get(DatabaseFactory::class)->getDriver($options['driver'], $options);
                }
            );

        $container->share(
            DatabaseFactory::class,
            function (Container $container) {
                return new DatabaseFactory();
            }
        );
    }
}
