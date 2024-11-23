<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image\Filter;

use Flexis\Image\ImageFilter;

/**
 * Класс Image Filter заполняет фон цветом.
 */
class Backgroundfill extends ImageFilter {
    /**
     * Метод применения цвета фона к ресурсу изображения.
     *
     * @param   array  $options  Массив опций цвета фильтра фона.
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     */
    public function execute(array $options = []): void {
        if (!isset($options['color'])) {
            throw new \InvalidArgumentException('Значение цвета не указано. Ожидается строка или массив.');
        }

        $colorCode = $options['color'] ?? null;
        $width     = imagesx($this->handle);
        $height    = imagesy($this->handle);
        $rgba      = $this->sanitizeColor($colorCode);

        if (imageistruecolor($this->handle)) {
            imagealphablending($this->handle, false);
            imagesavealpha($this->handle, true);
        }

        $bg = imagecreatetruecolor($width, $height);
        imagesavealpha($bg, empty($rgba['alpha']));

        $color = imagecolorallocatealpha($bg, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);

        imagefill($bg, 0, 0, $color);
        imagecopy($bg, $this->handle, 0, 0, 0, 0, $width, $height);
        imagecopy($this->handle, $bg, 0, 0, 0, 0, $width, $height);
        imagedestroy($bg);
    }

    /**
     * Метод очистки значений цвета и/или преобразования в массив/
     *
     * @param   mixed  $input  Ассоциативный массив цветов и альфа или шестнадцатеричная строка RGBA,
     *                         когда альфа FF непрозрачна. По умолчанию черный и непрозрачный альфа.
     *
     * @return  array  Ассоциативный массив красного, зеленого, синего и альфа
     *
     *
     * @internal    '#FF0000FF' возвращает массив с альфа-значением 0 (непрозрачный)
     */
    protected function sanitizeColor(mixed $input): array {
        $colors = ['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0];

        if (\is_array($input)) {
            $colors = array_merge($colors, $input);
        } elseif (\is_string($input)) {
            $hex = ltrim($input, '#');

            $hexValues = [
                'red'   => substr($hex, 0, 2),
                'green' => substr($hex, 2, 2),
                'blue'  => substr($hex, 4, 2),
                'alpha' => substr($hex, 6, 2),
            ];

            $colors = array_map('hexdec', $hexValues);

            if (\strlen($hex) > 6) {
                $colors['alpha'] = floor((255 - $colors['alpha']) / 2);
            }
        } else {
            return $colors;
        }

        foreach ($colors as &$value) {
            $value = max(0, min(255, (int) $value));
        }

        $colors['alpha'] = min(127, $colors['alpha']);

        return $colors;
    }
}
