<?php

/**
 * Часть пакета Flexis Framework String.
 */

namespace Flexis\String;

use Algo26\IdnaConvert\Exception\AlreadyPunycodeException;
use Algo26\IdnaConvert\Exception\InvalidCharacterException;
use Algo26\IdnaConvert\ToIdn;
use Algo26\IdnaConvert\ToUnicode;
use Flexis\Uri\UriHelper;

/**
 * Flexis Framework String Punycode Class
 *
 * Класс для обработки URL-адресов UTF-8.
 * Обертывает библиотеку Punycode
 * Все функции предполагают допустимость URL-адресов utf-8.
 */
abstract class PunycodeHelper {
    /**
     * Преобразует строку UTF-8 в строку Punycode.
     *
     * @param   string  $utfString  Строка UTF-8 для преобразования
     *
     * @return  string  Строка Punycode
     */
    public static function toPunycode(string $utfString): string {
        try {
            $converted = (new ToIdn())->convert($utfString);
        } catch (AlreadyPunycodeException|InvalidCharacterException $e) {
            $converted = $utfString;
        }

        return $converted;
    }

    /**
     * Преобразует строку Punycode в строку UTF-8.
     *
     * @param   string  $punycodeString  Строка Punycode для преобразования
     *
     * @return  string  URL-адрес UF-8
     */
    public static function fromPunycode(string $punycodeString): string {
        return (new ToUnicode())->convert($punycodeString);
    }

    /**
     * Преобразует URL-адрес UTF-8 в URL-адрес Punycode.
     *
     * @param   string  $uri  URL-адрес UTF-8 для преобразования
     *
     * @return  string  URL-адрес Punycode
     */
    public static function urlToPunycode(string $uri): string {
        $parsed = UriHelper::parse_url($uri);

        if (!isset($parsed['host']) || $parsed['host'] == '') {
            return $uri;
        }

        $host         = $parsed['host'];
        $hostExploded = explode('.', $host);
        $newhost      = '';

        foreach ($hostExploded as $hostex) {
            $hostex = static::toPunycode($hostex);
            $newhost .= $hostex . '.';
        }

        $newhost = substr($newhost, 0, -1);
        $newuri  = '';

        if (!empty($parsed['scheme'])) {
            $newuri .= $parsed['scheme'] . '://';
        }

        if (!empty($newhost)) {
            $newuri .= $newhost;
        }

        if (!empty($parsed['port'])) {
            $newuri .= ':' . $parsed['port'];
        }

        if (!empty($parsed['path'])) {
            $newuri .= $parsed['path'];
        }

        if (!empty($parsed['query'])) {
            $newuri .= '?' . $parsed['query'];
        }

        if (!empty($parsed['fragment'])) {
            $newuri .= '#' . $parsed['fragment'];
        }

        return $newuri;
    }

    /**
     * Преобразует URL-адрес Punycode в URL-адрес UTF-8.
     *
     * @param   string  $uri  URL-адрес Punycode для преобразования
     *
     * @return  string  URL-адрес UTF-8
     */
    public static function urlToUTF8(string $uri): string {
        if (empty($uri)) {
            return '';
        }

        $parsed = UriHelper::parse_url($uri);

        if (!isset($parsed['host']) || $parsed['host'] == '') {
            return $uri;
        }

        $host         = $parsed['host'];
        $hostExploded = explode('.', $host);
        $newhost      = '';

        foreach ($hostExploded as $hostex) {
            $hostex = self::fromPunycode($hostex);
            $newhost .= $hostex . '.';
        }

        $newhost = substr($newhost, 0, -1);
        $newuri  = '';

        if (!empty($parsed['scheme'])) {
            $newuri .= $parsed['scheme'] . '://';
        }

        if (!empty($newhost)) {
            $newuri .= $newhost;
        }

        if (!empty($parsed['port'])) {
            $newuri .= ':' . $parsed['port'];
        }

        if (!empty($parsed['path'])) {
            $newuri .= $parsed['path'];
        }

        if (!empty($parsed['query'])) {
            $newuri .= '?' . $parsed['query'];
        }

        if (!empty($parsed['fragment'])) {
            $newuri .= '#' . $parsed['fragment'];
        }

        return $newuri;
    }

    /**
     * Преобразует электронное письмо UTF-8 в электронное письмо Punycode.
     * Предполагается, что действительный адрес электронной почты
     *
     * @param   string  $email  Электронная почта UTF-8 для преобразования
     *
     * @return  string  Электронная почта в Punycode
     */
    public static function emailToPunycode(string $email): string {
        $explodedAddress = explode('@', $email);

        $newEmail = $explodedAddress[0];

        if (!empty($explodedAddress[1])) {
            $domainExploded = explode('.', $explodedAddress[1]);
            $newdomain      = '';

            foreach ($domainExploded as $domainex) {
                $domainex = static::toPunycode($domainex);
                $newdomain .= $domainex . '.';
            }

            $newdomain = substr($newdomain, 0, -1);
            $newEmail .= '@' . $newdomain;
        }

        return $newEmail;
    }

    /**
     * Преобразует электронное письмо Punycode в электронное письмо UTF-8.
     * Предполагается, что действительный адрес электронной почты
     *
     * @param   string  $email  Письмо в Punycode, которое нужно изменить
     *
     * @return  string  Электронная почта в Punycode
     */
    public static function emailToUTF8(string $email): string {
        $explodedAddress = explode('@', $email);

        $newEmail = $explodedAddress[0];

        if (!empty($explodedAddress[1])) {
            $domainExploded = explode('.', $explodedAddress[1]);
            $newdomain      = '';

            foreach ($domainExploded as $domainex) {
                $domainex = static::fromPunycode($domainex);
                $newdomain .= $domainex . '.';
            }

            $newdomain = substr($newdomain, 0, -1);
            $newEmail .= '@' . $newdomain;
        }

        return $newEmail;
    }
}
