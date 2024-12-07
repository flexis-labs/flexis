<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image\Filter;

use Flexis\Image\ImageFilter;

/**
 * Класс Image Filter регулирует яркость изображения.
 */
class Brightness extends ImageFilter {
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
        if (!isset($options[IMG_FILTER_BRIGHTNESS]) || !\is_int($options[IMG_FILTER_BRIGHTNESS])) {
            throw new \InvalidArgumentException('Не было задано допустимое значение яркости. Ожидается целое число.');
        }

        imagefilter($this->handle, IMG_FILTER_BRIGHTNESS, $options[IMG_FILTER_BRIGHTNESS]);
    }
}
