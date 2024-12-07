<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry;

use InvalidArgumentException;

/**
 * Фабричный класс для получения объектов реестра.
 */
class Factory {
    /**
     * Возвращает объект FormatInterface, создавая его, только если он еще не существует.
     *
     * @param  string  $type     Формат для загрузки.
     * @param  array   $options  Дополнительные возможности настройки объекта.
     *
     * @return  FormatInterface  Обработчик формата реестра.
     *
     * @throws  InvalidArgumentException
     */
    public static function getFormat(string $type, array $options = []): FormatInterface {
        // Обезопасим тип формата.
        $type = \strtolower(\preg_replace('/[^A-Z\d_]/i', '', $type));

        $localNamespace = __NAMESPACE__ . '\\Format';
        $namespace      = $options['format_namespace'] ?? $localNamespace;
        $class          = $namespace . '\\' . \ucfirst($type);

        if (!\class_exists($class)) {
            // Было ли нам предоставлено пользовательское пространство имен?  
            // Если нет, то мы больше ничего не можем сделать.
            if ($namespace === $localNamespace) {
                throw new InvalidArgumentException(\sprintf('Невозможно загрузить класс формата для типа. "%s".', $type), 500);
            }

            $class = $localNamespace . '\\' . \ucfirst($type);

            if (!\class_exists($class)) {
                throw new InvalidArgumentException(\sprintf('Невозможно загрузить класс формата для типа. "%s".', $type), 500);
            }
        }

        return new $class();
    }
}
