<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language\Service;

use Flexis\DIP\Container;
use Flexis\DIP\ServiceProviderInterface;
use Flexis\Language\LanguageFactory;
use Flexis\Registry\Registry;

/**
 * Поставщик объектных услуг LanguageFactory.
 */
class LanguageFactoryProvider implements ServiceProviderInterface {
    /**
     * Регистрирует поставщика услуг в контейнере DI.
     *
     * @param   Container  $container  Контейнер DI.
     *
     * @return  void
     * @throws  \RuntimeException
     */
    public function register(Container $container): void {
        $container->alias('language', LanguageFactory::class)
            ->share(
            'Flexis\\Language\\LanguageFactory',
            function (Container $container) {
                $factory = new LanguageFactory();

                /** @var Registry $config */
                $config = $container->get('config');

                $baseLangDir = $config->get('language.basedir');
                $defaultLang = $config->get('language.default', 'ru-RU');

                if ($baseLangDir) {
                    $factory->setLanguageDirectory($baseLangDir);
                }

                $factory->setDefaultLanguage($defaultLang);

                return $factory;
            },
            true
        );
    }
}
