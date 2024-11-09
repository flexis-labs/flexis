<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry\Format;

use Flexis\Registry\Factory;
use Flexis\Registry\FormatInterface;

/**
 * Обработчик формата JSON для реестра.
 */
class Json implements FormatInterface {
    /**
     * Преобразует объект в строку формата JSON.
     *
     * @param  object  $object   Объект источника данных.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  string  Строка в формате JSON.
     */
    public function objectToString(object $object, array $options = []): string {
        $bitMask = $options['bitmask'] ?? 0;
        $depth   = $options['depth'] ?? 512;

        return \json_encode($object, $bitMask, $depth);
    }

    /**
     * Разберите строку в формате JSON и преобразуйте ее в объект.
     *
     * Если строка не в формате JSON, этот метод попытается проанализировать ее как формат INI.
     *
     * @param string $data     Строка в формате JSON для преобразования.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  object   Объект данных.
     *
     * @throws  \RuntimeException.0
     */
    public function stringToObject(string $data, array $options = ['processSections' => false]): object {
        $data = \trim($data);

        if (empty($data)) {
            return new \stdClass();
        }

        $decoded = \json_decode($data);

        // Проверим наличие ошибки декодирования данных
        if ($decoded === null && \json_last_error() !== JSON_ERROR_NONE) {
            // Если это ini-файл, анализируйте его как ini.
            if ($data !== '' && $data[0] !== '{') {
                return Factory::getFormat('Ini')->stringToObject($data, $options);
            }

            throw new \RuntimeException(\sprintf('Ошибка декодирования данных JSON: %s', \json_last_error_msg()));
        }

        return (object) $decoded;
    }
}
