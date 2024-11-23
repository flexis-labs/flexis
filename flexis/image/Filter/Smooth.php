<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image\Filter;

use Flexis\Image\ImageFilter;

/**
 * Класс Image Filter регулирует плавность изображения.
 */
class Smooth extends ImageFilter {
    /**
     * Метод применения фильтра к ресурсу изображения.
     *
     * @param   array  $options  Массив опций для фильтра.
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     */
    public function execute(array $options = []): void {
        if (!isset($options[IMG_FILTER_SMOOTH]) || !\is_int($options[IMG_FILTER_SMOOTH])) {
            throw new \InvalidArgumentException('Не было задано допустимое значение сглаживания. Ожидается целое число.');
        }

        imagefilter($this->handle, IMG_FILTER_SMOOTH, $options[IMG_FILTER_SMOOTH]);
    }
}
