<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http;

use ArrayAccess;
use InvalidArgumentException;
use RuntimeException;

/**
 * Фабричный класс HTTP.
 */
class HttpFactory {
    /**
     * Метод для создания экземпляра Http.
     *
     * @param   array|ArrayAccess  $options   Массив опций клиента.
     * @param   array|string|null  $adapters  Адаптер (string) или очередь адаптеров (array),
     *                                        которые будут использоваться для связи.
     *
     * @return  Http
     *
     * @throws  InvalidArgumentException
     * @throws  RuntimeException
     */
    public function getHttp(array|ArrayAccess $options = [], array|string|null $adapters = null): Http {
        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        if (!$driver = $this->getAvailableDriver($options, $adapters)) {
            throw new RuntimeException('Нет драйвера транспорта.');
        }

        return new Http($options, $driver);
    }

    /**
     * Находит доступный объект TransportInterface для связи.
     *
     * @param   array|ArrayAccess  $options  Варианты создания объекта TransportInterface.
     * @param   array|string|null  $default  Адаптер (string) или очередь адаптеров (array) для использования.
     *
     * @return  TransportInterface|boolean  Подкласс интерфейса или логическое значение false, если адаптеры недоступны.
     *
     * @throws  InvalidArgumentException
     */
    public function getAvailableDriver(
        array|ArrayAccess $options = [],
        array|string|null $default = null
    ): TransportInterface|bool {

        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        if ($default === null) {
            $availableAdapters = $this->getHttpTransports();
        } else {
            settype($default, 'array');
            $availableAdapters = $default;
        }

        if (!\count($availableAdapters)) {
            return false;
        }

        foreach ($availableAdapters as $adapter) {
            $class = __NAMESPACE__ . '\\Transport\\' . ucfirst($adapter);

            if (class_exists($class)) {
                if ($class::isSupported()) {
                    return new $class($options);
                }
            }
        }

        return false;
    }

    /**
     * Возвращает обработчики транспорта HTTP
     *
     * @return  string[]  Массив доступных типов обработчиков транспорта
     *
     */
    public function getHttpTransports(): array {
        $names    = [];
        $iterator = new \DirectoryIterator(__DIR__ . '/Transport');

        /** @var \DirectoryIterator $file */
        foreach ($iterator as $file) {
            $fileName = $file->getFilename();

            if ($file->isFile() && $file->getExtension() == 'php') {
                $names[] = substr($fileName, 0, strrpos($fileName, '.'));
            }
        }

        sort($names);

        $key = array_search('Curl', $names);

        if ($key) {
            unset($names[$key]);
            array_unshift($names, 'Curl');
        }

        return $names;
    }
}
