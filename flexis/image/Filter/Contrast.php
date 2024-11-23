<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image\Filter;

use Flexis\Image\ImageFilter;

/**
 * Класс Image Filter регулирует контрастность изображения.
 */
class Contrast extends ImageFilter {
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
        if (!isset($options[IMG_FILTER_CONTRAST]) || !\is_int($options[IMG_FILTER_CONTRAST])) {
            throw new \InvalidArgumentException('Не было указано достоверное значение контрастности. Ожидается целое число.');
        }

        imagefilter($this->handle, IMG_FILTER_CONTRAST, $options[IMG_FILTER_CONTRAST]);
    }
}
