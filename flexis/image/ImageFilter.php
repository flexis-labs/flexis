<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image;

/**
 * Класс для управления изображением.
 */
abstract class ImageFilter {
    /**
     * @var    \GdImage  Дескриптор ресурса изображения.
     */
    protected \GdImage $handle;

    /**
     * Конструктор класса.
     *
     * @param   resource  $handle  Ресурс изображения, к которому применяется фильтр.
     *
     * @throws  \InvalidArgumentException
     * @throws  \RuntimeException
     */
    public function __construct($handle) {
        if (!\function_exists('imagefilter')) {
            throw new \RuntimeException('Функция imagefilter для PHP недоступна.');
        }

        if (!(\is_object($handle) && \get_class($handle) == 'GdImage')) {
            throw new \InvalidArgumentException('Дескриптор изображения недействителен для фильтра изображений.');
        }

        $this->handle = $handle;
    }

    /**
     * Метод применения фильтра к ресурсу изображения.
     *
     * @param   array  $options  Массив опций для фильтра.
     *
     * @return  void
     *
     */
    abstract public function execute(array $options = []): void;
}
