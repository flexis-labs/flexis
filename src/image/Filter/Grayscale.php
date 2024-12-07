<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image\Filter;

use Flexis\Image\ImageFilter;

/**
 * Класс Image Filter для преобразования изображения в оттенки серого.
 */
class Grayscale extends ImageFilter {
    /**
     * Метод применения фильтра к ресурсу изображения.
     *
     * @param   array  $options  Массив опций для фильтра.
     *
     * @return  void
     *
     */
    public function execute(array $options = []): void {
        imagefilter($this->handle, IMG_FILTER_GRAYSCALE);
    }
}
