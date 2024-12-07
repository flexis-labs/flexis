<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Голосовой интерфейс.
 */
interface StemmerInterface {
    /**
     * Метод для проверки токена и возврата корня.
     *
     * @param   string  $token  Токен, который нужно остановить.
     * @param   string  $lang   Язык токена.
     *
     * @return  string  Корневой токен.
     */
    public function stem(string $token, string $lang): string;
}
