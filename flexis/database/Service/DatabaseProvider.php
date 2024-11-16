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
                    $options = [
						'driver'   => 'pgsql',
						'host'     => 'localhost',
						'user'     => 'postgres',
						'password' => 'UZs96T8u3LNn',
						'database' => 'local_db',
						'prefix'   => 'qulpm_',
					];

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
