<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry;

/**
 * Интерфейс, определяющий объект формата
 */
interface FormatInterface {
    /**
     * Преобразует объект в форматированную строку.
     *
     * @param  object  $object   Объект источника данных.
     * @param  array   $options  Массив опций форматтера.
     *
     * @return  string  Форматированная строка.
     */
    public function objectToString(object $object, array $options = []): string;

    /**
     * Преобразует форматированную строку в объект.
     *
     * @param  string  $data     Форматированная строка.
     * @param  array   $options  Массив опций форматтера.
     *
     * @return  object  Объект данных.
     *
     */
    public function stringToObject(string $data, array $options = []): object;
}
