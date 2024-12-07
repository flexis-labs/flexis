<?php

/**
 * Часть пакета Flexis Uri Framework.
 */

namespace Flexis\Uri;

/**
 * Вспомогательный класс Uri.
 *
 * Этот класс предоставляет безопасную версию parse_url() для UTF-8.
 */
class UriHelper {
    /**
     * Имеет ли UTF-8 безопасную версию функции PHP parse_url.
     *
     * @param   string   $url        URL для анализа.
     * @param   integer  $component  Получить только определенный компонент URL.
     *
     * @return  array|boolean  Ассоциативный массив или false, если URL-адрес неправильно сформирован.
     *
     * @link    https://www.php.net/manual/ru/function.parse-url.php
     */
    public static function parse_url(string $url, int $component = -1): array|bool {
        $result = [];

        if (extension_loaded('mbstring') && mb_convert_encoding($url, 'ISO-8859-1', 'UTF-8') === $url) {
            return parse_url($url, $component);
        }

        $reservedUriCharactersMap = [
            '%21' => '!',
            '%2A' => '*',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
            '%3B' => ';',
            '%3A' => ':',
            '%40' => '@',
            '%26' => '&',
            '%3D' => '=',
            '%24' => '$',
            '%2C' => ',',
            '%2F' => '/',
            '%3F' => '?',
            '%23' => '#',
            '%5B' => '[',
            '%5D' => ']',
        ];

        $parts = parse_url(strtr(urlencode($url), $reservedUriCharactersMap), $component);

        return $parts ? array_map('urldecode', $parts) : $parts;
    }
}
